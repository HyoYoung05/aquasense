#include "environment_sensor.h"
#include "config.h"
#include "i2c_bus.h"
#include "pins.h"

#if AQUASENSE_ENABLE_ENS160_LIBRARY
#include <DFRobot_ENS160.h>
#endif
#if AQUASENSE_ENABLE_AHT2X_LIBRARY
#include <Adafruit_AHTX0.h>
#endif

namespace {
#if AQUASENSE_ENABLE_ENS160_LIBRARY
DFRobot_ENS160_I2C ens160(&Wire, AquaSenseConfig::ENS160_I2C_ADDRESS);
#endif
#if AQUASENSE_ENABLE_AHT2X_LIBRARY
Adafruit_AHTX0 aht2x;
#endif
volatile bool ens160DataReady = false;
bool ens160Initialized = false;
bool aht2xInitialized = false;
uint32_t lastEnsReadMs = 0;
uint32_t lastAhtReadMs = 0;
uint32_t lastEnsInitMs = 0;
uint32_t lastAhtInitMs = 0;

void IRAM_ATTR markEns160DataReady() { ens160DataReady = true; }

bool tryBeginEns160(SensorData& data) {
#if AQUASENSE_ENABLE_ENS160_LIBRARY
  if (!AquaSenseConfig::ENABLE_ENS160 ||
      !i2cAddressStable(AquaSenseConfig::ENS160_I2C_ADDRESS,
                        AquaSenseConfig::I2C_STABLE_SCAN_COUNT)) return false;
  if (ens160.begin() != NO_ERR) {
    data.ens160State = SensorState::kError;
    return false;
  }
  ens160.setPWRMode(ENS160_STANDARD_MODE);
  if (AquaSenseConfig::ENS160_USE_INTERRUPT) {
    const uint8_t mode = static_cast<uint8_t>(ens160.eINTModeEN) |
                         static_cast<uint8_t>(ens160.eINTPinPP) |
                         static_cast<uint8_t>(ens160.eINTPinActiveHigh) |
                         static_cast<uint8_t>(ens160.eINTDataDrdyEN);
    ens160.setINTMode(mode);
    pinMode(AquaSensePins::ENS160_INT, INPUT);
    attachInterrupt(digitalPinToInterrupt(AquaSensePins::ENS160_INT),
                    markEns160DataReady, RISING);
  }
  data.ens160State = SensorState::kWarmingUp;
  return true;
#else
  (void)data;
  return false;
#endif
}

bool tryBeginAht2x(SensorData& data) {
#if AQUASENSE_ENABLE_AHT2X_LIBRARY
  if (!AquaSenseConfig::ENABLE_AHT2X ||
      !i2cAddressStable(AquaSenseConfig::AHT2X_I2C_ADDRESS,
                        AquaSenseConfig::I2C_STABLE_SCAN_COUNT)) return false;
  if (!aht2x.begin(&Wire, 0, AquaSenseConfig::AHT2X_I2C_ADDRESS)) {
    data.aht2xState = SensorState::kError;
    return false;
  }
  data.aht2xState = SensorState::kUnavailable;
  return true;
#else
  (void)data;
  return false;
#endif
}
}

void environmentSensorsBegin(SensorData& data) {
  data.ens160State = AquaSenseConfig::ENABLE_ENS160
                         ? SensorState::kUnavailable
                         : SensorState::kDisabled;
  data.aht2xState = AquaSenseConfig::ENABLE_AHT2X
                        ? SensorState::kUnavailable
                        : SensorState::kDisabled;
  const uint32_t nowMs = millis();
  lastEnsInitMs = nowMs;
  lastAhtInitMs = nowMs;
  ens160Initialized = tryBeginEns160(data);
  aht2xInitialized = tryBeginAht2x(data);
  lastEnsReadMs = nowMs - AquaSenseConfig::ENS160_INTERVAL_MS;
  lastAhtReadMs = nowMs - AquaSenseConfig::AHT2X_INTERVAL_MS;
}

void environmentSensorsUpdate(const uint32_t nowMs, SensorData& data) {
  if (AquaSenseConfig::ENABLE_AHT2X && !aht2xInitialized &&
      nowMs - lastAhtInitMs >= AquaSenseConfig::I2C_RETRY_MS) {
    lastAhtInitMs = nowMs;
    aht2xInitialized = tryBeginAht2x(data);
  }
#if AQUASENSE_ENABLE_AHT2X_LIBRARY
  if (aht2xInitialized &&
      nowMs - lastAhtReadMs >= AquaSenseConfig::AHT2X_INTERVAL_MS) {
    lastAhtReadMs = nowMs;
    sensors_event_t humidity{};
    sensors_event_t temperature{};
    const bool readOk = aht2x.getEvent(&humidity, &temperature);
    const float temp = temperature.temperature;
    const float rh = humidity.relative_humidity;
    if (readOk && isfinite(temp) && isfinite(rh) && temp >= -40.0F &&
        temp <= 85.0F && rh >= 0.0F && rh <= 100.0F) {
      data.ambientTemperatureC = temp;
      data.humidityPercent = rh;
      data.aht2xValid = true;
      data.aht2xState = SensorState::kValid;
    } else {
      data.aht2xValid = false;
      data.ambientTemperatureC = NAN;
      data.humidityPercent = NAN;
      data.aht2xState = SensorState::kError;
    }
  }
#endif

  if (AquaSenseConfig::ENABLE_ENS160 && !ens160Initialized &&
      nowMs - lastEnsInitMs >= AquaSenseConfig::I2C_RETRY_MS) {
    lastEnsInitMs = nowMs;
    ens160Initialized = tryBeginEns160(data);
  }
#if AQUASENSE_ENABLE_ENS160_LIBRARY
  if (!ens160Initialized) return;
  const bool intervalReached =
      nowMs - lastEnsReadMs >= AquaSenseConfig::ENS160_INTERVAL_MS;
  if (!intervalReached &&
      (!AquaSenseConfig::ENS160_USE_INTERRUPT || !ens160DataReady)) return;
  ens160DataReady = false;
  lastEnsReadMs = nowMs;
  if (data.aht2xValid) {
    ens160.setTempAndHum(data.ambientTemperatureC, data.humidityPercent);
  }
  const uint8_t status = ens160.getENS160Status();
  if (status == ens160.eWarmUpPhase || status == ens160.eInitialStartUpPhase) {
    data.ens160Valid = false;
    data.ens160State = SensorState::kWarmingUp;
    return;
  }
  if (status != ens160.eNormalOperation) {
    data.ens160Valid = false;
    data.ens160State = SensorState::kError;
    return;
  }
  const uint8_t aqi = ens160.getAQI();
  const uint16_t tvoc = ens160.getTVOC();
  const uint16_t eco2 = ens160.getECO2();
  if (aqi < 1U || aqi > 5U || eco2 < 400U) {
    data.ens160Valid = false;
    data.ens160State = SensorState::kError;
    return;
  }
  data.ens160Aqi = aqi;
  data.ens160TvocPpb = tvoc;
  data.ens160Eco2Ppm = eco2;
  data.ens160Valid = true;
  data.ens160State = SensorState::kValid;
#endif
}

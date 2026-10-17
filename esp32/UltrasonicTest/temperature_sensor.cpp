#include "temperature_sensor.h"
#include "calibration.h"
#include "config.h"
#include "pins.h"

#if AQUASENSE_ENABLE_DS18B20_LIBRARIES
#include <DallasTemperature.h>
#include <OneWire.h>
namespace {
OneWire oneWire(AquaSensePins::DS18B20_DATA);
DallasTemperature ds18b20(&oneWire);
bool conversionPending = false;
uint32_t conversionStartedMs = 0;
uint32_t lastActionMs = 0;

bool devicePresent() {
  return ds18b20.getDeviceCount() > 0U;
}
}
#endif

void temperatureSensorBegin(SensorData& data) {
  data.wastewaterTemperatureValid = false;
  data.wastewaterTemperatureC = NAN;
  data.ds18b20State = AquaSenseConfig::ENABLE_DS18B20
                          ? SensorState::kUnavailable
                          : SensorState::kDisabled;
#if AQUASENSE_ENABLE_DS18B20_LIBRARIES
  if (!AquaSenseConfig::ENABLE_DS18B20) return;
  ds18b20.begin();
  ds18b20.setWaitForConversion(false);
  lastActionMs = millis();
  if (!devicePresent()) return;
  ds18b20.requestTemperatures();
  conversionPending = true;
  conversionStartedMs = lastActionMs;
#endif
}

void temperatureSensorUpdate(const uint32_t nowMs, SensorData& data) {
#if AQUASENSE_ENABLE_DS18B20_LIBRARIES
  if (!AquaSenseConfig::ENABLE_DS18B20) return;
  if (!devicePresent()) {
    data.wastewaterTemperatureValid = false;
    data.wastewaterTemperatureC = NAN;
    data.ds18b20State = SensorState::kUnavailable;
    conversionPending = false;
    if (nowMs - lastActionMs >= AquaSenseConfig::TEMPERATURE_RETRY_MS) {
      ds18b20.begin();
      lastActionMs = nowMs;
    }
    return;
  }
  if (conversionPending &&
      nowMs - conversionStartedMs >= AquaSenseConfig::TEMPERATURE_CONVERSION_MS) {
    const float value = ds18b20.getTempCByIndex(0);
    conversionPending = false;
    if (isfinite(value) && value != DEVICE_DISCONNECTED_C && value != 85.0F &&
        value >= -55.0F && value <= 125.0F) {
      data.wastewaterTemperatureC =
          value + AquaSenseCalibration::DS18B20_TEMPERATURE_OFFSET_C;
      data.wastewaterTemperatureValid = true;
      data.ds18b20State = SensorState::kValid;
    } else {
      data.wastewaterTemperatureC = NAN;
      data.wastewaterTemperatureValid = false;
      data.ds18b20State = SensorState::kError;
    }
  }
  if (!conversionPending &&
      nowMs - lastActionMs >= AquaSenseConfig::TEMPERATURE_INTERVAL_MS) {
    ds18b20.requestTemperatures();
    conversionPending = true;
    conversionStartedMs = nowMs;
    lastActionMs = nowMs;
  }
#else
  (void)nowMs;
  (void)data;
#endif
}

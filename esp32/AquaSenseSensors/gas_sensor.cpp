#include "gas_sensor.h"
#include "config.h"
#include "pins.h"

namespace {
uint32_t warmupStartedMs = 0;
uint32_t lastReadMs = 0;

uint16_t averagedAdc(uint16_t& latest) {
  uint32_t sum = 0;
  for (uint8_t sample = 0; sample < AquaSenseConfig::ANALOG_SAMPLE_COUNT;
       ++sample) {
    latest = static_cast<uint16_t>(analogRead(AquaSensePins::MQ135_ANALOG));
    sum += latest;
  }
  return static_cast<uint16_t>(sum / AquaSenseConfig::ANALOG_SAMPLE_COUNT);
}
}

void gasSensorBegin(SensorData& data) {
  data.mq135RawValid = false;
  data.mq135State = SensorState::kDisabled;
  if (!AquaSenseConfig::ENABLE_MQ135) return;
  if (!AquaSenseConfig::MQ135_INPUT_VOLTAGE_VERIFIED) {
    data.mq135State = SensorState::kError;
    return;
  }
  analogReadResolution(12);
  analogSetPinAttenuation(AquaSensePins::MQ135_ANALOG, ADC_11db);
  pinMode(AquaSensePins::MQ135_ANALOG, INPUT);
  warmupStartedMs = millis();
  lastReadMs = warmupStartedMs;
  data.mq135State = SensorState::kWarmingUp;
}

void gasSensorUpdate(const uint32_t nowMs, SensorData& data) {
  if (!AquaSenseConfig::ENABLE_MQ135 ||
      !AquaSenseConfig::MQ135_INPUT_VOLTAGE_VERIFIED) return;
  if (nowMs - warmupStartedMs < AquaSenseConfig::MQ135_WARMUP_MS) {
    data.mq135RawValid = false;
    data.mq135State = SensorState::kWarmingUp;
    return;
  }
  if (nowMs - lastReadMs < AquaSenseConfig::MQ135_INTERVAL_MS) return;
  lastReadMs = nowMs;
  data.mq135RawAdc = averagedAdc(data.mq135LatestAdc);
  if (data.mq135RawAdc <= AquaSenseConfig::ANALOG_RAIL_FAULT_MARGIN ||
      data.mq135RawAdc >= AquaSenseConfig::ADC_MAX_COUNT -
                               AquaSenseConfig::ANALOG_RAIL_FAULT_MARGIN) {
    data.mq135RawValid = false;
    data.mq135AdcVoltage = NAN;
    data.mq135SourceVoltage = NAN;
    data.mq135State = SensorState::kError;
    return;
  }
  data.mq135AdcVoltage = static_cast<float>(data.mq135RawAdc) *
                         AquaSenseConfig::ADC_REFERENCE_V /
                         static_cast<float>(AquaSenseConfig::ADC_MAX_COUNT);
  data.mq135SourceVoltage = data.mq135AdcVoltage *
                           AquaSenseConfig::ANALOG_SOURCE_MULTIPLIER;
  data.mq135RawValid = true;
  data.mq135State = SensorState::kNotCalibrated;
}

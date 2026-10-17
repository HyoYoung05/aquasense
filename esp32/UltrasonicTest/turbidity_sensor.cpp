#include "turbidity_sensor.h"
#include "calibration.h"
#include "config.h"
#include "pins.h"

namespace {
uint32_t lastReadMs = 0;

uint16_t averagedAdc(const uint8_t pin, uint16_t& latest) {
  uint32_t sum = 0;
  for (uint8_t sample = 0; sample < AquaSenseConfig::ANALOG_SAMPLE_COUNT;
       ++sample) {
    latest = static_cast<uint16_t>(analogRead(pin));
    sum += latest;
  }
  return static_cast<uint16_t>(sum / AquaSenseConfig::ANALOG_SAMPLE_COUNT);
}

float pinVoltage(const uint16_t raw) {
  return static_cast<float>(raw) * AquaSenseConfig::ADC_REFERENCE_V /
         static_cast<float>(AquaSenseConfig::ADC_MAX_COUNT);
}
}

void turbiditySensorBegin(SensorData& data) {
  data.turbidityRawValid = false;
  data.turbidityNtuValid = false;
  data.turbidityState = SensorState::kDisabled;
  if (!AquaSenseConfig::ENABLE_TURBIDITY) return;
  if (!AquaSenseConfig::TURBIDITY_INPUT_VOLTAGE_VERIFIED) {
    data.turbidityState = SensorState::kError;
    return;
  }
  pinMode(AquaSensePins::TURBIDITY_ANALOG, INPUT);
  data.turbidityState = SensorState::kNotCalibrated;
  lastReadMs = millis() - AquaSenseConfig::TURBIDITY_INTERVAL_MS;
}

void turbiditySensorUpdate(const uint32_t nowMs, SensorData& data) {
  if (!AquaSenseConfig::ENABLE_TURBIDITY ||
      !AquaSenseConfig::TURBIDITY_INPUT_VOLTAGE_VERIFIED ||
      nowMs - lastReadMs < AquaSenseConfig::TURBIDITY_INTERVAL_MS) return;
  lastReadMs = nowMs;
  data.turbidityRawAdc = averagedAdc(AquaSensePins::TURBIDITY_ANALOG,
                                     data.turbidityLatestAdc);
  data.turbidityAdcVoltage = pinVoltage(data.turbidityRawAdc);
  data.turbiditySourceVoltage = data.turbidityAdcVoltage *
                               AquaSenseConfig::ANALOG_SOURCE_MULTIPLIER;
  data.turbidityRawValid = true;
  const bool coefficientsValid =
      isfinite(AquaSenseCalibration::TURBIDITY_NTU_COEFFICIENT_A) &&
      isfinite(AquaSenseCalibration::TURBIDITY_NTU_COEFFICIENT_B) &&
      isfinite(AquaSenseCalibration::TURBIDITY_NTU_COEFFICIENT_C);
  if (AquaSenseCalibration::TURBIDITY_NTU_CALIBRATED &&
      coefficientsValid) {
    const float voltage = data.turbiditySourceVoltage;
    data.turbidityNtu = max(
        0.0F,
        AquaSenseCalibration::TURBIDITY_NTU_COEFFICIENT_A * voltage * voltage +
            AquaSenseCalibration::TURBIDITY_NTU_COEFFICIENT_B * voltage +
            AquaSenseCalibration::TURBIDITY_NTU_COEFFICIENT_C);
    data.turbidityNtuValid = isfinite(data.turbidityNtu);
    data.turbidityState = data.turbidityNtuValid
                              ? SensorState::kValid
                              : SensorState::kError;
  } else {
    data.turbidityNtuValid = false;
    data.turbidityNtu = NAN;
    data.turbidityState = SensorState::kNotCalibrated;
  }
}

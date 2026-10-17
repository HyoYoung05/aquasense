#include "flow_sensor.h"
#include "calibration.h"
#include "config.h"
#include "pins.h"

namespace {
volatile uint32_t pulseCounter = 0;
uint32_t lastCalculationMs = 0;
float cumulativeLiters = 0.0F;
void IRAM_ATTR countFlowPulse() { pulseCounter = pulseCounter + 1U; }
}

void flowSensorBegin(SensorData& data) {
  data.flowValid = false;
  data.flowPulseValid = false;
  data.flowState = SensorState::kDisabled;
  if (!AquaSenseConfig::ENABLE_FLOW) return;
  if (!AquaSenseConfig::FLOW_SIGNAL_VOLTAGE_VERIFIED) {
    data.flowState = SensorState::kError;
    return;
  }
  pinMode(AquaSensePins::FLOW_SIGNAL, INPUT);
  attachInterrupt(digitalPinToInterrupt(AquaSensePins::FLOW_SIGNAL),
                  countFlowPulse, RISING);
  data.flowState = AquaSenseCalibration::FLOW_PHYSICALLY_CALIBRATED
                       ? SensorState::kValid
                       : SensorState::kNotCalibrated;
  data.cumulativeFlowLiters = AquaSenseCalibration::FLOW_PHYSICALLY_CALIBRATED
                                  ? 0.0F
                                  : NAN;
  lastCalculationMs = millis();
}

void flowSensorUpdate(const uint32_t nowMs, SensorData& data) {
  if (!AquaSenseConfig::ENABLE_FLOW ||
      !AquaSenseConfig::FLOW_SIGNAL_VOLTAGE_VERIFIED ||
      nowMs - lastCalculationMs < AquaSenseConfig::FLOW_INTERVAL_MS) return;
  const uint32_t elapsedMs = nowMs - lastCalculationMs;
  lastCalculationMs = nowMs;
  noInterrupts();
  const uint32_t pulses = pulseCounter;
  pulseCounter = 0;
  interrupts();
  data.flowPulseCount = pulses;
  data.flowPulseValid = true;
  data.flowPulseFrequencyHz = static_cast<float>(pulses) * 1000.0F /
                              static_cast<float>(elapsedMs);
  const bool calibrationValid =
      AquaSenseCalibration::FLOW_PHYSICALLY_CALIBRATED &&
      isfinite(AquaSenseCalibration::FLOW_PULSES_PER_LITER) &&
      AquaSenseCalibration::FLOW_PULSES_PER_LITER > 0.0F;
  if (!calibrationValid) {
    data.flowRateLpm = NAN;
    data.cumulativeFlowLiters = NAN;
    data.flowValid = false;
    data.flowState = SensorState::kNotCalibrated;
    return;
  }
  const float liters = static_cast<float>(pulses) /
                       AquaSenseCalibration::FLOW_PULSES_PER_LITER;
  data.flowRateLpm = liters * 60000.0F / static_cast<float>(elapsedMs);
  cumulativeLiters += liters;
  data.cumulativeFlowLiters = cumulativeLiters;
  data.flowValid = isfinite(data.flowRateLpm);
  data.flowState = data.flowValid ? SensorState::kValid : SensorState::kError;
}

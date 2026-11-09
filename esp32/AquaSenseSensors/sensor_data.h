#pragma once

#include <Arduino.h>

enum class SensorState : uint8_t {
  kDisabled,
  kUnavailable,
  kWarmingUp,
  kNotCalibrated,
  kValid,
  kError,
};

struct SensorData {
  bool wastewaterTemperatureValid = false;
  float wastewaterTemperatureC = NAN;
  SensorState ds18b20State = SensorState::kDisabled;

  bool turbidityRawValid = false;
  uint16_t turbidityLatestAdc = 0;
  uint16_t turbidityRawAdc = 0;
  float turbidityAdcVoltage = NAN;
  float turbiditySourceVoltage = NAN;
  bool turbidityNtuValid = false;
  float turbidityNtu = NAN;
  SensorState turbidityState = SensorState::kDisabled;

  bool flowPulseValid = false;
  bool flowValid = false;
  uint32_t flowPulseCount = 0;
  float flowPulseFrequencyHz = NAN;
  float flowRateLpm = NAN;
  float cumulativeFlowLiters = NAN;
  SensorState flowState = SensorState::kDisabled;

  bool mq135RawValid = false;
  uint16_t mq135LatestAdc = 0;
  uint16_t mq135RawAdc = 0;
  float mq135AdcVoltage = NAN;
  float mq135SourceVoltage = NAN;
  SensorState mq135State = SensorState::kDisabled;
};

const char* sensorStateName(SensorState state);
void printSensorData(const SensorData& data);

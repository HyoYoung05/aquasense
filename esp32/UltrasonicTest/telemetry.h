#pragma once

#include <Arduino.h>
#include "sensor_data.h"

struct PendingTelemetry {
  bool active = false;
  uint32_t queueSequence = 0;
  uint32_t capturedUptimeMs = 0;
  char readingUuid[37]{};
  float ultrasonicDistanceCm = NAN;
  bool hasWastewaterTemperature = false;
  float wastewaterTemperatureC = NAN;
  bool hasTurbidityRaw = false;
  uint16_t turbidityRawAdc = 0;
  bool hasTurbidityNtu = false;
  float turbidityNtu = NAN;
  bool hasFlowPulseCount = false;
  uint32_t flowPulseCount = 0;
  bool hasFlowRate = false;
  float flowRateLpm = NAN;
  bool hasEns160 = false;
  uint8_t ens160Aqi = 0;
  uint16_t ens160TvocPpb = 0;
  uint16_t ens160Eco2Ppm = 0;
  bool hasAht2x = false;
  float ambientTemperatureC = NAN;
  float humidityPercent = NAN;
  bool hasGasRaw = false;
  uint16_t gasRawAdc = 0;
};

enum class TelemetryOutcome : uint8_t {
  kAccepted,
  kOffline,
  kNetworkFailure,
  kTimeout,
  kHttp4xx,
  kHttp5xx,
  kOtherHttpFailure,
  kLocalConfigurationFailure,
  kRateLimited,
};

struct TelemetryResult {
  TelemetryOutcome outcome = TelemetryOutcome::kNetworkFailure;
  int httpStatus = 0;
  uint32_t retryAfterMs = 0;
};

void createPendingTelemetry(float distanceCm, const SensorData& sensors,
                            uint32_t queueSequence, uint32_t capturedUptimeMs,
                            PendingTelemetry& pending);
TelemetryResult sendPendingTelemetry(const PendingTelemetry& pending);
const char* telemetryOutcomeName(TelemetryOutcome outcome);

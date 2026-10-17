#include "telemetry.h"

#include <HTTPClient.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>
#include <esp_system.h>
#include <time.h>

#include "config.h"
#include "private_config.h"
#include "wifi_manager.h"

namespace {

void generateUuidV4(char output[37]) {
  uint8_t bytes[16];
  for (uint8_t index = 0; index < sizeof(bytes); index += 4U) {
    const uint32_t randomValue = esp_random();
    memcpy(bytes + index, &randomValue, 4U);
  }
  bytes[6] = static_cast<uint8_t>((bytes[6] & 0x0FU) | 0x40U);
  bytes[8] = static_cast<uint8_t>((bytes[8] & 0x3FU) | 0x80U);
  snprintf(output, 37,
           "%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-"
           "%02x%02x%02x%02x%02x%02x",
           bytes[0], bytes[1], bytes[2], bytes[3], bytes[4], bytes[5],
           bytes[6], bytes[7], bytes[8], bytes[9], bytes[10], bytes[11],
           bytes[12], bytes[13], bytes[14], bytes[15]);
}

bool secureClockReady() {
  return time(nullptr) >= 1700000000;
}

TelemetryResult classifyResponse(const int status, HTTPClient& http) {
  if (status == 200 || status == 201) {
    return {TelemetryOutcome::kAccepted, status, 0};
  }
  if (status == 429) {
    uint32_t delayMs = 0;
    const String retryAfter = http.header("Retry-After");
    if (retryAfter.length() > 0) {
      const long seconds = retryAfter.toInt();
      if (seconds > 0) delayMs = static_cast<uint32_t>(seconds) * 1000U;
    }
    return {TelemetryOutcome::kRateLimited, status, delayMs};
  }
  if (status == 408) {
    return {TelemetryOutcome::kTimeout, status, 0};
  }
  if (status >= 400 && status <= 499) {
    return {TelemetryOutcome::kHttp4xx, status,
            AquaSenseConfig::CONFIGURATION_RETRY_MS};
  }
  if (status >= 500 && status <= 599) {
    return {TelemetryOutcome::kHttp5xx, status, 0};
  }
  return {TelemetryOutcome::kOtherHttpFailure, status, 0};
}

TelemetryResult performRequest(HTTPClient& http, char* json) {
  http.setConnectTimeout(AquaSenseConfig::HTTP_TIMEOUT_MS);
  http.setTimeout(AquaSenseConfig::HTTP_TIMEOUT_MS);
  http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
  const char* responseHeaders[] = {"Retry-After"};
  http.collectHeaders(responseHeaders, 1U);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Device-Key", AquaSenseSecrets::DEVICE_API_KEY);
  const int status = http.POST(reinterpret_cast<uint8_t*>(json),
                               strlen(json));
  if (status <= 0) {
    Serial.print(F("[API] Network/timeout error: "));
    Serial.println(HTTPClient::errorToString(status));
    const bool timeout = status == HTTPC_ERROR_READ_TIMEOUT;
    return {timeout ? TelemetryOutcome::kTimeout
                    : TelemetryOutcome::kNetworkFailure,
            status, 0};
  }
  String response = http.getString();
  if (response.length() > AquaSenseConfig::MAX_RESPONSE_LOG_CHARS) {
    response.remove(AquaSenseConfig::MAX_RESPONSE_LOG_CHARS);
  }
  Serial.printf("[API] HTTP %d | response: %s\n", status, response.c_str());
  return classifyResponse(status, http);
}

}  // namespace

void createPendingTelemetry(const float distanceCm, const SensorData& sensors,
                            const uint32_t queueSequence,
                            const uint32_t capturedUptimeMs,
                            PendingTelemetry& pending) {
  pending.active = true;
  pending.queueSequence = queueSequence;
  pending.capturedUptimeMs = capturedUptimeMs;
  pending.ultrasonicDistanceCm = distanceCm;
  pending.hasWastewaterTemperature = sensors.wastewaterTemperatureValid &&
                                     isfinite(sensors.wastewaterTemperatureC);
  pending.wastewaterTemperatureC = sensors.wastewaterTemperatureC;
  pending.hasTurbidityRaw = sensors.turbidityRawValid;
  pending.turbidityRawAdc = sensors.turbidityRawAdc;
  pending.hasTurbidityNtu = sensors.turbidityNtuValid &&
                            isfinite(sensors.turbidityNtu);
  pending.turbidityNtu = sensors.turbidityNtu;
  pending.hasFlowPulseCount = sensors.flowPulseValid;
  pending.flowPulseCount = sensors.flowPulseCount;
  pending.hasFlowRate = sensors.flowValid && isfinite(sensors.flowRateLpm);
  pending.flowRateLpm = sensors.flowRateLpm;
  pending.hasEns160 = sensors.ens160Valid;
  pending.ens160Aqi = sensors.ens160Aqi;
  pending.ens160TvocPpb = sensors.ens160TvocPpb;
  pending.ens160Eco2Ppm = sensors.ens160Eco2Ppm;
  pending.hasAht2x = sensors.aht2xValid &&
                     isfinite(sensors.ambientTemperatureC) &&
                     isfinite(sensors.humidityPercent);
  pending.ambientTemperatureC = sensors.ambientTemperatureC;
  pending.humidityPercent = sensors.humidityPercent;
  pending.hasGasRaw = sensors.mq135RawValid;
  pending.gasRawAdc = sensors.mq135RawAdc;
  generateUuidV4(pending.readingUuid);
}

TelemetryResult sendPendingTelemetry(const PendingTelemetry& pending) {
  if (!pending.active || !isfinite(pending.ultrasonicDistanceCm)) {
    return {TelemetryOutcome::kLocalConfigurationFailure, 0,
            AquaSenseConfig::CONFIGURATION_RETRY_MS};
  }
  if (!networkConnected()) {
    Serial.println(F("[API] Offline; pending reading retained"));
    return {TelemetryOutcome::kOffline, 0, 0};
  }

  char json[768];
  int jsonLength = snprintf(
      json, sizeof(json),
      "{\"device_id\":\"%s\",\"grease_trap_id\":%lu,"
      "\"ultrasonic_distance\":%.2f",
      AquaSenseConfig::DEVICE_CODE,
      static_cast<unsigned long>(AquaSenseConfig::GREASE_TRAP_ID),
      pending.ultrasonicDistanceCm);
  auto appendFloat = [&](const char* field, const float value) {
    if (jsonLength < 0 || jsonLength >= static_cast<int>(sizeof(json))) return;
    const int added = snprintf(json + jsonLength, sizeof(json) - jsonLength,
                               ",\"%s\":%.3f", field, value);
    if (added < 0 || added >= static_cast<int>(sizeof(json) - jsonLength)) {
      jsonLength = static_cast<int>(sizeof(json));
    } else {
      jsonLength += added;
    }
  };
  auto appendUnsigned = [&](const char* field, const uint32_t value) {
    if (jsonLength < 0 || jsonLength >= static_cast<int>(sizeof(json))) return;
    const int added = snprintf(json + jsonLength, sizeof(json) - jsonLength,
                               ",\"%s\":%lu", field,
                               static_cast<unsigned long>(value));
    if (added < 0 || added >= static_cast<int>(sizeof(json) - jsonLength)) {
      jsonLength = static_cast<int>(sizeof(json));
    } else {
      jsonLength += added;
    }
  };
  if (pending.hasWastewaterTemperature) {
    appendFloat("temperature", pending.wastewaterTemperatureC);
  }
  if (pending.hasTurbidityRaw) {
    appendUnsigned("turbidity_raw", pending.turbidityRawAdc);
  }
  if (pending.hasTurbidityNtu) {
    appendFloat("turbidity", pending.turbidityNtu);
  }
  if (pending.hasFlowPulseCount) {
    appendUnsigned("flow_pulse_count", pending.flowPulseCount);
  }
  if (pending.hasFlowRate) {
    appendFloat("flow_rate", pending.flowRateLpm);
  }
  if (pending.hasEns160) {
    appendUnsigned("aqi", pending.ens160Aqi);
    appendUnsigned("tvoc_ppb", pending.ens160TvocPpb);
    appendUnsigned("eco2_ppm", pending.ens160Eco2Ppm);
  }
  if (pending.hasAht2x) {
    appendFloat("ambient_temperature", pending.ambientTemperatureC);
    appendFloat("humidity", pending.humidityPercent);
  }
  if (pending.hasGasRaw) appendUnsigned("gas_raw", pending.gasRawAdc);
  if (jsonLength >= 0 && jsonLength < static_cast<int>(sizeof(json))) {
    const int added = snprintf(json + jsonLength, sizeof(json) - jsonLength,
                               ",\"reading_uuid\":\"%s\"}",
                               pending.readingUuid);
    if (added < 0 || added >= static_cast<int>(sizeof(json) - jsonLength)) {
      jsonLength = static_cast<int>(sizeof(json));
    } else {
      jsonLength += added;
    }
  }
  if (jsonLength < 0 || jsonLength >= static_cast<int>(sizeof(json))) {
    Serial.println(F("[API] JSON buffer error"));
    return {TelemetryOutcome::kLocalConfigurationFailure, 0,
            AquaSenseConfig::CONFIGURATION_RETRY_MS};
  }
  Serial.printf("[API] POST %s | queue sequence=%lu | distance=%.2f cm | uuid=%s\n",
                AquaSenseConfig::DEVICE_CODE,
                static_cast<unsigned long>(pending.queueSequence),
                pending.ultrasonicDistanceCm, pending.readingUuid);

  const String url(AquaSenseSecrets::API_URL);
  TelemetryResult result{};
  if (url.startsWith("https://")) {
    if (strlen(AquaSenseSecrets::TLS_ROOT_CA) == 0 || !secureClockReady()) {
      Serial.println(F("[API] HTTPS trust/clock is not ready"));
      return {TelemetryOutcome::kLocalConfigurationFailure, 0,
              AquaSenseConfig::CONFIGURATION_RETRY_MS};
    }
    WiFiClientSecure client;
    client.setCACert(AquaSenseSecrets::TLS_ROOT_CA);
    client.setHandshakeTimeout(AquaSenseConfig::HTTP_TIMEOUT_MS / 1000U);
    HTTPClient http;
    if (!http.begin(client, AquaSenseSecrets::API_URL)) {
      return {TelemetryOutcome::kNetworkFailure, 0, 0};
    }
    result = performRequest(http, json);
    http.end();
  } else {
    WiFiClient client;
    HTTPClient http;
    if (!http.begin(client, AquaSenseSecrets::API_URL)) {
      return {TelemetryOutcome::kNetworkFailure, 0, 0};
    }
    result = performRequest(http, json);
    http.end();
  }
  return result;
}

const char* telemetryOutcomeName(const TelemetryOutcome outcome) {
  switch (outcome) {
    case TelemetryOutcome::kAccepted: return "SUCCESS";
    case TelemetryOutcome::kOffline: return "NETWORK OFFLINE";
    case TelemetryOutcome::kNetworkFailure: return "NETWORK FAILURE";
    case TelemetryOutcome::kTimeout: return "TIMEOUT";
    case TelemetryOutcome::kHttp4xx: return "HTTP 4XX";
    case TelemetryOutcome::kHttp5xx: return "HTTP 5XX";
    case TelemetryOutcome::kOtherHttpFailure: return "HTTP OTHER";
    case TelemetryOutcome::kLocalConfigurationFailure:
      return "LOCAL CONFIGURATION";
    case TelemetryOutcome::kRateLimited: return "HTTP 429";
  }
  return "UNKNOWN";
}

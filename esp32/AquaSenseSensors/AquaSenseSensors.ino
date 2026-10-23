#include <Arduino.h>
#include <ctype.h>
#include <esp_system.h>

#include "config.h"
#include "calibration.h"
#include "environment_sensor.h"
#include "flow_sensor.h"
#include "gas_sensor.h"
#include "i2c_bus.h"
#include "pins.h"
#include "private_config.h"
#include "sensor_data.h"
#include "telemetry.h"
#include "telemetry_queue.h"
#include "temperature_sensor.h"
#include "turbidity_sensor.h"
#include "ultrasonic.h"
#include "wifi_manager.h"

namespace {

uint32_t lastStatusMs = 0;
uint32_t lastHealthMs = 0;
uint32_t lastSensorStartMs = 0;
uint32_t sensorSequence = 0;
uint32_t reportSequence = 0;
uint32_t telemetryQueueSequence = 0;
uint32_t nextTelemetryCaptureMs = 0;
uint32_t nextUploadAttemptMs = 0;
uint8_t retryBackoffIndex = 0;
bool networkConfigurationValid = false;
UltrasonicReading latestReading{};
TelemetryQueue telemetryQueue{};
SensorData sensorData{};
uint32_t telemetryAcceptedCount = 0;
uint32_t telemetryFailureCount = 0;
uint32_t telemetryPermanentDiscardCount = 0;
uint32_t networkFailureCount = 0;
uint32_t timeoutCount = 0;
uint32_t http4xxCount = 0;
uint32_t http5xxCount = 0;
uint32_t sensorErrorCount = 0;
uint32_t ultrasonicTimeoutCount = 0;
uint32_t minimumFreeHeap = UINT32_MAX;
SensorState previousDs18b20State = SensorState::kDisabled;
SensorState previousTurbidityState = SensorState::kDisabled;
SensorState previousFlowState = SensorState::kDisabled;
SensorState previousEns160State = SensorState::kDisabled;
SensorState previousAht2xState = SensorState::kDisabled;
SensorState previousMq135State = SensorState::kDisabled;

bool timeReached(const uint32_t nowMs, const uint32_t targetMs) {
  return static_cast<int32_t>(nowMs - targetMs) >= 0;
}

bool deviceCredentialIsValid() {
  if (strlen(AquaSenseSecrets::DEVICE_API_KEY) != 64U) return false;
  for (const char* character = AquaSenseSecrets::DEVICE_API_KEY;
       *character; ++character) {
    if (!isxdigit(static_cast<unsigned char>(*character))) return false;
  }
  return true;
}

bool networkSettingsAreValid() {
  const String url(AquaSenseSecrets::API_URL);
  const bool supportedScheme = url.startsWith("http://") ||
                               url.startsWith("https://");
  const bool forbiddenLoopback = url.indexOf("localhost") >= 0 ||
                                 url.indexOf("127.0.0.1") >= 0;
  return strlen(AquaSenseSecrets::WIFI_SSID_1) > 0U &&
         String(AquaSenseSecrets::WIFI_SSID_1) != "YOUR_WIFI_NAME" &&
         supportedScheme && !forbiddenLoopback &&
         url.indexOf("YOUR_") < 0 && deviceCredentialIsValid() &&
         AquaSenseConfig::GREASE_TRAP_ID > 0U;
}

uint32_t nextRetryDelay(const TelemetryResult& result) {
  if (result.retryAfterMs > 0U) return result.retryAfterMs;
  const size_t index = min(static_cast<size_t>(retryBackoffIndex),
                           AquaSenseConfig::RETRY_BACKOFF_COUNT - 1U);
  const uint32_t delayMs = AquaSenseConfig::RETRY_BACKOFF_MS[index];
  if (retryBackoffIndex + 1U < AquaSenseConfig::RETRY_BACKOFF_COUNT) {
    ++retryBackoffIndex;
  }
  return delayMs;
}

void handleSerialDiagnostics() {
  while (Serial.available() > 0) {
    const char command = static_cast<char>(Serial.read());
    if ((command == 'w' || command == 'W') && networkConfigurationValid) {
      networkForceDisconnectForTest();
    }
  }
}

void captureTelemetry(const uint32_t nowMs) {
  if (!networkConfigurationValid ||
      !timeReached(nowMs, nextTelemetryCaptureMs)) return;
  nextTelemetryCaptureMs = nowMs + AquaSenseConfig::TELEMETRY_INTERVAL_MS;
  if (latestReading.status != UltrasonicStatus::kOk ||
      !isfinite(latestReading.medianDistanceCm)) {
    Serial.println(F("[QUEUE] Not enqueued: ultrasonic reading is invalid"));
    Serial.println(F("[QUEUE] No fake zero, fill, or safe state was stored"));
    return;
  }
  PendingTelemetry pending{};
  createPendingTelemetry(latestReading.medianDistanceCm, sensorData,
                         ++telemetryQueueSequence, nowMs, pending);
  const bool droppedOldest = telemetryQueue.enqueue(pending);
  if (droppedOldest) {
    Serial.println(F("[QUEUE] Full: dropped oldest unsent record to preserve recent data"));
  }
  Serial.printf("[QUEUE] Enqueued sequence=%lu | depth=%u/%u | uuid=%s\n",
                static_cast<unsigned long>(pending.queueSequence),
                static_cast<unsigned int>(telemetryQueue.size()),
                static_cast<unsigned int>(telemetryQueue.capacity()),
                pending.readingUuid);
  if (telemetryQueue.size() == 1U) nextUploadAttemptMs = nowMs;
}

void handleTelemetryUpload(const uint32_t nowMs) {
  if (!networkConfigurationValid || telemetryQueue.size() == 0U ||
      !timeReached(nowMs, nextUploadAttemptMs)) return;
  PendingTelemetry* pending = telemetryQueue.front();
  if (pending == nullptr) return;
  const TelemetryResult result = sendPendingTelemetry(*pending);
  Serial.printf("[API] Outcome: %s | HTTP/result=%d\n",
                telemetryOutcomeName(result.outcome), result.httpStatus);
  if (result.outcome == TelemetryOutcome::kAccepted) {
    ++telemetryAcceptedCount;
    telemetryQueue.pop();
    retryBackoffIndex = 0;
    nextUploadAttemptMs = millis() +
                          AquaSenseConfig::QUEUE_DRAIN_INTERVAL_MS;
    Serial.printf("[QUEUE] Accepted oldest record | remaining=%u\n",
                  static_cast<unsigned int>(telemetryQueue.size()));
    return;
  }

  ++telemetryFailureCount;
  switch (result.outcome) {
    case TelemetryOutcome::kNetworkFailure: ++networkFailureCount; break;
    case TelemetryOutcome::kTimeout: ++timeoutCount; break;
    case TelemetryOutcome::kHttp4xx:
      ++http4xxCount;
      ++telemetryPermanentDiscardCount;
      telemetryQueue.pop();
      Serial.println(F("[QUEUE] Permanent 4xx: rejected record discarded; no tight retry"));
      break;
    case TelemetryOutcome::kHttp5xx: ++http5xxCount; break;
    default: break;
  }
  const uint32_t delayMs = nextRetryDelay(result);
  nextUploadAttemptMs = millis() + delayMs;
  Serial.printf("[API] Next upload attempt in %lu ms; sensing continues\n",
                static_cast<unsigned long>(delayMs));
}

void updateSensorFailureCounters(const UltrasonicReading& reading) {
  if (reading.status != UltrasonicStatus::kOk) {
    ++sensorErrorCount;
    if (reading.status == UltrasonicStatus::kNoEcho) ++ultrasonicTimeoutCount;
  }
}

void trackSensorFaultTransition(const bool enabled, const SensorState current,
                                SensorState& previous) {
  if (enabled && current != previous &&
      (current == SensorState::kError ||
       current == SensorState::kUnavailable)) {
    ++sensorErrorCount;
  }
  previous = current;
}

void updateOptionalSensorFailureCounters() {
  trackSensorFaultTransition(AquaSenseConfig::ENABLE_DS18B20,
                             sensorData.ds18b20State,
                             previousDs18b20State);
  trackSensorFaultTransition(AquaSenseConfig::ENABLE_TURBIDITY,
                             sensorData.turbidityState,
                             previousTurbidityState);
  trackSensorFaultTransition(AquaSenseConfig::ENABLE_FLOW,
                             sensorData.flowState, previousFlowState);
  trackSensorFaultTransition(AquaSenseConfig::ENABLE_ENS160,
                             sensorData.ens160State, previousEns160State);
  trackSensorFaultTransition(AquaSenseConfig::ENABLE_AHT2X,
                             sensorData.aht2xState, previousAht2xState);
  trackSensorFaultTransition(AquaSenseConfig::ENABLE_MQ135,
                             sensorData.mq135State, previousMq135State);
}

const char* resetReasonName(const esp_reset_reason_t reason) {
  switch (reason) {
    case ESP_RST_POWERON: return "POWER_ON";
    case ESP_RST_EXT: return "EXTERNAL_RESET";
    case ESP_RST_SW: return "SOFTWARE_RESET";
    case ESP_RST_PANIC: return "PANIC";
    case ESP_RST_INT_WDT: return "INTERRUPT_WATCHDOG";
    case ESP_RST_TASK_WDT: return "TASK_WATCHDOG";
    case ESP_RST_WDT: return "OTHER_WATCHDOG";
    case ESP_RST_DEEPSLEEP: return "DEEP_SLEEP";
    case ESP_RST_BROWNOUT: return "BROWNOUT";
    case ESP_RST_SDIO: return "SDIO";
    case ESP_RST_USB: return "USB";
    case ESP_RST_JTAG: return "JTAG";
    case ESP_RST_EFUSE: return "EFUSE";
    case ESP_RST_PWR_GLITCH: return "POWER_GLITCH";
    case ESP_RST_CPU_LOCKUP: return "CPU_LOCKUP";
    case ESP_RST_UNKNOWN: return "UNKNOWN";
  }
  return "OTHER";
}

void printHardwareSummary() {
  Serial.println();
  Serial.println(F("AQUASENSE+"));
  Serial.println(F("ESP32 Six-Sensor Firmware"));
  Serial.printf("Firmware version: %s\n", AquaSenseConfig::FIRMWARE_VERSION);
  Serial.printf("Chip model: %s\n", ESP.getChipModel());
  Serial.printf("Chip revision: %u\n", ESP.getChipRevision());
  Serial.printf("CPU frequency: %lu MHz\n",
                static_cast<unsigned long>(ESP.getCpuFreqMHz()));
  Serial.printf("Flash size: %lu bytes\n",
                static_cast<unsigned long>(ESP.getFlashChipSize()));
  Serial.printf("Free heap: %lu bytes\n",
                static_cast<unsigned long>(ESP.getFreeHeap()));

  const esp_reset_reason_t resetReason = esp_reset_reason();
  Serial.printf("Reset reason: %s (%d)\n",
                resetReasonName(resetReason), static_cast<int>(resetReason));
  if (resetReason == ESP_RST_BROWNOUT || resetReason == ESP_RST_PWR_GLITCH) {
    Serial.println(F("[POWER] WARNING: power-related reset detected"));
  }
  Serial.printf("Power mode: %s\n", AquaSenseConfig::POWER_MODE);
  Serial.printf("External regulated DC verified: %s\n",
                AquaSenseConfig::EXTERNAL_DC_POWER_VERIFIED ? "YES" : "NO");
  Serial.printf("Rail voltage monitor: %s\n",
                AquaSenseConfig::RAIL_VOLTAGE_MONITOR_PRESENT
                    ? "PRESENT"
                    : "NOT PRESENT - USE A MULTIMETER");
  Serial.println(F("Approved rails: 5V=VIN/HC-SR04/YF-S201/SEN0189/MQ-135"));
  Serial.println(F("Approved rails: 3.3V=DS18B20/ENS160+AHT2x | common ground"));
  Serial.println(F("Backup battery: NOT IMPLEMENTED - schematic not finalized"));
  Serial.printf("HC-SR04 pins: TRIG=GPIO%u | ECHO=GPIO%u\n",
                static_cast<unsigned int>(AquaSensePins::ULTRASONIC_TRIG),
                static_cast<unsigned int>(AquaSensePins::ULTRASONIC_ECHO));
  Serial.printf("Local ultrasonic diagnostic: empty=%.1f cm | full=%.1f cm | calibrated=%s\n",
                AquaSenseCalibration::ULTRASONIC_EMPTY_DISTANCE_CM,
                AquaSenseCalibration::ULTRASONIC_FULL_DISTANCE_CM,
                AquaSenseCalibration::ULTRASONIC_PHYSICALLY_CALIBRATED
                    ? "YES"
                    : "NO - DEVELOPMENT VALUES");
  Serial.println(F("Backend grease-trap calibration and thresholds are authoritative."));
  Serial.printf("Calibration: DS18B20=%s | SEN0189 NTU=%s | YF-S201=%s\n",
                AquaSenseCalibration::DS18B20_REFERENCE_VALIDATED ? "VALIDATED" : "PENDING",
                AquaSenseCalibration::TURBIDITY_NTU_CALIBRATED ? "CALIBRATED" : "INCOMPLETE",
                AquaSenseCalibration::FLOW_PHYSICALLY_CALIBRATED ? "CALIBRATED" : "INCOMPLETE");
  Serial.printf("Calibration: ENS160=%s | AHT2x=%s | MQ-135 ppm=%s\n",
                AquaSenseCalibration::ENS160_RESPONSE_VALIDATED ? "VALIDATED" : "PENDING",
                AquaSenseCalibration::AHT2X_REFERENCE_VALIDATED ? "VALIDATED" : "PENDING",
                AquaSenseCalibration::MQ135_PPM_CALIBRATED ? "CALIBRATED" : "INCOMPLETE");
  Serial.printf("Device identity: %s | grease trap: %lu\n",
                AquaSenseConfig::DEVICE_CODE,
                static_cast<unsigned long>(AquaSenseConfig::GREASE_TRAP_ID));
  Serial.println(F("Data path: ESP32 -> PHP API -> MySQL (never direct MySQL)"));
  Serial.printf("Sensor pins: DS18B20=%u | SEN0189=%u | YF-S201=%u\n",
                AquaSensePins::DS18B20_DATA,
                AquaSensePins::TURBIDITY_ANALOG,
                AquaSensePins::FLOW_SIGNAL);
  Serial.printf("Combo I2C: SDA=%u SCL=%u | optional ENS160 INT=%u | MQ-135=%u\n",
                AquaSensePins::ENS160_SDA, AquaSensePins::ENS160_SCL,
                AquaSensePins::ENS160_INT, AquaSensePins::MQ135_ANALOG);
  Serial.println(F("Unverified sensor circuits remain disabled; unavailable is valid."));
  Serial.printf("RAM telemetry queue: %u records x %u bytes = %u bytes fixed\n",
                static_cast<unsigned int>(telemetryQueue.capacity()),
                static_cast<unsigned int>(sizeof(PendingTelemetry)),
                static_cast<unsigned int>(telemetryQueue.capacity() *
                                          sizeof(PendingTelemetry)));
  Serial.println(F("Queue is volatile: reset/power loss clears unsent records."));
  Serial.println(F("Queue overflow policy: DROP OLDEST; retry order: FIFO."));
  Serial.println(F("Custom watchdog: NOT ADDED; bounded operations and recovery states used."));
}

void printSixSensorLiveReport() {
  Serial.println();
  Serial.println(F("========================================"));
  Serial.printf("AQUASENSE+ SIX-SENSOR LIVE REPORT #%lu\n",
                static_cast<unsigned long>(++reportSequence));
  Serial.println(F("========================================"));
  Serial.println();
  printUltrasonicLiveSection(latestReading);
  printSensorData(sensorData);
  Serial.println(F("========================================"));
}

void printSystemHealth(const uint32_t nowMs) {
  const uint32_t currentHeap = ESP.getFreeHeap();
  minimumFreeHeap = min(minimumFreeHeap, currentHeap);
  Serial.println(F("[SYSTEM HEALTH]"));
  Serial.printf("Uptime: %lu ms | free heap: %lu | minimum heap: %lu\n",
                static_cast<unsigned long>(nowMs),
                static_cast<unsigned long>(currentHeap),
                static_cast<unsigned long>(minimumFreeHeap));
  Serial.printf("Wi-Fi: connects=%lu | reconnects=%lu | disconnects=%lu\n",
                static_cast<unsigned long>(networkConnectionCount()),
                static_cast<unsigned long>(networkReconnectCount()),
                static_cast<unsigned long>(networkDisconnectCount()));
  Serial.printf("Telemetry: success=%lu | failures=%lu | network=%lu | timeout=%lu | 4xx=%lu | 5xx=%lu\n",
                static_cast<unsigned long>(telemetryAcceptedCount),
                static_cast<unsigned long>(telemetryFailureCount),
                static_cast<unsigned long>(networkFailureCount),
                static_cast<unsigned long>(timeoutCount),
                static_cast<unsigned long>(http4xxCount),
                static_cast<unsigned long>(http5xxCount));
  Serial.printf("Queue: depth=%u/%u | high-water=%u | dropped-oldest=%lu | permanent-discards=%lu\n",
                static_cast<unsigned int>(telemetryQueue.size()),
                static_cast<unsigned int>(telemetryQueue.capacity()),
                static_cast<unsigned int>(telemetryQueue.highWaterMark()),
                static_cast<unsigned long>(telemetryQueue.droppedOldestCount()),
                static_cast<unsigned long>(telemetryPermanentDiscardCount));
  Serial.printf("Faults: sensor acquisitions=%lu | ultrasonic timeouts=%lu | I2C errors=%lu | I2C recoveries=%lu\n",
                static_cast<unsigned long>(sensorErrorCount),
                static_cast<unsigned long>(ultrasonicTimeoutCount),
                static_cast<unsigned long>(i2cErrorCount()),
                static_cast<unsigned long>(i2cRecoveryCount()));
  printNetworkStatus();
}

}  // namespace

void setup() {
  Serial.begin(AquaSenseConfig::SERIAL_BAUD);
  delay(AquaSenseConfig::STARTUP_DELAY_MS);
  ultrasonicBegin();
  // Required integration order: 4A, 4B, 4C, 4D, then 4E.
  temperatureSensorBegin(sensorData);
  turbiditySensorBegin(sensorData);
  flowSensorBegin(sensorData);
  i2cBusBegin();
  environmentSensorsBegin(sensorData);
  gasSensorBegin(sensorData);
  printHardwareSummary();
  Serial.printf("Algorithm self-test: %s\n",
                ultrasonicAlgorithmSelfTest() ? "PASS" : "FAIL");
  Serial.printf("RAM queue FIFO/overflow self-test: %s\n",
                telemetryQueueSelfTest() ? "PASS" : "FAIL");
  Serial.println(F("Reference only: 10 cm=80%, 20 cm=40%, 30 cm=0% with temporary calibration."));
  networkConfigurationValid = networkSettingsAreValid();
  if (networkConfigurationValid) {
    Serial.println(F("[CONFIG] Private Wi-Fi, API URL, and device credential loaded"));
    Serial.println(F("[CONFIG] Secrets are not printed"));
    networkBegin();
    Serial.println(F("[TEST] Send W over Serial to test Wi-Fi recovery"));
  } else {
    Serial.println(F("[CONFIG] Private network/API settings are incomplete"));
    Serial.println(F("[CONFIG] Sensing continues; telemetry is disabled"));
  }
  lastStatusMs = millis();
  lastSensorStartMs = millis() - AquaSenseConfig::SENSOR_INTERVAL_MS;
  nextTelemetryCaptureMs = millis() +
                           AquaSenseConfig::INITIAL_TELEMETRY_DELAY_MS;
  nextUploadAttemptMs = nextTelemetryCaptureMs;
  lastHealthMs = lastStatusMs;
}

void loop() {
  const uint32_t nowMs = millis();
  handleSerialDiagnostics();
  if (networkConfigurationValid) networkUpdate(nowMs);
  temperatureSensorUpdate(nowMs, sensorData);
  turbiditySensorUpdate(nowMs, sensorData);
  flowSensorUpdate(nowMs, sensorData);
  environmentSensorsUpdate(nowMs, sensorData);
  gasSensorUpdate(nowMs, sensorData);
  updateOptionalSensorFailureCounters();
  if (nowMs - lastSensorStartMs >= AquaSenseConfig::SENSOR_INTERVAL_MS) {
    lastSensorStartMs = nowMs;
    ++sensorSequence;
    latestReading = readUltrasonic();
    updateSensorFailureCounters(latestReading);
    if (AquaSenseConfig::VERBOSE_ULTRASONIC_SERIAL) {
      printUltrasonicDiagnostics(latestReading, sensorSequence);
    }
  }
  captureTelemetry(millis());
  handleTelemetryUpload(millis());
  if (nowMs - lastStatusMs >= AquaSenseConfig::STATUS_INTERVAL_MS) {
    lastStatusMs = nowMs;
    printSixSensorLiveReport();
  }
  if (nowMs - lastHealthMs >= AquaSenseConfig::HEALTH_INTERVAL_MS) {
    lastHealthMs = nowMs;
    printSystemHealth(nowMs);
  }
  delay(1);
}

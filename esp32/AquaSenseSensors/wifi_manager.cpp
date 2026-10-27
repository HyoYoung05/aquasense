#include "wifi_manager.h"

#include <WiFi.h>
#include <time.h>

#include "config.h"
#include "private_config.h"

namespace {

bool connectionAttemptActive = false;
bool previouslyConnected = false;
uint8_t nextNetworkIndex = 0;
uint8_t activeNetworkSlot = 0;
size_t retryBackoffIndex = 0;
uint32_t attemptStartedMs = 0;
uint32_t nextAttemptMs = 0;
uint32_t startupRotationStartedMs = 0;
uint32_t connectionCount = 0;
uint32_t disconnectCount = 0;
uint32_t attemptCount = 0;

enum class WiFiRecoveryState : uint8_t {
  kDisconnected,
  kConnecting,
  kRetryWait,
  kConnected,
};

WiFiRecoveryState recoveryState = WiFiRecoveryState::kDisconnected;

bool timeReached(const uint32_t nowMs, const uint32_t targetMs) {
  return static_cast<int32_t>(nowMs - targetMs) >= 0;
}

struct WiFiSlot {
  const char* ssid;
  const char* password;
};

const WiFiSlot WIFI_SLOTS[] = {
    {AquaSenseSecrets::WIFI_SSID_1, AquaSenseSecrets::WIFI_PASSWORD_1},
    {AquaSenseSecrets::WIFI_SSID_2, AquaSenseSecrets::WIFI_PASSWORD_2},
    {AquaSenseSecrets::WIFI_SSID_3, AquaSenseSecrets::WIFI_PASSWORD_3},
    {AquaSenseSecrets::WIFI_SSID_4, AquaSenseSecrets::WIFI_PASSWORD_4},
    {AquaSenseSecrets::WIFI_SSID_5, AquaSenseSecrets::WIFI_PASSWORD_5},
    {AquaSenseSecrets::WIFI_SSID_6, AquaSenseSecrets::WIFI_PASSWORD_6},
    {AquaSenseSecrets::WIFI_SSID_7, AquaSenseSecrets::WIFI_PASSWORD_7},
    {AquaSenseSecrets::WIFI_SSID_8, AquaSenseSecrets::WIFI_PASSWORD_8},
    {AquaSenseSecrets::WIFI_SSID_9, AquaSenseSecrets::WIFI_PASSWORD_9},
    {AquaSenseSecrets::WIFI_SSID_10, AquaSenseSecrets::WIFI_PASSWORD_10},
};
constexpr size_t WIFI_SLOT_COUNT = sizeof(WIFI_SLOTS) / sizeof(WIFI_SLOTS[0]);

bool slotConfigured(const WiFiSlot& slot) {
  return strlen(slot.ssid) > 0U && strcmp(slot.ssid, "YOUR_WIFI_NAME") != 0;
}

size_t configuredSlotCount() {
  size_t count = 0;
  for (const WiFiSlot& slot : WIFI_SLOTS) {
    if (slotConfigured(slot)) ++count;
  }
  return count;
}

uint32_t connectionAttemptTimeoutMs(const uint32_t nowMs) {
  const bool startupFastRotation = connectionCount == 0U &&
      nowMs - startupRotationStartedMs < AquaSenseConfig::WIFI_STARTUP_GATE_MS;
  if (!startupFastRotation) return AquaSenseConfig::WIFI_CONNECT_TIMEOUT_MS;
  const size_t count = configuredSlotCount();
  if (count == 0U) return AquaSenseConfig::WIFI_CONNECT_TIMEOUT_MS;
  return min(AquaSenseConfig::WIFI_CONNECT_TIMEOUT_MS,
             AquaSenseConfig::WIFI_STARTUP_ATTEMPT_BUDGET_MS /
                 static_cast<uint32_t>(count));
}

bool startConnectionAttempt(const uint32_t nowMs) {
  const WiFiSlot* selected = nullptr;
  for (size_t checked = 0; checked < WIFI_SLOT_COUNT; ++checked) {
    const uint8_t index = nextNetworkIndex;
    nextNetworkIndex = static_cast<uint8_t>((nextNetworkIndex + 1U) %
                                            WIFI_SLOT_COUNT);
    if (!slotConfigured(WIFI_SLOTS[index])) continue;
    selected = &WIFI_SLOTS[index];
    activeNetworkSlot = static_cast<uint8_t>(index + 1U);
    break;
  }
  if (selected == nullptr) {
    recoveryState = WiFiRecoveryState::kRetryWait;
    nextAttemptMs = nowMs + AquaSenseConfig::WIFI_RETRY_BACKOFF_MS[0];
    Serial.println(F("[WIFI] No configured network slots are available"));
    return false;
  }
  WiFi.disconnect(false, false);
  WiFi.begin(selected->ssid, selected->password);
  connectionAttemptActive = true;
  recoveryState = WiFiRecoveryState::kConnecting;
  attemptStartedMs = nowMs;
  ++attemptCount;
  Serial.printf("[WIFI] Connecting with configured network %u\n",
                static_cast<unsigned int>(activeNetworkSlot));
  return true;
}

uint32_t currentRetryDelayMs() {
  const size_t index = min(retryBackoffIndex,
                           AquaSenseConfig::WIFI_RETRY_BACKOFF_COUNT - 1U);
  const uint32_t delayMs = AquaSenseConfig::WIFI_RETRY_BACKOFF_MS[index];
  if (retryBackoffIndex + 1U < AquaSenseConfig::WIFI_RETRY_BACKOFF_COUNT) {
    ++retryBackoffIndex;
  }
  return delayMs;
}

}  // namespace

void networkBegin() {
  WiFi.persistent(false);
  WiFi.setAutoReconnect(false);
  WiFi.mode(WIFI_STA);
  nextAttemptMs = millis();
  startupRotationStartedMs = nextAttemptMs;
  recoveryState = WiFiRecoveryState::kDisconnected;
}

void networkUpdate(const uint32_t nowMs) {
  const bool connected = WiFi.status() == WL_CONNECTED;
  if (connected) {
    connectionAttemptActive = false;
    recoveryState = WiFiRecoveryState::kConnected;
    if (!previouslyConnected) {
      ++connectionCount;
      retryBackoffIndex = 0;
      Serial.printf("[WIFI] Connected using configured network %u | IP: ",
                    static_cast<unsigned int>(activeNetworkSlot));
      Serial.println(WiFi.localIP());
      if (String(AquaSenseSecrets::API_URL).startsWith("https://")) {
        configTime(0, 0, "pool.ntp.org", "time.nist.gov");
      }
    }
  } else {
    if (previouslyConnected) {
      ++disconnectCount;
      Serial.println(F("[WIFI] Disconnected; processing pause and reconnection scheduled"));
      const uint32_t delayMs = currentRetryDelayMs();
      nextAttemptMs = nowMs + delayMs;
      recoveryState = WiFiRecoveryState::kRetryWait;
      Serial.printf("[WIFI] Retry scheduled in %lu ms\n",
                    static_cast<unsigned long>(delayMs));
    }
    if (connectionAttemptActive &&
        nowMs - attemptStartedMs >= connectionAttemptTimeoutMs(nowMs)) {
      connectionAttemptActive = false;
      WiFi.disconnect(false, false);
      const bool startupFastRotation = connectionCount == 0U &&
          nowMs - startupRotationStartedMs < AquaSenseConfig::WIFI_STARTUP_GATE_MS;
      const uint32_t delayMs = startupFastRotation ? 0U : currentRetryDelayMs();
      nextAttemptMs = nowMs + delayMs;
      recoveryState = delayMs == 0U ? WiFiRecoveryState::kDisconnected
                                    : WiFiRecoveryState::kRetryWait;
      if (delayMs == 0U) {
        Serial.println(F("[WIFI] Connection attempt timed out; trying next configured network"));
      } else {
        Serial.printf("[WIFI] Connection attempt timed out; retry in %lu ms\n",
                      static_cast<unsigned long>(delayMs));
      }
    }
    if (!connectionAttemptActive && timeReached(nowMs, nextAttemptMs)) {
      startConnectionAttempt(nowMs);
    }
  }
  previouslyConnected = connected;
}

bool networkConnected() {
  return WiFi.status() == WL_CONNECTED;
}

void printNetworkStatus() {
  if (!networkConnected()) {
    Serial.printf("Wi-Fi: %s | attempts=%lu | reconnects=%lu | disconnects=%lu\n",
                  networkStateName(), static_cast<unsigned long>(attemptCount),
                  static_cast<unsigned long>(networkReconnectCount()),
                  static_cast<unsigned long>(disconnectCount));
    return;
  }
  Serial.print(F("Wi-Fi: ONLINE | IP: "));
  Serial.print(WiFi.localIP());
  Serial.printf(" | RSSI: %d dBm\n", WiFi.RSSI());
}

uint32_t networkConnectionCount() { return connectionCount; }

uint32_t networkReconnectCount() {
  return connectionCount > 0U ? connectionCount - 1U : 0U;
}

uint32_t networkDisconnectCount() { return disconnectCount; }

uint32_t networkAttemptCount() { return attemptCount; }

const char* networkStateName() {
  switch (recoveryState) {
    case WiFiRecoveryState::kDisconnected: return "DISCONNECTED";
    case WiFiRecoveryState::kConnecting: return "CONNECTING";
    case WiFiRecoveryState::kRetryWait: return "RETRY_WAIT";
    case WiFiRecoveryState::kConnected: return "CONNECTED";
  }
  return "UNKNOWN";
}

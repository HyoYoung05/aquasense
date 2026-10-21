#include "wifi_manager.h"

#include <WiFi.h>
#include <time.h>

#include "config.h"
#include "private_config.h"

namespace {

bool connectionAttemptActive = false;
bool previouslyConnected = false;
uint8_t nextNetworkIndex = 0;
size_t retryBackoffIndex = 0;
uint32_t attemptStartedMs = 0;
uint32_t nextAttemptMs = 0;
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

bool secondNetworkConfigured() {
  return strlen(AquaSenseSecrets::WIFI_SSID_2) > 0;
}

void startConnectionAttempt(const uint32_t nowMs) {
  const bool useSecond = nextNetworkIndex == 1U && secondNetworkConfigured();
  const char* ssid = useSecond ? AquaSenseSecrets::WIFI_SSID_2
                               : AquaSenseSecrets::WIFI_SSID_1;
  const char* password = useSecond ? AquaSenseSecrets::WIFI_PASSWORD_2
                                   : AquaSenseSecrets::WIFI_PASSWORD_1;
  nextNetworkIndex = useSecond ? 0U : (secondNetworkConfigured() ? 1U : 0U);
  WiFi.disconnect(false, false);
  WiFi.begin(ssid, password);
  connectionAttemptActive = true;
  recoveryState = WiFiRecoveryState::kConnecting;
  attemptStartedMs = nowMs;
  ++attemptCount;
  Serial.printf("[WIFI] Connecting with configured network %u\n",
                useSecond ? 2U : 1U);
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
      Serial.print(F("[WIFI] Connected | IP: "));
      Serial.println(WiFi.localIP());
      if (String(AquaSenseSecrets::API_URL).startsWith("https://")) {
        configTime(0, 0, "pool.ntp.org", "time.nist.gov");
      }
    }
  } else {
    if (previouslyConnected) {
      ++disconnectCount;
      Serial.println(F("[WIFI] Disconnected; sensing continues"));
      const uint32_t delayMs = currentRetryDelayMs();
      nextAttemptMs = nowMs + delayMs;
      recoveryState = WiFiRecoveryState::kRetryWait;
      Serial.printf("[WIFI] Retry scheduled in %lu ms\n",
                    static_cast<unsigned long>(delayMs));
    }
    if (connectionAttemptActive &&
        nowMs - attemptStartedMs >= AquaSenseConfig::WIFI_CONNECT_TIMEOUT_MS) {
      connectionAttemptActive = false;
      WiFi.disconnect(false, false);
      const uint32_t delayMs = currentRetryDelayMs();
      nextAttemptMs = nowMs + delayMs;
      recoveryState = WiFiRecoveryState::kRetryWait;
      Serial.printf("[WIFI] Connection attempt timed out; retry in %lu ms\n",
                    static_cast<unsigned long>(delayMs));
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

void networkForceDisconnectForTest() {
  Serial.println(F("[WIFI TEST] Forced disconnect requested"));
  const bool wasConnected = networkConnected();
  WiFi.disconnect(false, false);
  connectionAttemptActive = false;
  if (wasConnected) ++disconnectCount;
  previouslyConnected = false;
  const uint32_t delayMs = currentRetryDelayMs();
  nextAttemptMs = millis() + delayMs;
  recoveryState = WiFiRecoveryState::kRetryWait;
  Serial.printf("[WIFI TEST] Recovery scheduled in %lu ms\n",
                static_cast<unsigned long>(delayMs));
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

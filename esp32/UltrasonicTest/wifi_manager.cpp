#include "wifi_manager.h"

#include <WiFi.h>
#include <time.h>

#include "config.h"
#include "private_config.h"

namespace {

bool connectionAttemptActive = false;
bool previouslyConnected = false;
uint8_t nextNetworkIndex = 0;
uint32_t attemptStartedMs = 0;
uint32_t nextAttemptMs = 0;
uint32_t connectionCount = 0;
uint32_t disconnectCount = 0;

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
  attemptStartedMs = nowMs;
  Serial.printf("[WIFI] Connecting with configured network %u\n",
                useSecond ? 2U : 1U);
}

}  // namespace

void networkBegin() {
  WiFi.persistent(false);
  WiFi.setAutoReconnect(false);
  WiFi.mode(WIFI_STA);
  nextAttemptMs = millis();
}

void networkUpdate(const uint32_t nowMs) {
  const bool connected = WiFi.status() == WL_CONNECTED;
  if (connected) {
    connectionAttemptActive = false;
    if (!previouslyConnected) {
      ++connectionCount;
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
      nextAttemptMs = nowMs + AquaSenseConfig::WIFI_RETRY_INTERVAL_MS;
    }
    if (connectionAttemptActive &&
        nowMs - attemptStartedMs >= AquaSenseConfig::WIFI_CONNECT_TIMEOUT_MS) {
      connectionAttemptActive = false;
      WiFi.disconnect(false, false);
      nextAttemptMs = nowMs + AquaSenseConfig::WIFI_RETRY_INTERVAL_MS;
      Serial.println(F("[WIFI] Connection attempt timed out"));
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
    Serial.println(F("Wi-Fi: OFFLINE"));
    return;
  }
  Serial.print(F("Wi-Fi: ONLINE | IP: "));
  Serial.print(WiFi.localIP());
  Serial.printf(" | RSSI: %d dBm\n", WiFi.RSSI());
}

void networkForceDisconnectForTest() {
  Serial.println(F("[WIFI TEST] Forced disconnect requested"));
  WiFi.disconnect(false, false);
  connectionAttemptActive = false;
  previouslyConnected = false;
  nextAttemptMs = millis() + AquaSenseConfig::WIFI_RETRY_INTERVAL_MS;
}

uint32_t networkConnectionCount() { return connectionCount; }

uint32_t networkReconnectCount() {
  return connectionCount > 0U ? connectionCount - 1U : 0U;
}

uint32_t networkDisconnectCount() { return disconnectCount; }

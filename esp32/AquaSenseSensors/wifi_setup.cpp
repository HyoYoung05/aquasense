#include "wifi_setup.h"

#include <Preferences.h>
#include <WiFi.h>
#include <WiFiProv.h>
#include <atomic>
#include <esp_system.h>
#include "config.h"

namespace {
constexpr uint8_t kSetupButton = 0; // ESP32 DevKit BOOT, pressed while running.
constexpr uint32_t kHoldMs = 3000;
constexpr uint32_t kWindowMs = 300000;
struct OwnerNetwork { char ssid[33]{}; char password[65]{}; };
bool active = false;
uint32_t buttonPressedAt = 0;
bool buttonHeld = false;
uint32_t startedAt = 0;
uint32_t restartAt = 0;
char serviceName[32]{};
char setupProof[17]{};
std::atomic<bool> connectionConfirmed{false};
std::atomic<bool> credentialsFailed{false};
char serialCommand[17]{};
size_t serialCommandSize = 0;

bool saveOwnerWiFi() {
  const String ssid = WiFi.SSID();
  const String password = WiFi.psk();
  if (ssid.length() == 0 || ssid.length() > 32 || password.length() > 64) return false;
  OwnerNetwork record{};
  ssid.toCharArray(record.ssid, sizeof(record.ssid));
  password.toCharArray(record.password, sizeof(record.password));
  Preferences preferences;
  if (!preferences.begin("aq-owner-wifi", false)) return false;
  // One NVS blob commit replaces the previous network only after connection.
  const bool saved = preferences.putBytes("network", &record, sizeof(record)) == sizeof(record);
  preferences.end();
  memset(&record, 0, sizeof(record));
  return saved;
}

bool initializeSetupProof() {
  Preferences preferences;
  if (!preferences.begin("aq-owner-wifi", false)) return false;
  String proof = preferences.getString("proof", "");
  bool valid = proof.length() == 16;
  for (size_t i = 0; valid && i < proof.length(); ++i) valid = isxdigit(static_cast<unsigned char>(proof[i]));
  if (!valid) {
    snprintf(setupProof, sizeof(setupProof), "%08lx%08lx",
             static_cast<unsigned long>(esp_random()), static_cast<unsigned long>(esp_random()));
    if (preferences.putString("proof", setupProof) != 16U) { preferences.end(); return false; }
  } else {
    proof.toLowerCase();
    proof.toCharArray(setupProof, sizeof(setupProof));
  }
  preferences.end();
  return true;
}

void provisioningEvent(arduino_event_t* event) {
  // Callback runs on another task. Only signal the main loop; never log
  // PROV_CRED_RECV, because that event includes the user's Wi-Fi password.
  if (event->event_id == ARDUINO_EVENT_PROV_CRED_SUCCESS) connectionConfirmed.store(true);
  if (event->event_id == ARDUINO_EVENT_PROV_CRED_FAIL) credentialsFailed.store(true);
}

void startSetup(uint32_t nowMs) {
  if (active) return;
  if (!initializeSetupProof()) {
    Serial.println(F("[SETUP] Private storage unavailable; setup not started"));
    return;
  }
  snprintf(serviceName, sizeof(serviceName), "AQUASENSE_%.12s_%04x",
           AquaSenseConfig::DEVICE_CODE, static_cast<unsigned int>(ESP.getEfuseMac() & 0xffffU));
  active = true;
  startedAt = nowMs;
  restartAt = 0;
  connectionConfirmed.store(false);
  credentialsFailed.store(false);
  WiFi.disconnect(false, false);
  Serial.printf("[SETUP] Bluetooth device: %s\n", serviceName);
  // Installer-only output: put this device-specific code on the unit's label.
  // It is not a Wi-Fi/API credential and is never sent to the production API.
  Serial.printf("[SETUP] Device setup code: %s\n", setupProof);
  Serial.println(F("[SETUP] Open Device Wi-Fi in the owner app; setup closes in 5 minutes"));
  uint8_t uuid[16] = {0xb4, 0xdf, 0x5a, 0x1c, 0x3f, 0x6b, 0xf4, 0xbf,
                      0xea, 0x4a, 0x82, 0x03, 0x04, 0x90, 0x1a, 0x02};
  WiFiProv.beginProvision(NETWORK_PROV_SCHEME_BLE, NETWORK_PROV_SCHEME_HANDLER_FREE_BLE,
                         NETWORK_PROV_SECURITY_1, setupProof, serviceName, nullptr, uuid, true);
}
} // namespace

bool loadOwnerWiFi(String& ssid, String& password) {
  ssid = ""; password = "";
  Preferences preferences;
  if (!preferences.begin("aq-owner-wifi", true)) return false;
  OwnerNetwork record{};
  const bool loaded = preferences.getBytesLength("network") == sizeof(record) &&
                      preferences.getBytes("network", &record, sizeof(record)) == sizeof(record);
  preferences.end();
  if (!loaded || record.ssid[32] != '\0' || record.password[64] != '\0' || record.ssid[0] == '\0') {
    memset(&record, 0, sizeof(record));
    return false;
  }
  ssid = record.ssid; password = record.password;
  memset(&record, 0, sizeof(record));
  return true;
}

void wifiSetupBegin() {
  pinMode(kSetupButton, INPUT_PULLUP);
  WiFi.onEvent(provisioningEvent);
  Serial.println(F("[SETUP] Hold BOOT for 3 seconds and release to change Wi-Fi using the app"));
}

void wifiSetupUpdate(uint32_t nowMs) {
  if (active) {
    if (connectionConfirmed.exchange(false)) {
      const bool saved = saveOwnerWiFi();
      Serial.println(saved ? F("[SETUP] Owner Wi-Fi saved; restarting shortly")
                           : F("[SETUP] Save failed; previous owner network retained"));
      restartAt = nowMs + 10000U; // Allow app to receive the connected status.
    }
    if (credentialsFailed.exchange(false)) {
      Serial.println(F("[SETUP] New network failed; previous saved network retained"));
      network_prov_mgr_reset_wifi_sm_state_on_failure();
    }
    if (restartAt != 0 && static_cast<int32_t>(nowMs - restartAt) >= 0) ESP.restart();
    if (restartAt == 0 && nowMs - startedAt >= kWindowMs) {
      Serial.println(F("[SETUP] Setup window expired; restoring normal operation"));
      ESP.restart();
    }
    return;
  }
  if (digitalRead(kSetupButton) == LOW) {
    if (buttonPressedAt == 0) buttonPressedAt = nowMs;
    if (nowMs - buttonPressedAt >= kHoldMs) buttonHeld = true;
  } else {
    buttonPressedAt = 0;
    if (buttonHeld) { buttonHeld = false; startSetup(nowMs); }
  }
  // Equivalent installer trigger over the physical USB serial connection.
  while (!active && Serial.available() > 0) {
    const char c = static_cast<char>(Serial.read());
    if (c == '\n') {
      serialCommand[serialCommandSize] = '\0';
      if (strcmp(serialCommand, "WIFI_SETUP") == 0) startSetup(nowMs);
      serialCommandSize = 0;
    } else if (c != '\r') {
      if (serialCommandSize < sizeof(serialCommand) - 1U) serialCommand[serialCommandSize++] = c;
      else serialCommandSize = 0;
    }
  }
}

bool wifiSetupActive() { return active; }

// AQUASENSE+ 0.1.1: one HC-SR04-style ultrasonic sensor, bench testing only.
// No MySQL client, other sensors, battery logic, actuator, or reboot-on-error.
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <time.h>
#include "level_math.h"
#if __has_include("config.local.h")
#include "config.local.h"
#else
#include "config.example.h"
#endif

bool configurationValid = false;
bool wasConnected = false;
unsigned long lastAttempt = 0;
unsigned long lastSample = 0;
uint8_t nextNetwork = 0;

bool safeDeviceId() {
  if (strlen(DEVICE_ID) == 0 || strlen(DEVICE_ID) > 60) return false;
  for (const char* p = DEVICE_ID; *p; ++p)
    if (!isalnum(static_cast<unsigned char>(*p)) && *p != '-' && *p != '_') return false;
  return true;
}

void connectNextNetwork() {
  bool second = nextNetwork == 1 && strlen(WIFI_SSID_2) > 0;
  nextNetwork = second ? 0 : 1;
  WiFi.disconnect(false, false);
  WiFi.begin(second ? WIFI_SSID_2 : WIFI_SSID_1,
             second ? WIFI_PASSWORD_2 : WIFI_PASSWORD_1);
  lastAttempt = millis();
  Serial.printf("Trying configured Wi-Fi %d...\n", second ? 2 : 1);
}

void maintainWiFi() {
  bool connected = WiFi.status() == WL_CONNECTED;
  if (connected && !wasConnected) {
    Serial.print("Wi-Fi connected. ESP32 IP: ");
    Serial.println(WiFi.localIP());
    if (String(API_URL).startsWith("https://")) configTime(0, 0, "pool.ntp.org", "time.nist.gov");
  } else if (!connected && wasConnected) {
    Serial.println("Wi-Fi disconnected. Sensor sampling continues; reconnection will retry.");
  }
  wasConnected = connected;
  if (!connected && millis() - lastAttempt >= WIFI_ATTEMPT_MS) connectNextNetwork();
}

float readDistance() {
  float samples[5];
  uint8_t count = 0;
  for (int i = 0; i < 5; ++i) {
    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG_PIN, LOW);
    unsigned long echo = pulseIn(ECHO_PIN, HIGH, 30000UL);
    float cm = echo * 0.0343f / 2.0f;
    Serial.printf("Raw echo: %lu us; distance: %.2f cm%s\n",
                  echo, cm, (echo == 0 || cm < 2 || cm > 400) ? " INVALID" : "");
    if (echo > 0 && isfinite(cm) && cm >= 2 && cm <= 400) samples[count++] = cm;
    delay(65); // HC-SR04 measurement spacing; all waits are bounded.
  }
  if (count < 3) return NAN;
  for (int i = 1; i < count; ++i) {
    float value = samples[i];
    int j = i - 1;
    while (j >= 0 && samples[j] > value) { samples[j + 1] = samples[j]; --j; }
    samples[j + 1] = value;
  }
  // Round the same distance used in the JSON so the server computes identically.
  return roundf(samples[count / 2] * 100.0f) / 100.0f;
}

void sendReading(float distance, float percent, const char* status) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Not sent: Wi-Fi offline. Next cycle will use a fresh reading.");
    return;
  }
  String url(API_URL);
  bool secure = url.startsWith("https://");
  if (secure && (strlen(TLS_ROOT_CA) == 0 || time(nullptr) < 1700000000)) {
    Serial.println("HTTPS not ready: configure trusted CA and wait for clock sync. No request sent.");
    return;
  }
  char json[320];
  int size = snprintf(json, sizeof(json),
    "{\"device_id\":\"%s\",\"grease_trap_id\":%d,\"ultrasonic_distance\":%.2f,"
    "\"waste_level_percent\":%.1f,\"status\":\"%s\"}",
    DEVICE_ID, GREASE_TRAP_ID, distance, percent, status);
  if (size < 0 || size >= (int) sizeof(json)) {
    Serial.println("JSON buffer error; skipping this sample.");
    return;
  }
  WiFiClient plain;
  WiFiClientSecure tls;
  if (secure) {
    tls.setCACert(TLS_ROOT_CA);
    tls.setHandshakeTimeout(5);
  }
  HTTPClient http;
  http.setConnectTimeout(HTTP_TIMEOUT_MS);
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
  bool opened = secure ? http.begin(tls, url) : http.begin(plain, url);
  if (!opened) { Serial.println("Could not initialize API request."); return; }
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + DEVICE_API_KEY);
  Serial.print("POST JSON: ");
  Serial.println(json); // No password or device credential is printed.
  int code = http.POST(reinterpret_cast<uint8_t*>(json), strlen(json));
  Serial.printf("HTTP result: %d\n", code);
  if (code > 0) {
    String response = http.getString();
    Serial.println(response.substring(0, 1024));
    if (code >= 200 && code < 300) Serial.println("Reading accepted by API.");
    else if (code == 401 || code == 403) Serial.println("Check device key and active trap assignment.");
    else if (code == 422) Serial.println("Check calibration and test thresholds against the server.");
    else if (code == 429) Serial.println("Rate limited. Waiting for next sample cycle.");
    else Serial.println("Server rejected reading; next cycle will try fresh telemetry.");
  } else {
    Serial.print("Network/timeout error: ");
    Serial.println(HTTPClient::errorToString(code));
  }
  http.end();
}

void setup() {
  Serial.begin(115200);
  pinMode(TRIG_PIN, OUTPUT);
  digitalWrite(TRIG_PIN, LOW);
  pinMode(ECHO_PIN, INPUT);
  bool keyValid = strlen(DEVICE_API_KEY) == 64;
  for (const char* p = DEVICE_API_KEY; *p; ++p)
    keyValid = keyValid && isxdigit(static_cast<unsigned char>(*p));
  configurationValid = safeDeviceId() && keyValid && GREASE_TRAP_ID > 0
    && strlen(WIFI_SSID_1) > 0 && String(WIFI_SSID_1) != "YOUR_WIFI"
    && (String(API_URL).startsWith("http://") || String(API_URL).startsWith("https://"))
    && String(API_URL).indexOf("YOUR_") < 0
    && FULL_DISTANCE_CM >= 2 && EMPTY_DISTANCE_CM > FULL_DISTANCE_CM && EMPTY_DISTANCE_CM <= 400
    && WARNING_PERCENT > 0 && WARNING_PERCENT < CRITICAL_PERCENT && CRITICAL_PERCENT < 100
    && SAMPLE_INTERVAL_MS >= 2000;
  Serial.println("AQUASENSE+ ultrasonic bench test v0.1.1");
  Serial.printf("Ultrasonic pins: TRIG=GPIO%d, ECHO=GPIO%d\n", TRIG_PIN, ECHO_PIN);
  if (!configurationValid) {
    Serial.println("Configuration incomplete. Edit config.local.h; sensor uploads are disabled.");
    return;
  }
  WiFi.persistent(false);
  WiFi.setAutoReconnect(false);
  WiFi.mode(WIFI_STA);
  connectNextNetwork();
  lastSample = millis() - SAMPLE_INTERVAL_MS;
}

void loop() {
  if (!configurationValid) { delay(50); return; }
  maintainWiFi();
  if (millis() - lastSample < SAMPLE_INTERVAL_MS) { delay(10); return; }
  float distance = readDistance();
  if (!isfinite(distance)) {
    Serial.println("Invalid ultrasonic sample: UNKNOWN. No zero-fill/safe reading sent.");
  } else {
    float percent = fillPercent(distance, EMPTY_DISTANCE_CM, FULL_DISTANCE_CM);
    const char* status = levelStatus(percent, WARNING_PERCENT, CRITICAL_PERCENT);
    Serial.printf("Filtered: %.2f cm | fill: %.1f%% | %s\n", distance, percent, status);
    sendReading(distance, percent, status);
  }
  // Wait after each attempt; a slow or unavailable server cannot cause a tight retry loop.
  lastSample = millis();
}

#pragma once
// Copy to config.local.h. Keep that private file out of Git.
// Configuration for an HC-SR04-style trigger/echo sensor on a classic ESP32 DevKit.
constexpr char WIFI_SSID_1[] = "YOUR_WIFI";
constexpr char WIFI_PASSWORD_1[] = "YOUR_WIFI_PASSWORD";
constexpr char WIFI_SSID_2[] = "";       // Optional second reachable test Wi-Fi.
constexpr char WIFI_PASSWORD_2[] = "";
constexpr char API_URL[] = "http://YOUR_PC_IP/AQUASENSE+/aquasense-web/api/device/telemetry.php";
constexpr char DEVICE_ID[] = "AQS-001";
constexpr int GREASE_TRAP_ID = 0;        // Use the ID returned by device setup.
constexpr char DEVICE_API_KEY[] = "";   // Unique 64-hex device credential, never an owner token.
constexpr int TRIG_PIN = 5;
constexpr int ECHO_PIN = 18;             // 5V Echo MUST go through a voltage divider.
constexpr float EMPTY_DISTANCE_CM = 30.0f;
constexpr float FULL_DISTANCE_CM = 5.0f;
constexpr float WARNING_PERCENT = 75.0f;
constexpr float CRITICAL_PERCENT = 90.0f;
constexpr unsigned long SAMPLE_INTERVAL_MS = 5000;
constexpr unsigned long WIFI_ATTEMPT_MS = 15000;
constexpr uint16_t HTTP_TIMEOUT_MS = 5000;
// For a future HTTPS test host, supply its trusted CA here. TLS verification
// is mandatory for https:// URLs; the sketch never uses setInsecure().
constexpr char TLS_ROOT_CA[] = "";

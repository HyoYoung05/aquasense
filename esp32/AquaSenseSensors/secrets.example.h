#pragma once

// Copy this file to secrets.h and replace every placeholder. secrets.h is
// ignored by Git. Never commit Wi-Fi passwords or the device credential.
namespace AquaSenseSecrets {

inline constexpr char WIFI_SSID_1[] = "YOUR_WIFI_NAME";
inline constexpr char WIFI_PASSWORD_1[] = "YOUR_WIFI_PASSWORD";
inline constexpr char WIFI_SSID_2[] = "";
inline constexpr char WIFI_PASSWORD_2[] = "";

inline constexpr char API_URL[] =
    "http://YOUR_SERVER/AQUASENSE+/aquasense-web/api/device/telemetry.php";

// The 64-hex credential issued for AQS-001. This is not a website password.
inline constexpr char DEVICE_API_KEY[] = "YOUR_64_HEX_DEVICE_KEY";

// Required for production HTTPS. Do not use an insecure TLS client.
inline constexpr char TLS_ROOT_CA[] = "";

}  // namespace AquaSenseSecrets

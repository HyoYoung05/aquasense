#pragma once
#include <Arduino.h>

// Saved credentials are private to the ESP32. Never print these outputs.
bool loadOwnerWiFi(String& ssid, String& password);
void wifiSetupBegin();
void wifiSetupUpdate(uint32_t nowMs);
bool wifiSetupActive();

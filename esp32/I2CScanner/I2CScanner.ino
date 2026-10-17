#include <Arduino.h>
#include <Wire.h>

constexpr uint8_t SDA_PIN = 21;
constexpr uint8_t SCL_PIN = 22;
constexpr uint32_t SCAN_INTERVAL_MS = 2000;
uint32_t lastScanMs = 0;
uint32_t scanNumber = 0;

void scanBus() {
  ++scanNumber;
  uint8_t found = 0;
  Serial.printf("[I2C] Scan %lu\n", static_cast<unsigned long>(scanNumber));
  for (uint8_t address = 0x08; address <= 0x77; ++address) {
    Wire.beginTransmission(address);
    if (Wire.endTransmission() == 0U) {
      Serial.printf("[I2C] Found 0x%02X\n", address);
      ++found;
    }
  }
  Serial.printf("[I2C] Complete: %u device(s)\n", found);
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  Wire.begin(SDA_PIN, SCL_PIN);
  Wire.setTimeOut(50);
  Serial.println(F("AQUASENSE+ Phase 4D I2C Scanner"));
  Serial.printf("SDA=GPIO%u | SCL=GPIO%u\n", SDA_PIN, SCL_PIN);
  scanBus();
  lastScanMs = millis();
}

void loop() {
  const uint32_t nowMs = millis();
  if (nowMs - lastScanMs >= SCAN_INTERVAL_MS) {
    lastScanMs = nowMs;
    scanBus();
  }
  delay(1);
}

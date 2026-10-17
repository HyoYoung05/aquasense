#include "i2c_bus.h"
#include <Wire.h>
#include "config.h"
#include "pins.h"

void i2cBusBegin() {
  Wire.begin(AquaSensePins::ENS160_SDA, AquaSensePins::ENS160_SCL);
  Wire.setTimeOut(AquaSenseConfig::I2C_TIMEOUT_MS);
}

bool i2cAddressPresent(const uint8_t address) {
  if (address < 0x08U || address > 0x77U) return false;
  Wire.beginTransmission(address);
  return Wire.endTransmission() == 0U;
}

bool i2cAddressStable(const uint8_t address, const uint8_t requiredScans) {
  if (requiredScans == 0U) return false;
  for (uint8_t scan = 0; scan < requiredScans; ++scan) {
    if (!i2cAddressPresent(address)) return false;
    delay(10);
  }
  return true;
}

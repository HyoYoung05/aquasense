#include "i2c_bus.h"
#include <Wire.h>
#include "config.h"
#include "pins.h"

namespace {
uint32_t errorCount = 0;
uint32_t recoveryCount = 0;
uint32_t lastRecoveryMs = 0;
}

void i2cBusBegin() {
  Wire.begin(AquaSensePins::ENS160_SDA, AquaSensePins::ENS160_SCL);
  Wire.setClock(AquaSenseConfig::I2C_CLOCK_HZ);
  Wire.setTimeOut(AquaSenseConfig::I2C_TIMEOUT_MS);
}

bool i2cAddressPresent(const uint8_t address) {
  if (address < 0x08U || address > 0x77U) return false;
  Wire.beginTransmission(address);
  const uint8_t result = Wire.endTransmission();
  if (result != 0U) ++errorCount;
  return result == 0U;
}

void i2cRecordError() { ++errorCount; }

uint32_t i2cErrorCount() { return errorCount; }

uint32_t i2cRecoveryCount() { return recoveryCount; }

void i2cRecover(const uint32_t nowMs) {
  if (nowMs - lastRecoveryMs < AquaSenseConfig::I2C_RETRY_MS) return;
  Wire.end();
  Wire.begin(AquaSensePins::ENS160_SDA, AquaSensePins::ENS160_SCL);
  Wire.setClock(AquaSenseConfig::I2C_CLOCK_HZ);
  Wire.setTimeOut(AquaSenseConfig::I2C_TIMEOUT_MS);
  lastRecoveryMs = nowMs;
  ++recoveryCount;
  Serial.println(F("[I2C] Bus reinitialized after controlled retry interval"));
}

bool i2cAddressStable(const uint8_t address, const uint8_t requiredScans) {
  if (requiredScans == 0U) return false;
  for (uint8_t scan = 0; scan < requiredScans; ++scan) {
    if (!i2cAddressPresent(address)) return false;
    delay(10);
  }
  return true;
}

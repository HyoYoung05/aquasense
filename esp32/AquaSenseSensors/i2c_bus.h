#pragma once
#include <Arduino.h>
void i2cBusBegin();
bool i2cAddressPresent(uint8_t address);
bool i2cAddressStable(uint8_t address, uint8_t requiredScans);
void i2cRecordError();
uint32_t i2cErrorCount();
uint32_t i2cRecoveryCount();
void i2cRecover(uint32_t nowMs);

#pragma once
#include <Arduino.h>
void i2cBusBegin();
bool i2cAddressPresent(uint8_t address);
bool i2cAddressStable(uint8_t address, uint8_t requiredScans);

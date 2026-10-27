#pragma once

// Copy to config.local.h. That file is ignored by Git.
// Set a gate to 1 only after checking the installed module and protection.

// Match these values to the device and active assignment registered in the
// AQUASENSE+ website before uploading firmware.
#define AQUASENSE_DEVICE_CODE "AQS-001"
#define AQUASENSE_GREASE_TRAP_ID 1

#define AQUASENSE_HW_ENABLE_DS18B20 0

#define AQUASENSE_HW_ENABLE_TURBIDITY 0
#define AQUASENSE_HW_TURBIDITY_INPUT_VERIFIED 0

#define AQUASENSE_HW_ENABLE_FLOW 0
#define AQUASENSE_HW_FLOW_SIGNAL_VERIFIED 0

#define AQUASENSE_HW_ENABLE_ENS160 0
#define AQUASENSE_HW_ENS160_I2C_ADDRESS 0x00
#define AQUASENSE_HW_ENS160_USE_INTERRUPT 0

#define AQUASENSE_HW_ENABLE_AHT2X 0
#define AQUASENSE_HW_AHT2X_I2C_ADDRESS 0x00

#define AQUASENSE_HW_ENABLE_MQ135 0
#define AQUASENSE_HW_MQ135_INPUT_VERIFIED 0

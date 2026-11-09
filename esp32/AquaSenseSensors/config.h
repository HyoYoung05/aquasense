#pragma once

#include <Arduino.h>

// Optional ignored hardware profile. The committed defaults below keep every
// physically gated Phase 4 input disabled until the specific installation is
// checked. Copy config.local.example.h to config.local.h to activate only the
// modules and protected inputs verified on the connected prototype.
#if __has_include("config.local.h")
#include "config.local.h"
#endif

#ifndef AQUASENSE_HW_ENABLE_DS18B20
#define AQUASENSE_HW_ENABLE_DS18B20 0
#endif
#ifndef AQUASENSE_HW_ENABLE_TURBIDITY
#define AQUASENSE_HW_ENABLE_TURBIDITY 0
#endif
#ifndef AQUASENSE_HW_TURBIDITY_INPUT_VERIFIED
#define AQUASENSE_HW_TURBIDITY_INPUT_VERIFIED 0
#endif
#ifndef AQUASENSE_HW_ENABLE_FLOW
#define AQUASENSE_HW_ENABLE_FLOW 0
#endif
#ifndef AQUASENSE_HW_FLOW_SIGNAL_VERIFIED
#define AQUASENSE_HW_FLOW_SIGNAL_VERIFIED 0
#endif
#ifndef AQUASENSE_HW_ENABLE_MQ135
#define AQUASENSE_HW_ENABLE_MQ135 0
#endif
#ifndef AQUASENSE_HW_MQ135_INPUT_VERIFIED
#define AQUASENSE_HW_MQ135_INPUT_VERIFIED 0
#endif
#ifndef AQUASENSE_DEVICE_CODE
#define AQUASENSE_DEVICE_CODE "AQS-001"
#endif
#ifndef AQUASENSE_GREASE_TRAP_ID
#define AQUASENSE_GREASE_TRAP_ID 12
#endif

namespace AquaSenseConfig {

inline constexpr char FIRMWARE_VERSION[] = "0.8.19";
inline constexpr char POWER_MODE[] =
    "USB DEVELOPMENT - EXTERNAL DC NOT VERIFIED";
inline constexpr bool EXTERNAL_DC_POWER_VERIFIED = false;
inline constexpr bool RAIL_VOLTAGE_MONITOR_PRESENT = false;
inline constexpr uint32_t SERIAL_BAUD = 115200;
inline constexpr uint32_t STARTUP_DELAY_MS = 1000;
inline constexpr uint32_t STATUS_INTERVAL_MS = 5000;
inline constexpr uint32_t HEALTH_INTERVAL_MS = 30000;
inline constexpr uint32_t HEARTBEAT_INTERVAL_MS = 30000;
inline constexpr uint32_t HEARTBEAT_RETRY_MS = 10000;
inline constexpr uint32_t SENSOR_INTERVAL_MS = 1000;
inline constexpr uint32_t TELEMETRY_INTERVAL_MS = 5000;
inline constexpr uint32_t INITIAL_TELEMETRY_DELAY_MS = 5000;
inline constexpr uint32_t QUEUE_DRAIN_INTERVAL_MS = 2500;
inline constexpr size_t TELEMETRY_QUEUE_CAPACITY = 8;

// Device identity is installation-specific. Production builds override these
// defaults from the ignored config.local.h file rather than editing source.
inline constexpr char DEVICE_CODE[] = AQUASENSE_DEVICE_CODE;
inline constexpr uint32_t GREASE_TRAP_ID = AQUASENSE_GREASE_TRAP_ID;

inline constexpr uint32_t WIFI_CONNECT_TIMEOUT_MS = 12000;
inline constexpr uint32_t WIFI_STARTUP_GATE_MS = 60000;
inline constexpr uint32_t WIFI_STARTUP_ATTEMPT_BUDGET_MS = 55000;
inline constexpr uint32_t WIFI_RETRY_BACKOFF_MS[] = {5000, 10000, 30000};
inline constexpr size_t WIFI_RETRY_BACKOFF_COUNT =
    sizeof(WIFI_RETRY_BACKOFF_MS) / sizeof(WIFI_RETRY_BACKOFF_MS[0]);
inline constexpr uint32_t HTTP_TIMEOUT_MS = 5000;
inline constexpr uint32_t CONFIGURATION_RETRY_MS = 30000;
inline constexpr uint32_t RETRY_BACKOFF_MS[] = {5000, 10000, 30000};
inline constexpr size_t RETRY_BACKOFF_COUNT =
    sizeof(RETRY_BACKOFF_MS) / sizeof(RETRY_BACKOFF_MS[0]);
inline constexpr size_t MAX_RESPONSE_LOG_CHARS = 512;

inline constexpr uint8_t ULTRASONIC_SAMPLE_COUNT = 5;
inline constexpr uint8_t ULTRASONIC_MINIMUM_VALID_SAMPLES = 3;
// Keep the five-sample median filter, but do not flood the normal live report.
// Set true only while diagnosing individual echo pulses on the bench.
inline constexpr bool VERBOSE_ULTRASONIC_SERIAL = false;
inline constexpr uint32_t ULTRASONIC_ECHO_TIMEOUT_US = 30000;
inline constexpr uint32_t ULTRASONIC_SAMPLE_SPACING_MS = 60;
inline constexpr float SPEED_OF_SOUND_CM_PER_US = 0.0343F;
inline constexpr float ULTRASONIC_MIN_DISTANCE_CM = 2.0F;
inline constexpr float ULTRASONIC_MAX_DISTANCE_CM = 400.0F;

// Phase 4 compile checks include the selected libraries. Hardware stays
// disabled until its wiring, supply voltage, and protection are verified.
#define AQUASENSE_ENABLE_DS18B20_LIBRARIES 1
inline constexpr bool ENABLE_DS18B20 = AQUASENSE_HW_ENABLE_DS18B20 != 0;
inline constexpr bool ENABLE_TURBIDITY = AQUASENSE_HW_ENABLE_TURBIDITY != 0;
inline constexpr bool TURBIDITY_INPUT_VOLTAGE_VERIFIED =
    AQUASENSE_HW_TURBIDITY_INPUT_VERIFIED != 0;
inline constexpr bool ENABLE_FLOW = AQUASENSE_HW_ENABLE_FLOW != 0;
inline constexpr bool FLOW_SIGNAL_VOLTAGE_VERIFIED =
    AQUASENSE_HW_FLOW_SIGNAL_VERIFIED != 0;
inline constexpr bool ENABLE_MQ135 = AQUASENSE_HW_ENABLE_MQ135 != 0;
inline constexpr bool MQ135_INPUT_VOLTAGE_VERIFIED =
    AQUASENSE_HW_MQ135_INPUT_VERIFIED != 0;

inline constexpr uint32_t TEMPERATURE_INTERVAL_MS = 3000;
inline constexpr uint32_t TEMPERATURE_CONVERSION_MS = 750;
inline constexpr uint32_t TEMPERATURE_RETRY_MS = 30000;
inline constexpr uint32_t TURBIDITY_INTERVAL_MS = 3000;
inline constexpr uint8_t ANALOG_SAMPLE_COUNT = 8;
inline constexpr uint32_t FLOW_INTERVAL_MS = 1000;
inline constexpr uint32_t MQ135_INTERVAL_MS = 5000;
// Minimum development stabilization gate. The installed MQ-135 may require
// longer initial conditioning according to its actual module documentation.
inline constexpr uint32_t MQ135_WARMUP_MS = 180000;

inline constexpr uint16_t ADC_MAX_COUNT = 4095;
// Counts at either ADC rail usually indicate a short, open/divider fault, or
// unusable input. The margin is an electrical-validity gate, not calibration.
inline constexpr uint16_t ANALOG_RAIL_FAULT_MARGIN = 4;
inline constexpr float ADC_REFERENCE_V = 3.3F;
// Revised schematic: 10k series and 20k to ground => source = ADC * 1.5.
inline constexpr float ANALOG_SOURCE_MULTIPLIER = 1.5F;
inline constexpr uint8_t DS18B20_RESOLUTION_BITS = 12;

}  // namespace AquaSenseConfig

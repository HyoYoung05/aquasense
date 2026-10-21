#pragma once

#include <Arduino.h>

// Official GPIO plan from the revised AQUASENSE+ capstone paper.
// A Phase 4 module configures its pins only after its hardware safety gate is
// enabled. Reserved pins remain untouched while sensors are absent.
namespace AquaSensePins {

inline constexpr uint8_t ULTRASONIC_TRIG = 5;
inline constexpr uint8_t ULTRASONIC_ECHO = 18;
inline constexpr uint8_t DS18B20_DATA = 4;
inline constexpr uint8_t FLOW_SIGNAL = 19;
inline constexpr uint8_t TURBIDITY_ANALOG = 34;
inline constexpr uint8_t MQ135_ANALOG = 35;
inline constexpr uint8_t ENS160_SDA = 21;
inline constexpr uint8_t ENS160_SCL = 22;
inline constexpr uint8_t ENS160_INT = 26;

static_assert(ULTRASONIC_TRIG != ULTRASONIC_ECHO, "Ultrasonic pins conflict");
static_assert(TURBIDITY_ANALOG >= 32 && MQ135_ANALOG >= 32,
              "Analog sensors must remain on ADC1 while future Wi-Fi is active");

}  // namespace AquaSensePins

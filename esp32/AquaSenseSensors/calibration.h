#pragma once

#include <Arduino.h>
#include <math.h>

// Hardware Phase 6 calibration registry.
//
// Values in this file are the only firmware-side calibration constants. A
// calibration flag must remain false until the corresponding physical test is
// completed and recorded in docs/CALIBRATION.md. Disabled calibration values
// must never be reported as calibrated engineering measurements.
namespace AquaSenseCalibration {

// HC-SR04: temporary bench geometry only. The PHP backend remains authoritative
// for fill percentage and uses the assigned grease trap's database calibration.
inline constexpr bool ULTRASONIC_PHYSICALLY_CALIBRATED = false;
inline constexpr float ULTRASONIC_EMPTY_DISTANCE_CM = 30.0F;
inline constexpr float ULTRASONIC_FULL_DISTANCE_CM = 5.0F;

// DS18B20: keep zero until repeated comparison with a reference thermometer
// demonstrates a consistent systematic offset.
inline constexpr bool DS18B20_REFERENCE_VALIDATED = false;
inline constexpr float DS18B20_TEMPERATURE_OFFSET_C = 0.0F;

// SEN0189: quadratic voltage-to-NTU coefficients are intentionally unavailable.
// Set the flag true only after reference-standard calibration and replace every
// coefficient with the measured fit. Raw/filtered ADC remains available while
// calibration is incomplete.
inline constexpr bool TURBIDITY_NTU_CALIBRATED = false;
inline constexpr float TURBIDITY_NTU_COEFFICIENT_A = NAN;
inline constexpr float TURBIDITY_NTU_COEFFICIENT_B = NAN;
inline constexpr float TURBIDITY_NTU_COEFFICIENT_C = NAN;

// YF-S201: 450 pulses/L is a provisional development reference. It is not used
// to publish L/min until known-volume trials approve a measured value.
inline constexpr bool FLOW_PHYSICALLY_CALIBRATED = false;
inline constexpr float FLOW_PULSES_PER_LITER = 450.0F;

// ENS160 outputs use the sensor/library's AQI, TVOC, and eCO2 algorithms. AHT2x
// values are supplied as compensation when both devices are valid. These flags
// record pending comparison/stability validation; they do not invent offsets.
inline constexpr bool ENS160_RESPONSE_VALIDATED = false;
inline constexpr bool AHT2X_REFERENCE_VALIDATED = false;

// MQ-135 remains raw/relative. No ppm conversion is permitted without a
// documented clean-air baseline, module load resistance, target-gas curve, and
// reference procedure.
inline constexpr bool MQ135_BASELINE_VALIDATED = false;
inline constexpr float MQ135_CLEAN_AIR_BASELINE_ADC = NAN;
inline constexpr bool MQ135_PPM_CALIBRATED = false;

}  // namespace AquaSenseCalibration

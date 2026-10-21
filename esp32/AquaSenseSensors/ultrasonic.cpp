#include "ultrasonic.h"
#include "calibration.h"

#include <math.h>

#include "level_math.h"
#include "pins.h"

namespace {

float durationToDistanceCm(const uint32_t durationUs) {
  return (static_cast<float>(durationUs) *
          AquaSenseConfig::SPEED_OF_SOUND_CM_PER_US) / 2.0F;
}

uint32_t captureEchoDurationUs() {
  digitalWrite(AquaSensePins::ULTRASONIC_TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(AquaSensePins::ULTRASONIC_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(AquaSensePins::ULTRASONIC_TRIG, LOW);
  return pulseIn(AquaSensePins::ULTRASONIC_ECHO, HIGH,
                 AquaSenseConfig::ULTRASONIC_ECHO_TIMEOUT_US);
}

float medianOfValidSamples(float* samples, const uint8_t count) {
  for (uint8_t index = 1; index < count; ++index) {
    const float value = samples[index];
    int8_t previous = static_cast<int8_t>(index) - 1;
    while (previous >= 0 && samples[previous] > value) {
      samples[previous + 1] = samples[previous];
      --previous;
    }
    samples[previous + 1] = value;
  }
  if ((count % 2U) == 1U) return samples[count / 2U];
  return (samples[(count / 2U) - 1U] + samples[count / 2U]) / 2.0F;
}

}  // namespace

void ultrasonicBegin() {
  pinMode(AquaSensePins::ULTRASONIC_TRIG, OUTPUT);
  digitalWrite(AquaSensePins::ULTRASONIC_TRIG, LOW);
  pinMode(AquaSensePins::ULTRASONIC_ECHO, INPUT);
}

bool ultrasonicAlgorithmSelfTest() {
  float medianSamples[] = {30.0F, 10.0F, 21.0F, 20.0F, 20.0F};
  const float median = medianOfValidSamples(medianSamples, 5U);
  const float fillAt10 = calculateTestFillPercent(
      10.0F, AquaSenseCalibration::ULTRASONIC_EMPTY_DISTANCE_CM,
      AquaSenseCalibration::ULTRASONIC_FULL_DISTANCE_CM);
  const float fillAt20 = calculateTestFillPercent(
      20.0F, AquaSenseCalibration::ULTRASONIC_EMPTY_DISTANCE_CM,
      AquaSenseCalibration::ULTRASONIC_FULL_DISTANCE_CM);
  const float fillAt30 = calculateTestFillPercent(
      30.0F, AquaSenseCalibration::ULTRASONIC_EMPTY_DISTANCE_CM,
      AquaSenseCalibration::ULTRASONIC_FULL_DISTANCE_CM);
  const float invalidFill = calculateTestFillPercent(
      NAN, AquaSenseCalibration::ULTRASONIC_EMPTY_DISTANCE_CM,
      AquaSenseCalibration::ULTRASONIC_FULL_DISTANCE_CM);
  return fabsf(median - 20.0F) < 0.01F &&
         fabsf(fillAt10 - 80.0F) < 0.01F &&
         fabsf(fillAt20 - 40.0F) < 0.01F &&
         fabsf(fillAt30 - 0.0F) < 0.01F &&
         !isfinite(invalidFill);
}

UltrasonicReading readUltrasonic() {
  UltrasonicReading reading{};
  float validDistances[AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT]{};
  bool anyOutOfRange = false;

  for (uint8_t index = 0;
       index < AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT; ++index) {
    const uint32_t durationUs = captureEchoDurationUs();
    reading.echoDurationUs[index] = durationUs;
    if (durationUs > 0U) {
      const float distanceCm = durationToDistanceCm(durationUs);
      reading.rawDistanceCm[index] = distanceCm;
      const bool valid = isfinite(distanceCm) &&
                         distanceCm >= AquaSenseConfig::ULTRASONIC_MIN_DISTANCE_CM &&
                         distanceCm <= AquaSenseConfig::ULTRASONIC_MAX_DISTANCE_CM;
      reading.sampleValid[index] = valid;
      if (valid) {
        validDistances[reading.validSampleCount++] = distanceCm;
      } else {
        anyOutOfRange = true;
      }
    }
    if (index + 1U < AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT) {
      delay(AquaSenseConfig::ULTRASONIC_SAMPLE_SPACING_MS);
    }
  }

  if (reading.validSampleCount <
      AquaSenseConfig::ULTRASONIC_MINIMUM_VALID_SAMPLES) {
    reading.status = reading.validSampleCount == 0U && !anyOutOfRange
        ? UltrasonicStatus::kNoEcho
        : (anyOutOfRange && reading.validSampleCount == 0U
            ? UltrasonicStatus::kOutOfRange
            : UltrasonicStatus::kInsufficientValidSamples);
    return reading;
  }

  reading.medianDistanceCm =
      medianOfValidSamples(validDistances, reading.validSampleCount);
  if (!testCalibrationIsValid(
          AquaSenseCalibration::ULTRASONIC_EMPTY_DISTANCE_CM,
          AquaSenseCalibration::ULTRASONIC_FULL_DISTANCE_CM)) {
    reading.status = UltrasonicStatus::kCalibrationError;
    return reading;
  }
  reading.testFillPercent = calculateTestFillPercent(
      reading.medianDistanceCm,
      AquaSenseCalibration::ULTRASONIC_EMPTY_DISTANCE_CM,
      AquaSenseCalibration::ULTRASONIC_FULL_DISTANCE_CM);
  reading.status = isfinite(reading.testFillPercent)
      ? UltrasonicStatus::kOk
      : UltrasonicStatus::kCalibrationError;
  return reading;
}

const char* ultrasonicStatusName(const UltrasonicStatus status) {
  switch (status) {
    case UltrasonicStatus::kOk: return "OK";
    case UltrasonicStatus::kNoEcho: return "NO_ECHO";
    case UltrasonicStatus::kOutOfRange: return "OUT_OF_RANGE";
    case UltrasonicStatus::kInsufficientValidSamples: return "INSUFFICIENT_SAMPLES";
    case UltrasonicStatus::kCalibrationError: return "CALIBRATION_ERROR";
  }
  return "ERROR";
}

void printUltrasonicDiagnostics(const UltrasonicReading& reading,
                                const uint32_t sequence) {
  Serial.printf("\n[ULTRASONIC #%lu]\n", static_cast<unsigned long>(sequence));
  for (uint8_t index = 0;
       index < AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT; ++index) {
    Serial.printf("Sample %u | Echo: %lu us | ",
                  static_cast<unsigned int>(index + 1U),
                  static_cast<unsigned long>(reading.echoDurationUs[index]));
    if (reading.sampleValid[index]) {
      Serial.printf("Distance: %.2f cm\n", reading.rawDistanceCm[index]);
    } else {
      Serial.println(F("Distance: INVALID"));
    }
  }

  if (reading.status == UltrasonicStatus::kOk) {
    Serial.printf("Median distance: %.2f cm\n", reading.medianDistanceCm);
    Serial.printf("Test fill: %.1f%%\n", reading.testFillPercent);
    Serial.println(F("Sensor: OK"));
  } else {
    Serial.println(F("Distance: INVALID"));
    Serial.println(F("Fill: UNKNOWN"));
    Serial.println(F("Sensor: ERROR"));
    Serial.printf("Reason: %s | valid samples: %u/%u\n",
                  ultrasonicStatusName(reading.status),
                  static_cast<unsigned int>(reading.validSampleCount),
                  static_cast<unsigned int>(AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT));
  }
}

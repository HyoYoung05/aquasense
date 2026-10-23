#pragma once

#include <Arduino.h>

#include "config.h"

enum class UltrasonicStatus : uint8_t {
  kOk,
  kNoEcho,
  kOutOfRange,
  kInsufficientValidSamples,
  kCalibrationError,
};

struct UltrasonicReading {
  uint32_t echoDurationUs[AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT]{};
  float rawDistanceCm[AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT]{};
  bool sampleValid[AquaSenseConfig::ULTRASONIC_SAMPLE_COUNT]{};
  uint8_t validSampleCount = 0;
  float medianDistanceCm = NAN;
  float testFillPercent = NAN;
  UltrasonicStatus status = UltrasonicStatus::kNoEcho;
};

void ultrasonicBegin();
bool ultrasonicAlgorithmSelfTest();
UltrasonicReading readUltrasonic();
const char* ultrasonicStatusName(UltrasonicStatus status);
void printUltrasonicLiveSection(const UltrasonicReading& reading);
void printUltrasonicDiagnostics(const UltrasonicReading& reading,
                                uint32_t sequence);

#pragma once

#include <Arduino.h>
#include <math.h>

inline bool testCalibrationIsValid(const float emptyDistanceCm,
                                   const float fullDistanceCm) {
  return isfinite(emptyDistanceCm) && isfinite(fullDistanceCm) &&
         emptyDistanceCm > fullDistanceCm;
}

inline float calculateTestFillPercent(const float distanceCm,
                                      const float emptyDistanceCm,
                                      const float fullDistanceCm) {
  if (!isfinite(distanceCm) ||
      !testCalibrationIsValid(emptyDistanceCm, fullDistanceCm)) {
    return NAN;
  }
  const float percent =
      ((emptyDistanceCm - distanceCm) /
       (emptyDistanceCm - fullDistanceCm)) * 100.0F;
  if (percent < 0.0F) return 0.0F;
  if (percent > 100.0F) return 100.0F;
  return percent;
}

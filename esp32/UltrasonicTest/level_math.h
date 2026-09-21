#pragma once
#include <math.h>

inline float fillPercent(float distance, float empty, float full) {
  float percent = 100.0f * (empty - distance) / (empty - full);
  percent = fmaxf(0.0f, fminf(100.0f, percent));
  return roundf(percent * 10.0f) / 10.0f;
}
inline const char* levelStatus(float percent, float warning, float critical) {
  return percent >= critical ? "CRITICAL" : (percent >= warning ? "WARNING" : "NORMAL");
}

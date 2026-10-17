#pragma once
#include <Arduino.h>
#include "sensor_data.h"
void gasSensorBegin(SensorData& data);
void gasSensorUpdate(uint32_t nowMs, SensorData& data);

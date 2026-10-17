#pragma once
#include <Arduino.h>
#include "sensor_data.h"
void environmentSensorsBegin(SensorData& data);
void environmentSensorsUpdate(uint32_t nowMs, SensorData& data);

#pragma once
#include <Arduino.h>
#include "sensor_data.h"
void temperatureSensorBegin(SensorData& data);
void temperatureSensorUpdate(uint32_t nowMs, SensorData& data);

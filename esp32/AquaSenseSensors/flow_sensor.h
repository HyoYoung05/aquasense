#pragma once
#include <Arduino.h>
#include "sensor_data.h"
void flowSensorBegin(SensorData& data);
void flowSensorUpdate(uint32_t nowMs, SensorData& data);

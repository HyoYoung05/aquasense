#pragma once
#include <Arduino.h>
#include "sensor_data.h"
void turbiditySensorBegin(SensorData& data);
void turbiditySensorUpdate(uint32_t nowMs, SensorData& data);

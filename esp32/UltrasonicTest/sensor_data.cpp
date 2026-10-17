#include "sensor_data.h"
#include "calibration.h"

const char* sensorStateName(const SensorState state) {
  switch (state) {
    case SensorState::kDisabled: return "DISABLED";
    case SensorState::kUnavailable: return "UNAVAILABLE";
    case SensorState::kWarmingUp: return "WARMING_UP";
    case SensorState::kNotCalibrated: return "NOT_CALIBRATED";
    case SensorState::kValid: return "VALID";
    case SensorState::kError: return "ERROR";
  }
  return "ERROR";
}

void printSensorData(const SensorData& data) {
  Serial.print(F("[4A DS18B20] Wastewater temperature: "));
  if (data.wastewaterTemperatureValid) {
    Serial.printf("%.2f C", data.wastewaterTemperatureC);
  } else {
    Serial.print(F("N/A"));
  }
  Serial.printf(" | offset %.2f C | reference %s | %s\n",
                AquaSenseCalibration::DS18B20_TEMPERATURE_OFFSET_C,
                AquaSenseCalibration::DS18B20_REFERENCE_VALIDATED
                    ? "VALIDATED"
                    : "PENDING",
                sensorStateName(data.ds18b20State));

  Serial.print(F("[4B SEN0189] Raw ADC: "));
  if (data.turbidityRawValid) {
    Serial.printf("latest %u | filtered %u | pin %.3f V | source %.3f V",
                  data.turbidityLatestAdc, data.turbidityRawAdc,
                  data.turbidityAdcVoltage,
                  data.turbiditySourceVoltage);
  } else {
    Serial.print(F("N/A"));
  }
  Serial.print(F(" | NTU: "));
  if (data.turbidityNtuValid) Serial.printf("%.2f", data.turbidityNtu);
  else Serial.print(F("NOT CALIBRATED"));
  Serial.printf(" | %s\n", sensorStateName(data.turbidityState));

  Serial.print(F("[4C YF-S201] Pulses: "));
  if (!data.flowPulseValid) Serial.print(F("N/A"));
  else Serial.print(data.flowPulseCount);
  Serial.print(F(" | Frequency: "));
  if (data.flowValid) {
    Serial.printf("%.2f Hz | calibrated %.3f L/min | total %.3f L",
                  data.flowPulseFrequencyHz, data.flowRateLpm,
                  data.cumulativeFlowLiters);
  } else {
    Serial.print(F("N/A - FLOW CALIBRATION INCOMPLETE"));
  }
  Serial.printf(" | %s\n", sensorStateName(data.flowState));

  Serial.print(F("[4D ENS160] AQI / TVOC / eCO2: "));
  if (data.ens160Valid) {
    Serial.printf("%u / %u ppb / %u ppm", data.ens160Aqi,
                  data.ens160TvocPpb, data.ens160Eco2Ppm);
  } else {
    Serial.print(F("N/A"));
  }
  Serial.printf(" | %s\n", sensorStateName(data.ens160State));

  Serial.print(F("[4D AHT2x] Ambient temperature / humidity: "));
  if (data.aht2xValid) {
    Serial.printf("%.2f C / %.2f %%", data.ambientTemperatureC,
                  data.humidityPercent);
  } else {
    Serial.print(F("N/A"));
  }
  Serial.printf(" | %s\n", sensorStateName(data.aht2xState));

  Serial.print(F("[4E MQ-135] Raw ADC: "));
  if (data.mq135RawValid) {
    Serial.printf("latest %u | filtered %u | pin %.3f V | source %.3f V | PPM NOT CALIBRATED",
                  data.mq135LatestAdc, data.mq135RawAdc,
                  data.mq135AdcVoltage,
                  data.mq135SourceVoltage);
  } else {
    Serial.print(F("N/A | PPM NOT CALIBRATED"));
  }
  Serial.printf(" | %s\n", sensorStateName(data.mq135State));
}

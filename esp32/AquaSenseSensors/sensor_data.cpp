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
  Serial.println(F("[2] DS18B20 WATER TEMPERATURE"));
  Serial.print(F("Temperature: "));
  if (data.wastewaterTemperatureValid) {
    Serial.printf("%.2f C", data.wastewaterTemperatureC);
  } else {
    Serial.print(F("N/A"));
  }
  Serial.println();
  Serial.printf("Calibration offset: %.2f C\n",
                AquaSenseCalibration::DS18B20_TEMPERATURE_OFFSET_C);
  Serial.printf("Reference validation: %s\n",
                AquaSenseCalibration::DS18B20_REFERENCE_VALIDATED
                    ? "VALIDATED" : "PENDING");
  Serial.printf("Status: %s\n\n", sensorStateName(data.ds18b20State));

  Serial.println(F("[3] SEN0189 TURBIDITY"));
  if (data.turbidityRawValid) {
    Serial.printf("Raw ADC: %u\n", data.turbidityRawAdc);
    Serial.printf("Latest ADC: %u\n", data.turbidityLatestAdc);
    Serial.printf("GPIO34 ADC voltage: %.3f V\n", data.turbidityAdcVoltage);
    Serial.printf("Sensor AO voltage (estimated): %.3f V\n",
                  data.turbiditySourceVoltage);
  } else {
    Serial.println(F("Raw ADC: N/A"));
    Serial.println(F("GPIO34 ADC voltage: N/A"));
    Serial.println(F("Sensor AO voltage (estimated): N/A"));
  }
  if (data.turbidityNtuValid) {
    Serial.printf("Calibrated turbidity: %.2f NTU\n", data.turbidityNtu);
  } else {
    Serial.println(F("Calibrated turbidity: N/A (calibration required)"));
  }
  Serial.printf("Status: %s\n\n", sensorStateName(data.turbidityState));

  Serial.println(F("[4] YF-S201 WATER FLOW"));
  if (data.flowPulseValid) {
    Serial.printf("Pulses in latest interval: %lu\n",
                  static_cast<unsigned long>(data.flowPulseCount));
    Serial.printf("Pulse frequency: %.2f Hz\n", data.flowPulseFrequencyHz);
  } else {
    Serial.println(F("Pulses in latest interval: N/A"));
    Serial.println(F("Pulse frequency: N/A"));
  }
  if (data.flowValid) {
    Serial.printf("Calibrated flow rate: %.3f L/min\n", data.flowRateLpm);
    Serial.printf("Calibrated volume since boot: %.3f L\n",
                  data.cumulativeFlowLiters);
  } else {
    Serial.println(F("Calibrated flow rate: N/A (calibration required)"));
    Serial.println(F("Calibrated volume since boot: N/A"));
  }
  Serial.printf("Status: %s\n\n", sensorStateName(data.flowState));

  Serial.println(F("[5] ENS160 + AHT2x AIR QUALITY"));
  if (data.aht2xValid) {
    Serial.printf("Ambient temperature: %.2f C\n", data.ambientTemperatureC);
    Serial.printf("Relative humidity: %.2f %%RH\n", data.humidityPercent);
  } else {
    Serial.println(F("Ambient temperature: N/A"));
    Serial.println(F("Relative humidity: N/A"));
  }
  Serial.printf("AHT2x status: %s\n", sensorStateName(data.aht2xState));
  if (data.ens160Valid) {
    Serial.printf("ENS160 AQI: %u\n", data.ens160Aqi);
    Serial.printf("ENS160 TVOC: %u ppb\n", data.ens160TvocPpb);
    Serial.printf("ENS160 eCO2: %u ppm\n", data.ens160Eco2Ppm);
  } else {
    Serial.println(F("ENS160 AQI / TVOC / eCO2: N/A"));
  }
  Serial.printf("ENS160 status: %s\n\n", sensorStateName(data.ens160State));

  Serial.println(F("[6] MQ-135 GAS / ODOR"));
  if (data.mq135RawValid) {
    Serial.printf("Raw ADC: %u\n", data.mq135RawAdc);
    Serial.printf("Latest ADC: %u\n", data.mq135LatestAdc);
    Serial.printf("GPIO35 ADC voltage: %.3f V\n", data.mq135AdcVoltage);
    Serial.printf("Sensor AO voltage (estimated): %.3f V\n",
                  data.mq135SourceVoltage);
  } else {
    Serial.println(F("Raw ADC: N/A"));
    Serial.println(F("GPIO35 ADC voltage: N/A"));
    Serial.println(F("Sensor AO voltage (estimated): N/A"));
  }
  Serial.println(F("Gas concentration: UNCALIBRATED (no ppm estimate)"));
  Serial.printf("Status: %s\n", sensorStateName(data.mq135State));
}

# AQUASENSE+ sensor calibration and threshold register

Hardware Phase 6 status: **PARTIAL — physical sensors and reference equipment unavailable**
Last updated: 2026-10-17

This file is the calibration record for the six physical sensor modules. No
table below contains fabricated measurements. Firmware constants are
centralized in `esp32/UltrasonicTest/calibration.h`; grease-trap geometry and
user-facing level thresholds remain authoritative in MySQL.

## Authority and current configuration

- ESP32: acquisition, filtering, calibrated conversion only after approval,
  sensor validity, and telemetry transport.
- PHP/MySQL: authoritative HC-SR04 empty/full geometry, LOW/MEDIUM/HIGH/
  CRITICAL/OVERFLOW state, alert creation, history, and user-facing status.
- Firmware does not send a local fill percentage or local alert status, so it
  cannot silently disagree with server thresholds.
- Trap 12 currently stores 30.00 cm empty and 5.00 cm full as development
  values. They are not a physical grease-trap calibration.
- Trap 12 currently stores LOW 20%, MEDIUM 50%, HIGH 75%, and CRITICAL 90%; the
  global OVERFLOW setting is 100%. These are existing development settings.
  The paper's 80% example was not applied: 80% currently falls in HIGH because
  the saved HIGH threshold is 75%.
- The configured Emulsion Warning threshold is 40 °C and is evaluated by PHP.
- The stored 500 NTU and 0–10 L/min limits require field approval.
  `turbidity_alert_enabled` and `flow_alert_enabled` default to `0`, preventing
  those values from creating alerts until calibration and threshold approval.

## Calibration status

| Measurement | Current firmware behavior | Status |
| --- | --- | --- |
| HC-SR04 fill | Sends valid filtered distance; PHP calculates fill | Physical empty/full calibration pending |
| DS18B20 wastewater temperature | Celsius with 0.00 °C offset | Reference comparison pending |
| SEN0189 turbidity | Latest/filtered raw ADC and voltage diagnostics; no NTU | **NTU CALIBRATION INCOMPLETE** |
| YF-S201 flow | Raw interval pulse count; no L/min from provisional factor | **FLOW CALIBRATION INCOMPLETE** |
| ENS160 | Library AQI/TVOC/eCO2 after valid status; AHT2x compensation supported | Response/stability validation pending |
| AHT2x | Separate ambient temperature and humidity | Reference comparison pending |
| MQ-135 | Latest/filtered raw ADC and voltage after warm-up; no ppm | **MQ-135 PPM CALIBRATION INCOMPLETE** |

The actual schema uses nullable `gas_value` for a future calibrated value and
`gas_raw_adc` for MQ-135 diagnostics; it does not contain a column named
`gas_ppm`. Phase 6 does not rename or populate `gas_value` without evidence.

## HC-SR04 physical record

Mount the sensor in its final position. Measure empty and maximum safe/full
distances, then collect repeated echo duration, raw distance, median distance,
calculated depth percentage, and spread at EMPTY, LOW, MID, HIGH, and FULL.

```text
fill % = ((empty distance - measured distance)
          / (empty distance - full distance)) × 100
```

Clamp the result to 0–100%. Record whether water, grease, foam, or an uneven
surface changes echo reliability. This is depth percentage; it is only a volume
approximation when trap cross-section is not uniform.

| Condition | Reference % | Echo µs | Raw cm | Median cm | Calculated % | Spread | Surface |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| EMPTY | Pending | Pending | Pending | Pending | Pending | Pending | Pending |
| LOW | Pending | Pending | Pending | Pending | Pending | Pending | Pending |
| MID | Pending | Pending | Pending | Pending | Pending | Pending | Pending |
| HIGH | Pending | Pending | Pending | Pending | Pending | Pending | Pending |
| FULL safe | Pending | Pending | Pending | Pending | Pending | Pending | Pending |

## DS18B20 reference record

Use safe water temperatures and a known reference thermometer. Apply an offset
only when repeated points show a consistent systematic error.

| Condition | Reference °C | DS18B20 °C | Error °C | Repeats/spread |
| --- | ---: | ---: | ---: | --- |
| Room temperature | Pending | Pending | Pending | Pending |
| Moderately warm | Pending | Pending | Pending | Pending |
| Near warning region | Pending | Pending | Pending | Pending |

No temperature hysteresis was added without evidence of alert chatter. The
backend boundary test confirms Emulsion Warning at the configured 40 °C.

## SEN0189 NTU record

Collect latest ADC, filtered ADC, ESP32 pin voltage, reconstructed sensor output
voltage, sample description, and a traceable reference NTU. Fit and record the
approved curve and applicable range. Until then, the firmware transmits only
raw ADC and the backend's NTU field remains null.

| Sample | Reference NTU | Latest ADC | Filtered ADC | Pin V | Source V | Repeat spread |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Clear reference | Pending | Pending | Pending | Pending | Pending | Pending |
| Slightly cloudy | Pending | Pending | Pending | Pending | Pending | Pending |
| More turbid | Pending | Pending | Pending | Pending | Pending | Pending |

## YF-S201 known-volume record

For each trial, reset the count, pass a measured volume, record pulses and
elapsed time, and calculate `pulses_per_liter = pulses / liters`. Approve a
factor only if repeated trials at practical flow rates are consistent.

| Trial | Volume L | Pulses | Elapsed s | Pulses/L | Flow condition | Error |
| --- | ---: | ---: | ---: | ---: | --- | ---: |
| 1 | Pending | Pending | Pending | Pending | Pending | Pending |
| 2 | Pending | Pending | Pending | Pending | Pending | Pending |
| 3 | Pending | Pending | Pending | Pending | Pending | Pending |

The firmware's 450 pulses/L value is a disabled development reference. Pulse
counts remain available, but L/min and cumulative liters remain unavailable
until `FLOW_PHYSICALLY_CALIBRATED` is deliberately enabled.

## ENS160+AHT2x validation record

Compare AHT2x ambient temperature/humidity with a reasonable reference. Verify
stable ENS160 status and plausible AQI/TVOC/eCO2 response after warm-up. The
DFRobot library receives valid AHT2x temperature/humidity through
`setTempAndHum`; invalid or missing AHT2x data is not supplied as compensation.

| Test | Reference | Sensor output | Warm-up/status | Repeat stability |
| --- | --- | --- | --- | --- |
| Ambient temperature | Pending | Pending | Pending | Pending |
| Relative humidity | Pending | Pending | Pending | Pending |
| ENS160 clean/reference response | Pending | Pending | Pending | Pending |
| ENS160 controlled response | Pending | Pending | Pending | Pending |

## MQ-135 baseline record

After the installed module's documented stabilization period, record clean-air
latest/filtered ADC, voltages, duration, and drift. A ppm conversion also needs
the actual module load resistance, target-gas curve, and a defensible reference
method. A single analog reading cannot identify a gas.

| Environment | Warm-up | Latest ADC | Filtered ADC | Pin V | Source V | Drift |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| Clean/reference | Pending | Pending | Pending | Pending | Pending | Pending |

The implemented notification layer is the in-app/database alert workflow.
External SMS and push delivery are not configured, so Phase 6 does not claim
those channels were verified.

## Acceptance path still pending

After physical calibration, use controlled conditions or the existing
development simulator to exercise every saved level boundary and the 40 °C
Emulsion Warning. Confirm the generated alert links to its assignment, reading,
type, severity, status, threshold, value, and timestamps, then appears on the
website and Flutter app. Do not physically overflow the prototype. No approved
turbidity, flow, ENS160, or MQ-135 business thresholds currently exist beyond
the disabled development placeholders described above.

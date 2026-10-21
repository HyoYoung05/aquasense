# AQUASENSE+ official ESP32 pinout

The revised capstone paper is authoritative. Older proposed maps are obsolete.

| Physical module | Signal | ESP32 pin | Electrical requirement | 2026-10-22 COM7 state |
| --- | --- | ---: | --- | --- |
| HC-SR04 | TRIG | GPIO5 | Output; module uses 5 V | Live distance sent; intermittent no-echo remains |
| HC-SR04 | ECHO | GPIO18 | 1 kOhm / 2 kOhm divider; never direct 5 V | Active; meter verification still required |
| DS18B20 | DATA | GPIO4 | 3.3 V and about 10 kOhm pull-up | Valid temperature; internal pull-up supplements the required external resistor |
| SEN0189 | ANALOG | GPIO34 | 10 kOhm / 20 kOhm divider; measure first | Raw ADC sent; intermittent rail fault recovered |
| YF-S201 | SIGNAL | GPIO19 | 1 kOhm / 2 kOhm divider; measure first | Disabled until GPIO19/protection is verified |
| ENS160+AHT2x | SDA | GPIO21 | Shared 3.3 V I2C bus | AHT2x `0x38` detected; ENS160 absent; intermittent I2C faults |
| ENS160+AHT2x | SCL | GPIO22 | Shared 3.3 V I2C bus | AHT2x `0x38` detected; ENS160 absent; intermittent I2C faults |
| ENS160+AHT2x | ENS160 INT | GPIO26 | Optional if exposed | Polling default; ENS160 disabled |
| MQ-135 | ANALOG | GPIO35 | 10 kOhm / 20 kOhm divider; measure first | Raw ADC sent after warm-up; noisy/low-rail samples remain |

GPIO34 and GPIO35 are input-only ADC1 pins. All modules and the ESP32 must
share ground. No protected input may exceed 3.3 V at an ESP32 pin.

ENS160 and AHT2x are two ICs on one physical module, so AHT2x is not a seventh
module. Figure 14 labels GPIO4 as DHT22, while the revised description specifies
DS18B20. Firmware uses DS18B20 and never fabricates humidity; AHT2x is the
humidity source.

Phase 8 retains this locked map. Connected-sensor evidence now exists on COM7,
but the protection voltages, final power system, placements, calibration, and
several unstable paths remain unverified. The approved design contains no
microSD, OLED, or buzzer. See `FINAL_ASSEMBLY.md` and the
[connected-sensor report](../esp32/PHYSICAL-VERIFICATION-2026-10-22.md).

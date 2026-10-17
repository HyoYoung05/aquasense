# AQUASENSE+ official ESP32 pinout

The revised capstone paper is authoritative. Older proposed maps are obsolete.

| Physical module | Signal | ESP32 pin | Electrical requirement | Phase 4 state |
| --- | --- | ---: | --- | --- |
| HC-SR04 | TRIG | GPIO5 | Output; module uses 5 V | Active; sensor absent |
| HC-SR04 | ECHO | GPIO18 | 1 kOhm / 2 kOhm divider; never direct 5 V | Active; sensor absent |
| DS18B20 | DATA | GPIO4 | 3.3 V and about 10 kOhm pull-up | Driver ready; disabled |
| SEN0189 | ANALOG | GPIO34 | 10 kOhm / 20 kOhm divider; measure first | Driver ready; disabled |
| YF-S201 | SIGNAL | GPIO19 | 1 kOhm / 2 kOhm divider; measure first | ISR ready; disabled |
| ENS160+AHT2x | SDA | GPIO21 | Shared 3.3 V I2C bus | Drivers ready; address unset |
| ENS160+AHT2x | SCL | GPIO22 | Shared 3.3 V I2C bus | Drivers ready; address unset |
| ENS160+AHT2x | ENS160 INT | GPIO26 | Optional if exposed | Polling default; disabled |
| MQ-135 | ANALOG | GPIO35 | 10 kOhm / 20 kOhm divider; measure first | Driver ready; disabled |

GPIO34 and GPIO35 are input-only ADC1 pins. All modules and the ESP32 must
share ground. No protected input may exceed 3.3 V at an ESP32 pin.

ENS160 and AHT2x are two ICs on one physical module, so AHT2x is not a seventh
module. Figure 14 labels GPIO4 as DHT22, while the revised description specifies
DS18B20. Firmware uses DS18B20 and never fabricates humidity; AHT2x is the
humidity source.

Phase 4 uses USB/bench power. Phase 5 validates the final power system. The
revised design contains no microSD, OLED, or buzzer.

# AQUASENSE+ final power architecture

Hardware Phase 8 final status: **PARTIAL — physical supply and sensors unavailable**
Last updated: 2026-10-18

The latest capstone paper specifies an enclosed, regulated AC-to-DC supply
feeding safe low-voltage 5 V and 3.3 V rails. Phase 5 work must stay entirely
on the low-voltage DC side. Exposed 220-240 VAC, breadboard mains wiring, and
mains routing through the ESP32 area are prohibited.

## Approved primary architecture

```text
Enclosed regulated AC-to-DC adapter
              |
     safe low-voltage DC
              |
      +-------+-------+
      |               |
     5 V             3.3 V
      |               |
 ESP32 VIN          DS18B20
 HC-SR04            ENS160+AHT2x
 YF-S201
 SEN0189
 MQ-135
      |
      +---- one common low-voltage ground ----+
```

If the selected adapter provides only regulated 5 V, the ESP32 3.3 V output
may supply DS18B20 and ENS160+AHT2x only after the installed modules' combined
current and the actual DevKit regulator capacity are verified. If an external
3.3 V regulator is used, it must share ground but must not be tied to the
ESP32's 3.3 V output unless the approved circuit explicitly permits it.

## Supply record

| Item | Current record |
| --- | --- |
| Manufacturer/model | Not available; must be recorded from the actual adapter |
| Rated AC input | Not available |
| Rated DC output | Not available |
| Rated maximum current | Not available |
| Measured unloaded 5 V rail | Not measured |
| Measured loaded 5 V rail | Not measured |
| 3.3 V source | Undecided pending actual supply and current review |
| Polarity | Not physically confirmed |
| Common ground | Required by design; not physically confirmed |
| Long-duration result | Not run |

No unverified supply may be connected to VIN. Confirm polarity and measure the
unloaded rail first, switch power off, recheck wiring, and only then connect the
ESP32.

## Distribution and input protection

| Device | Supply | Protected signal |
| --- | --- | --- |
| ESP32 DevKit V1 | Regulated 5 V to VIN | — |
| HC-SR04 | 5 V | ECHO through 1 kOhm / 2 kOhm divider to GPIO18 |
| YF-S201 | 5 V | SIGNAL through 1 kOhm / 2 kOhm divider to GPIO19 |
| SEN0189 | 5 V-side module per paper; inspect actual board | ANALOG through 10 kOhm / 20 kOhm divider to GPIO34 |
| MQ-135 | 5 V rail, never an ESP32 GPIO | ANALOG through 10 kOhm / 20 kOhm divider to GPIO35 |
| DS18B20 | 3.3 V | DATA GPIO4 with documented pull-up |
| ENS160+AHT2x | 3.3 V after actual board inspection | GPIO21/GPIO22 I2C |

Measure every divided signal before attaching it to the ESP32. Keep the ESP32
input at or below 3.3 V. The MQ-135 heater is the largest expected continuous
sensor load and must be checked for rail sag, heat, Wi-Fi instability, ADC
noise, and brownouts.

## USB and external power

The current verification uses USB development power only. The final external
DC and USB arrangement depends on the actual ESP32 DevKit power-path design.
Do not assume USB 5 V and external VIN can be connected together safely. For
standalone validation, use the intended external supply. If serial is needed,
use an electrically safe method established for the actual board and document
it before testing.

## Firmware diagnostics

Firmware 0.5.0 reports reset reason, declared power mode, minimum free heap,
Wi-Fi connection/reconnection/disconnection counts, and accepted/failed
telemetry counts. It states that rail voltage is not monitored. These counters
help detect symptoms; they are not substitutes for multimeter measurements.

## Required physical sequence

1. Identify and rate the enclosed adapter.
2. Measure polarity and unloaded rails without the ESP32.
3. Run ESP32 alone for several minutes.
4. Add HC-SR04, DS18B20, SEN0189, YF-S201, ENS160+AHT2x, then MQ-135 one at a
   time, measuring the rail and checking every earlier subsystem after each.
5. Run the full load, ten controlled power cycles, a DC interruption/recovery
   check, and a several-hour stability test.
6. Compare database records before/after restart and reject fake zeros, startup
   spikes, malformed values, or lost recovery.

Record loaded/unloaded voltage, reset reasons, temperatures, dropouts, ADC
variation, I2C errors, pulse behavior, Wi-Fi reconnects, and API errors. Do not
claim 24/7 reliability from a short bench run.

## Explicit exclusions and limitations

- MT3608, TP4056, arbitrary Li-ion wiring, microSD, OLED, and buzzer are not in
  the approved Phase 5 architecture.
- **Backup battery architecture is not finalized in the current schematic.**
  No battery, charger, protection, switchover, regulator, or backfeed circuit is
  implemented. Phase 5 PASS does not depend on an unspecified backup circuit.
- Sensor calibration and final thresholds belong to Phase 6.
- Physical Phase 4 remains on hold until the sensors are available; therefore
  full-load Phase 5 acceptance is also blocked.

## Phase 8 installation result

No final adapter, 5 V/3.3 V distribution, enclosure, finished harness, or
loaded-rail measurement is available. The common ground, divider outputs,
polarity, MQ-135 heater load, moisture separation, and controlled final-supply
restart therefore remain unverified. Only the USB-powered ESP32 diagnostic has
physical evidence. The final assembly must follow `FINAL_ASSEMBLY.md`; it must
not return to MT3608/Li-ion wiring or introduce a backup circuit without an
approved schematic.

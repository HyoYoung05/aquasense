# ESP32 Hardware Phase 6 test report

Date: 2026-10-17
Port: COM6 only
Firmware: 0.6.0
Status: **PARTIAL**

## Implemented and verified without sensors

- Inspected the firmware, PHP ingestion, database schema, trap configuration,
  alert engine, website monitoring, and Flutter monitoring before changing
  calibration behavior.
- Centralized firmware calibration state in `calibration.h`.
- Preserved every revised GPIO assignment and all Phase 1–5 safety behavior.
- Kept the backend authoritative for fill percentage and user-facing level
  state. Firmware transmits distance and does not transmit a competing state.
- Applied the zero DS18B20 offset and documented that reference validation is
  pending.
- Kept SEN0189 ADC/voltage separate from NTU. An NTU curve cannot run unless
  its approval flag and finite coefficients are supplied.
- Separated YF-S201 pulse evidence from calibrated L/min. The provisional
  450-pulse/L reference no longer produces telemetry engineering units.
- Preserved ENS160 warm-up/validity handling and valid AHT2x environmental
  compensation.
- Kept MQ-135 raw-only after warm-up, with no gas ppm claim.
- Added migration 012. Turbidity and flow alert gates default off until both
  physical calibration and threshold approval are complete.
- Confirmed the existing backend 40 °C Emulsion Warning remains centralized.
- Confirmed the current notification path is the database/in-app website and
  Flutter alert workflow; no SMS or push provider is configured to verify.

## Current threshold audit

Trap 12 currently has development geometry 30.00/5.00 cm and level boundaries
20/50/75/90%, with global overflow at 100%. The paper's example 80% condition
was not copied over the saved settings; it currently falls inside HIGH. The
stored 500 NTU and 0–10 L/min limits are retained for review but disabled for
alert generation.

## Build and runtime evidence

- Arduino build passed: 973,988 bytes flash and 50,208 bytes static RAM.
- Warnings came from installed DFRobot ENS160 and OneWire libraries, not the
  AQUASENSE+ source.
- Firmware was uploaded only to COM6; ESP32 flash hashes verified.
- Public placeholder and ignored private builds both passed with the same
  973,988-byte flash / 50,208-byte RAM result.
- A 26-second sensor-absent run reported firmware 0.6.0, `POWER_ON`, algorithm
  self-test PASS, one Wi-Fi connection, zero disconnects, no reboot, stable
  minimum heap of 220,008 bytes, invalid HC-SR04 echo, all absent sensors as
  disabled/unavailable, and zero fake telemetry attempts.
- AQS-001 remained unchanged at 335 readings, latest reading ID 409, and its
  previous `last_seen_at` value.
- Regression results: 35 device/API checks, 36 alert checks, 53 mobile API
  checks, 19 fresh-install checks, and 52 hardening checks passed. PHP lint and
  `git diff --check` passed. Flutter analysis found no issues and all 32 focused
  monitoring/navigation tests passed; dependency tooling emitted only the
  existing desktop-plugin metadata warnings.

## Physical blockers

No physical sensor, final trap, reference thermometer, NTU reference,
known-volume flow apparatus, environmental reference, MQ-135 baseline setup,
or approved external power setup is available. Therefore no calibration point,
offset, curve, pulses/L value, baseline, physical alert, or end-to-end physical
result is claimed. Complete the blank evidence tables in `docs/CALIBRATION.md`
when the hardware is available.

Phase 6 remains **PARTIAL**. Do not proceed as though calibration is complete.

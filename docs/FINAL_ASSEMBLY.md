# AQUASENSE+ final assembly and installation record

Hardware Phase 8 status: **PARTIAL — connected bench hardware is not a verified final assembly**

Last updated: 2026-10-22

This is the final ESP32 hardware phase. There is no automatic Phase 9. The
locked electrical design, firmware 0.7.1, server-derived thresholds, Phase 6
calibration gates, and Phase 7 recovery behavior remain authoritative. The
2026-10-22 follow-up adds connected bench evidence without claiming final
mounting, calibration, power, enclosure, or field acceptance.

This document separates the approved installation plan from physically
verified evidence. A planned connection or paper placement is not marked as an
installed component.

## Final hardware inventory

| Item | Evidence-based state | Evidence / blocker |
| --- | --- | --- |
| ESP32 DevKit V1 | **PRESENT** | CH340 ESP32 detected, compiled, and hash-verified on COM7 |
| HC-SR04 | **CONNECTED / PARTIAL** | Live distance reached MySQL; intermittent no-echo and unstable distance remain |
| DS18B20 waterproof probe | **CONNECTED / COMMUNICATING** | Valid GPIO4 temperature reached MySQL; reference calibration pending |
| SEN0189 | **CONNECTED / PARTIAL** | Raw GPIO34 ADC reached MySQL; rail fault and NTU calibration remain |
| YF-S201 | **CONNECTED BY USER / NOT VERIFIED** | Official GPIO19 and protection are not yet electrically confirmed; firmware gate remains off |
| ENS160+AHT2x combination board | **PARTIAL** | AHT2x is detected at `0x38`; ENS160 is not detected at `0x52` or `0x53` |
| MQ-135 | **CONNECTED / PARTIAL** | Warmed raw GPIO35 samples reached MySQL; noisy/low-rail behavior and calibration remain |
| Approved regulated power supply | **NOT AVAILABLE / UNVERIFIED** | Model, ratings, polarity, and loaded rails are unknown |
| Voltage-divider and pull-up components | **NOT METER-VERIFIED** | Runtime signals exist, but installed resistor values and worst-case GPIO voltages still require measurement |
| Final grease-trap prototype | **NOT AVAILABLE** | No final chamber/plumbing evidence |
| Electronics enclosure | **NOT AVAILABLE** | No enclosure or IP rating evidence |
| Final wiring and connectors | **NOT AVAILABLE** | No finished harness to inspect |
| Physical latch mechanism | **NOT SPECIFIED / NOT AVAILABLE** | Paper requirement has no approved hardware design |
| Latch actuator and driver | **NOT SPECIFIED / NOT AVAILABLE** | No actuator, voltage/current rating, GPIO, or driver circuit |
| Oil-diversion mechanism | **NOT SPECIFIED / NOT AVAILABLE** | No approved mechanical or electrical implementation |

Connected bench evidence is summarized in the
[2026-10-22 report](../esp32/PHYSICAL-VERIFICATION-2026-10-22.md). No bench
connection may be treated as final installation evidence until it is inspected,
metered, calibrated, mounted, and retested under final power.

## Locked wiring and placement table

These are approved target connections and paper placements. Their final
installation state is **not verified**.

| Component | Power | Ground | Signal | GPIO | Divider / pull-up | Approved physical location |
| --- | --- | --- | --- | ---: | --- | --- |
| HC-SR04 | 5 V | Common low-voltage ground | TRIG | 5 | None on TRIG | Chamber 1 lid, facing the monitored surface |
| HC-SR04 | 5 V | Common low-voltage ground | ECHO | 18 | 1 kOhm / 2 kOhm divider | Chamber 1 lid |
| DS18B20 | 3.3 V | Common low-voltage ground | DATA | 4 | Approximately 10 kOhm pull-up to 3.3 V per revised paper | Representative wastewater location; exact mount remains unapproved |
| YF-S201 | 5 V | Common low-voltage ground | Pulse signal | 19 | 1 kOhm / 2 kOhm divider | Inlet pipe, arrow aligned with flow |
| SEN0189 | Module supply shown by approved design; verify actual board | Common low-voltage ground | Analog output | 34 | 10 kOhm / 20 kOhm divider | Chamber 3 wall |
| ENS160+AHT2x | 3.3 V after board inspection | Common low-voltage ground | SDA / SCL | 21 / 22 | I2C board pull-ups must be inspected | Chamber 1 lid, exposed to intended air but protected from liquid |
| ENS160 | 3.3 V | Common low-voltage ground | Optional INT | 26 | Use only if supported by installed board/library | Chamber 1 lid |
| MQ-135 | 5 V | Common low-voltage ground | Analog output | 35 | 10 kOhm / 20 kOhm divider | Chamber 1 lid, ventilated and protected from liquid |

GPIO34 and GPIO35 are input-only ADC1 pins. Every divided ESP32 input must be
measured at or below 3.3 V before connection. ENS160 and AHT2x are two ICs on
one physical combination module, not seven sensor modules.

## Final mechanical and moisture rules

- Secure the ESP32 and exposed electronics in a serviceable enclosure separated
  from wastewater, oil, grease, splash, condensation, and wet walls.
- Do not claim an enclosure IP rating without its manufacturer marking or
  certificate.
- Protect cable entries, use strain relief, minimize exposed copper, and replace
  loose jumper wiring with mechanically secure connections where practical.
- Keep the HC-SR04 acoustic path open. A standard module is not assumed to be
  waterproof; protect its board without covering the transducers.
- Immerse only the waterproof portion of the DS18B20 probe. Seal its cable joint,
  pull-up, and ESP32-side connections away from liquid.
- Keep SEN0189 electronics, ENS160+AHT2x, and MQ-135 out of direct liquid while
  exposing only their intended sensing areas.
- Keep the MQ-135 heater clear of plastic, insulation, loose wiring, and other
  temperature-sensitive parts.
- Route analog, I2C, flow-pulse, and power wiring separately where practical.
- Install YF-S201 in the indicated flow direction with secure, leak-free fittings.

## Final low-voltage assembly sequence

1. Keep all power disconnected and keep exposed 220–240 V mains outside the wet
   prototype area. Use only an enclosed, isolated supply and work on its
   low-voltage side.
2. Record the actual adapter manufacturer, model, input/output ratings, polarity,
   and maximum current.
3. Inspect for shorts and measure unloaded 5 V and 3.3 V rails.
4. Verify common ground and each divider/pull-up with power removed.
5. Power the ESP32 alone, then add HC-SR04, DS18B20, SEN0189, YF-S201,
   ENS160+AHT2x, and MQ-135 one at a time. Recheck the earlier subsystems after
   each addition.
6. Measure every divided signal before attaching it to an ESP32 GPIO.
7. Inspect polarity, dry electronics, sensor orientation, strain relief, and
   plumbing before closing the enclosure.
8. Perform controlled power-up and confirm automatic boot, sensor initialization,
   Wi-Fi, API access, and telemetry without a Serial command.

Do not connect USB and external VIN simultaneously unless the actual DevKit
power path has been inspected and the arrangement is known to be safe.

## Installation acceptance still required

- Mount and remeasure HC-SR04 empty, safe-full, and intermediate distances.
- Compare DS18B20 with a known thermometer and safely validate the backend 40 °C
  Emulsion Warning boundary.
- Calibrate SEN0189 with traceable NTU references or retain raw-only status.
- Verify YF-S201 with measured volumes and record installed error.
- Require stable ENS160 and AHT2x detection and plausible safe-environment response.
- Record MQ-135 warm-up and clean-air baseline; retain raw-relative status unless
  a defensible gas calibration is approved.
- Verify real telemetry through PHP/MySQL, website, and Flutter without fake
  values, then repeat Wi-Fi, server, sensor-failure, queue-drain, and power-cycle
  recovery tests.
- Run safe threshold simulations, concurrent web/mobile load, and the longest
  practical fully assembled reliability test.

The existing development values are not field acceptance values: trap 12 uses
30 cm empty, 5 cm full, and 20/50/75/90/100% level boundaries. Turbidity and
flow alerts remain disabled pending calibration and approval. MQ-135 remains
raw-only. SMS and push providers are not configured.

## Actuation and backup-power gaps

**LATCH CONTROL: NOT IMPLEMENTED — HARDWARE SPECIFICATION MISSING.** No approved
actuator, voltage/current requirement, control GPIO, driver/isolation circuit,
mechanical latch, or fail-safe state exists. Phase 8 does not invent a servo,
relay, solenoid, motor, or MOSFET circuit.

**OIL DIVERSION: NOT IMPLEMENTED.** Repository oil-surrender and Hybrid
Verification workflows are business records, not physical diversion.

**AUTOMATED GREASE EXTRACTION: NOT IMPLEMENTED.** No physical implementation or
explicit extraction simulation was found. Development telemetry simulation must
not be presented as robotic extraction.

**BACKUP BATTERY: NOT IMPLEMENTED — CIRCUIT NOT DEFINED.** The approved design
does not specify a battery, charger, protection, switchover, regulator, or
backfeed-safe circuit. The RAM queue cannot survive full power loss.

## Final physical inspection checklist

Every item remains unchecked because the final assembly is unavailable.

- [ ] ESP32 secured in a serviceable enclosure
- [ ] Electronics separated from liquid, grease, splash, and condensation
- [ ] Cable entries protected and cables strain-relieved
- [ ] No loose power connection or exposed unsafe mains
- [ ] Common low-voltage ground verified
- [ ] Divider and pull-up components installed and measured
- [ ] HC-SR04 rigidly aligned with an unobstructed acoustic path
- [ ] DS18B20 waterproof probe installed without immersing dry-side joints
- [ ] SEN0189 installed at Chamber 3
- [ ] YF-S201 oriented correctly and plumbing leak-free
- [ ] ENS160+AHT2x ventilated but protected from liquid
- [ ] MQ-135 protected from liquid with safe heater clearance
- [ ] Final supply, polarity, 5 V rail, and 3.3 V rail verified
- [ ] Enclosure remains dry, ventilated where required, and serviceable

## Final sensor checklist

- [ ] HC-SR04 operational and final geometry calibrated
- [ ] DS18B20 operational and safe 40 °C workflow verified
- [ ] SEN0189 operational with honest raw/NTU status
- [ ] YF-S201 operational with installed known-volume calibration
- [ ] ENS160 operational after stable repeated detection
- [ ] AHT2x operational independently of ENS160
- [ ] MQ-135 operational after warm-up with honest raw/calibration status

## Final software checklist

- [x] PHP health route and local server/database connection
- [x] Device authentication, telemetry validation, and idempotency
- [x] MySQL schema and nullable sensor-field semantics
- [x] Website monitoring and backend-authoritative alerts
- [x] Flutter monitoring, device status, and stale-data handling
- [x] Controlled Wi-Fi recovery logic and bounded RAM queue
- [x] Invalid readings omitted instead of converted to fake zeros
- [ ] Final-location router and Wi-Fi coverage
- [ ] Valid physical telemetry through PHP and MySQL
- [ ] Queue fill/drain during a real server outage
- [ ] Final-power automatic restart and telemetry recovery
- [ ] External SMS and push delivery

## Final end-to-end checklist

- [ ] Physical sensor change detected
- [ ] ESP32 reading valid
- [ ] Calibrated value correct where calibration is approved
- [ ] Telemetry transmitted and accepted by PHP
- [ ] MySQL stores the real packet against the correct trap
- [ ] Backend evaluates the approved threshold
- [ ] Website and Flutter display the same fresh result
- [ ] Safe alert condition creates the correct alert
- [ ] External notification delivered where a provider is configured

## Paper-versus-prototype gap audit

| Requirement | Final classification | Evidence / blocker |
| --- | --- | --- |
| Grease-level monitoring | PARTIALLY IMPLEMENTED | Driver, filtering, API, database, and UI exist; sensor/final mounting absent |
| Overflow detection | PARTIALLY IMPLEMENTED | Backend thresholds and safe simulation work; no installed HC-SR04 workflow |
| Temperature/emulsion warning | PARTIALLY IMPLEMENTED | DS18B20 driver and 40 °C backend boundary exist; physical probe absent |
| Turbidity monitoring | PARTIALLY IMPLEMENTED | Raw path exists; sensor and NTU calibration absent |
| Flow monitoring | PARTIALLY IMPLEMENTED | ISR/raw pulse path exists; sensor and installed calibration absent |
| Air-quality monitoring | PARTIALLY IMPLEMENTED | ENS160 fields/driver exist; combination board absent |
| Gas/odor monitoring | PARTIALLY IMPLEMENTED | Separate schema/UI semantics and MQ-135 raw path exist; physical/calibrated values absent |
| Wi-Fi telemetry | PARTIALLY IMPLEMENTED | Sensor-absent COM6 recovery works; real final-location telemetry absent |
| MySQL logging | IMPLEMENTED | Authenticated PHP ingestion, validation, idempotency, and history pass tests |
| Web dashboard | IMPLEMENTED | Monitoring, status, alerts, history, and reports pass tests |
| Flutter monitoring | IMPLEMENTED | Static analysis and all 113 tests pass; real physical acceptance remains pending |
| SMS/push notifications | NOT IMPLEMENTED | No external provider is configured |
| Oil-water diversion | NOT IMPLEMENTED | No approved mechanism or circuit |
| Latch control | NOT SPECIFIED ENOUGH TO IMPLEMENT | No actuator, driver, pin, power, or fail-safe design |
| Automated grease extraction | NOT IMPLEMENTED | No physical system or explicit extraction simulation found |
| Oil surrender/photo verification | IMPLEMENTED | Owner submission, protected evidence, and Hybrid Verification pass tests |
| Admin verification | IMPLEMENTED | Role-protected review and audit workflows pass tests |
| Rice/reward logging | IMPLEMENTED | Rules, incentive transactions, distribution, and ledger pass tests |
| Backup power | NOT SPECIFIED ENOUGH TO IMPLEMENT | Paper mention has no approved charger/protection/switchover design |

Development telemetry and alert records may be **SIMULATED** when explicitly
labelled. No physical actuation, extraction, or sensor acceptance is classified
as simulated merely to make the final status appear complete.

## Final readiness decision

- Final assembled bench demo: **NO**
- Capstone hardware demo: **NO**
- Controlled field test: **NO**
- Firmware/API/database/UI software demonstration: **YES, using clearly labelled
  development or simulated data only**

Phase 8 remains **PARTIAL** until the physical inventory, final power supply,
assembly, calibration, full telemetry path, alert workflow, recovery regression,
and long-run tests are completed. The latch/diversion requirement also remains an
external paper/hardware blocker. There is no Phase 9.

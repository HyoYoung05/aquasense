# Gas warm-up and reset indication

App 0.9.40+60 and firmware 0.8.20 use a one-minute Gas warm-up. Other sensor
displays and level alerts retain their existing behavior.
The Gas warm-up timer starts during firmware startup, before Wi-Fi connects.

The old API returns the last sensor record after a reset, so the app cannot
reliably distinguish a running device from one that just restarted. The updated
heartbeat carries a random per-boot ID, Gas state, remaining warm-up and valid
Gas raw ADC. The server stores this independently in `devices.gas_runtime`.
Owner monitoring returns it only through the existing owner-scoped device query.
The app uses this runtime instead of a historical Gas measurement.

During warm-up, Gas shows **Sensor warming up**. When the one-minute deadline
passes without a current reading, it shows **Please check wiring of sensor**.
A ready heartbeat replaces that message with actual raw ADC, even when sensor
telemetry ingestion is blocked by a separate ultrasonic problem. Historical
readings are retained. Repeated heartbeats do not extend the same boot's deadline.
Heartbeat state older than 90 seconds is shown as **Waiting for Gas status**.

## Production activation

1. Back up the database and the PHP files being replaced.
2. If migration 018 is not installed, apply
   `database/migrations/018-partial-sensor-telemetry.sql` once.
3. Apply `database/migrations/019-gas-runtime.sql` once. It adds a nullable JSON
   column; apply it before uploading the new PHP code.
4. Upload the prepared update package's PHP files, retaining the host's private
   `config/local.php`. The package contains no database/device/Wi-Fi credentials.
5. Install firmware 0.8.20 and owner app 0.9.40+60, then reset the ESP32 after
   the server update. Use Huge APP and disable Erase All Flash when uploading.
6. Verify a successful heartbeat, a new boot ID and WARMING_UP in the owner's
   monitoring response. The old Gas value must be hidden while other readings
   remain visible. After one minute, verify actual Gas ADC or the wiring message.

Older firmware heartbeats remain accepted. Firmware 0.8.20 retries the legacy
heartbeat format when the old server rejects `gas_runtime`, preserving device
connectivity. It retries metadata support after five minutes. Automatic reset
display is unavailable until the production database and PHP updates are deployed.
No deployment connection was available during this implementation.

## Verification

- 22 app tests, including one-minute timing, old-value suppression on reset,
  second-reset timing, ready measurements, stale heartbeat and offline behavior.
- 15 pure PHP runtime validation/deadline checks.
- 31 isolated MariaDB integration checks, including authenticated heartbeat
  persistence, cached-history preservation, key rejection and existing alerts.
- ESP32 build and Android release APK verification.

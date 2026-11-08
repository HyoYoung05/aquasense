# Partial sensor telemetry update

ESP32 firmware 0.8.16 and owner app 0.9.30+50 allow the available measurements
from all six modules to keep updating when another sensor fails. Missing
ultrasonic distance produces SQL NULL level and UNKNOWN status, not zero fill.
Existing level alerts remain unresolved until a valid level proves recovery.
Temperature, turbidity and flow alerts continue independently.

Deploy in this order:

1. Back up the production database and the PHP files being replaced.
2. Apply database/migrations/018-partial-sensor-telemetry.sql to production.
3. Upload the updated includes/telemetry.php, includes/alert-engine.php,
   includes/mobile-monitoring.php and includes/monitoring.php. Also update
   includes/oil-surrender.php and the admin monitoring/device/trap detail pages
   so missing levels are not presented as zero.
4. Upload firmware built from esp32/AquaSenseSensors to the current
   physical port (COM7 during diagnosis). Keep the ignored AQS-003 credentials,
   trap 3 identity, hardware profile and production HTTPS endpoint.
5. Install owner app 0.9.33+53 built with config/api.production.json. This APK
   uses the existing local debug signing opt-in; production API configuration
   does not imply that a production signing key is available.
6. Verify serial HTTP 201, a newer production sensor_readings row, and fresh
   values in the owner account. With missing distance, level must be unavailable
   while valid temperature, raw ADC and flow values continue updating.

Local validation: tests/partial-telemetry.php --allow-local-fixtures checks
all six modules, real zero flow, empty/invalid payload rejection, duplicate
retries, unknown level, preserved critical alerts, and recovered level alerts.
The test runs only against an explicitly configured development database.

An APK replacement by itself cannot fix a production API that still requires
ultrasonic distance. Do not flash partial-upload firmware before deploying
the database and PHP changes, or the old endpoint will reject it with HTTP 422.

-- AQUASENSE+ separate odor telemetry value.
-- Existing gas readings are preserved; odor remains NULL until a sensor sends it.
ALTER TABLE sensor_readings
  ADD COLUMN IF NOT EXISTS odor_value DECIMAL(10,2) NULL AFTER gas_value;

ALTER TABLE sensor_readings
  ADD CONSTRAINT IF NOT EXISTS chk_sensor_odor_nonnegative
  CHECK (odor_value IS NULL OR odor_value >= 0);

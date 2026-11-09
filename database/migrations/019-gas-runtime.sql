-- Apply before deploying Gas runtime heartbeat/monitoring PHP changes.
-- Device-scoped runtime is independent of historical sensor readings.
ALTER TABLE devices ADD COLUMN gas_runtime JSON NULL;

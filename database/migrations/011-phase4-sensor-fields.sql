-- Revised ESP32 Hardware Phase 4: scientifically distinct nullable readings.
-- Raw ADC values are never stored in calibrated NTU or gas-value columns.
ALTER TABLE sensor_readings
  ADD COLUMN IF NOT EXISTS turbidity_raw_adc SMALLINT UNSIGNED NULL AFTER turbidity_ntu,
  ADD COLUMN IF NOT EXISTS flow_pulse_count INT UNSIGNED NULL AFTER flow_rate_lpm,
  ADD COLUMN IF NOT EXISTS gas_raw_adc SMALLINT UNSIGNED NULL AFTER odor_value,
  ADD COLUMN IF NOT EXISTS ambient_temperature_c DECIMAL(6,2) NULL AFTER gas_raw_adc,
  ADD COLUMN IF NOT EXISTS humidity_percent DECIMAL(5,2) NULL AFTER ambient_temperature_c,
  ADD COLUMN IF NOT EXISTS ens160_aqi TINYINT UNSIGNED NULL AFTER humidity_percent,
  ADD COLUMN IF NOT EXISTS tvoc_ppb INT UNSIGNED NULL AFTER ens160_aqi,
  ADD COLUMN IF NOT EXISTS eco2_ppm INT UNSIGNED NULL AFTER tvoc_ppb;

ALTER TABLE sensor_readings
  ADD CONSTRAINT IF NOT EXISTS chk_phase4_turbidity_raw_adc CHECK (turbidity_raw_adc IS NULL OR turbidity_raw_adc <= 4095),
  ADD CONSTRAINT IF NOT EXISTS chk_phase4_gas_raw_adc CHECK (gas_raw_adc IS NULL OR gas_raw_adc <= 4095),
  ADD CONSTRAINT IF NOT EXISTS chk_phase4_ambient_temperature CHECK (ambient_temperature_c IS NULL OR ambient_temperature_c BETWEEN -40 AND 85),
  ADD CONSTRAINT IF NOT EXISTS chk_phase4_humidity CHECK (humidity_percent IS NULL OR humidity_percent BETWEEN 0 AND 100),
  ADD CONSTRAINT IF NOT EXISTS chk_phase4_ens160_aqi CHECK (ens160_aqi IS NULL OR ens160_aqi BETWEEN 1 AND 5),
  ADD CONSTRAINT IF NOT EXISTS chk_phase4_tvoc CHECK (tvoc_ppb IS NULL OR tvoc_ppb <= 65000),
  ADD CONSTRAINT IF NOT EXISTS chk_phase4_eco2 CHECK (eco2_ppm IS NULL OR eco2_ppm BETWEEN 400 AND 65000);

-- Hardware Phase 6: calibrated-only alert gates.
-- Existing numerical values are retained for review, but turbidity and flow
-- alerts stay disabled until physical calibration and threshold approval.
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
  ('turbidity_alert_enabled', '0', 'Enable only after SEN0189 NTU calibration and threshold approval.'),
  ('flow_alert_enabled', '0', 'Enable only after YF-S201 flow calibration and threshold approval.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

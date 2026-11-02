-- AQUASENSE+ 1.4.27: device availability is based on active assignment.
-- Physical connectivity is verified manually, so legacy heartbeat-age alerts
-- must not remain visible as unresolved device failures.
UPDATE alerts
SET status = 'RESOLVED',
    resolved_at = COALESCE(resolved_at, UTC_TIMESTAMP()),
    resolution_note = COALESCE(resolution_note, 'Closed after enabling assignment-based device status.')
WHERE alert_type = 'DEVICE_OFFLINE'
  AND status <> 'RESOLVED';

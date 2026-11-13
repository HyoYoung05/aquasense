-- Refresh only unresolved owner-reported messages; retain resolved history.
UPDATE alerts SET message='Please check the grease trap and take action if needed.'
WHERE alert_type='MANUAL_WARNING' AND status IN ('ACTIVE','ACKNOWLEDGED');
UPDATE alerts SET message='Urgent attention is needed. Please check the grease trap immediately.'
WHERE alert_type='MANUAL_CRITICAL' AND status IN ('ACTIVE','ACKNOWLEDGED');

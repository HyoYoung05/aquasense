-- AQUASENSE+ 1.4.18: optional user email and unique contact-number login.
-- Apply once to an existing database before creating email-free accounts.
ALTER TABLE users
  MODIFY COLUMN email VARCHAR(190) NULL,
  ADD UNIQUE KEY uq_users_contact_number (contact_number);

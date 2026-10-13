-- AQUASENSE+ role separation for full system administration.
-- Additive and safe to run more than once. No account or password is created.
INSERT INTO roles (slug, name)
VALUES ('system_admin', 'Admin')
ON DUPLICATE KEY UPDATE name = VALUES(name);

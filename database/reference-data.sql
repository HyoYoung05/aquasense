-- REQUIRED REFERENCE DATA. Safe for development, test, and production.
-- Contains role names only: no users, passwords, businesses, devices, or sample activity.
SET time_zone = '+00:00';

INSERT INTO roles (slug, name) VALUES
    ('administrator', 'Barangay Administrator'),
    ('environmental_staff', 'Barangay Environmental Staff'),
    ('owner', 'Carinderia Owner')
ON DUPLICATE KEY UPDATE name = VALUES(name);

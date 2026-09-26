-- AQUASENSE+ 0.4.0 Phase 2: additive administrative fields.
-- Safe for the existing installation; no records are deleted or rewritten.
SET time_zone = '+00:00';

ALTER TABLE establishments
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER email;

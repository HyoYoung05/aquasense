-- AQUASENSE+ owner profile-photo support.
-- Additive and safe to reapply; existing accounts remain unchanged.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS profile_photo_path VARCHAR(255) NULL AFTER contact_number,
  ADD COLUMN IF NOT EXISTS profile_photo_mime VARCHAR(50) NULL AFTER profile_photo_path,
  ADD COLUMN IF NOT EXISTS profile_photo_updated_at DATETIME NULL AFTER profile_photo_mime;

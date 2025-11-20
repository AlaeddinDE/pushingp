-- MIGRATION 2025-11-20: Unique 6-digit PINs
-- Update all users to have a unique 6-digit PIN based on their ID to ensure uniqueness
UPDATE users SET pin_hash = CAST(100000 + id AS CHAR);

-- Add unique constraint to pin_hash to enforce uniqueness
ALTER TABLE users ADD UNIQUE (pin_hash);

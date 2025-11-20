-- MIGRATION 2025-11-20: Set specific PINs for core members
-- Alaeddin
UPDATE users SET pin_hash = '150702' WHERE id = 4;
-- Alessio
UPDATE users SET pin_hash = '403987' WHERE id = 5;
-- Yassin
UPDATE users SET pin_hash = '927746' WHERE id = 11;
-- Adis
UPDATE users SET pin_hash = '050890' WHERE id = 7;
-- Ayyub
UPDATE users SET pin_hash = '210719' WHERE id = 6;

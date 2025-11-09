-- MIGRATION 2025-11-09: Add sort order for shift workers
ALTER TABLE users ADD COLUMN shift_sort_order INT DEFAULT 999 AFTER shift_enabled;

-- MIGRATION 2025-11-09: Remove day shift type
ALTER TABLE shifts 
MODIFY COLUMN type ENUM('early','late','night','free','vacation') NOT NULL;

-- MIGRATION 2025-11-09: Add free and vacation shift types
ALTER TABLE shifts 
MODIFY COLUMN type ENUM('early','day','late','night','free','vacation') NOT NULL;

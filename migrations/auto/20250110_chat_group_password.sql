-- MIGRATION 2025-01-10: Chat Group Password Protection
-- Adds password protection to chat groups

ALTER TABLE chat_groups 
ADD COLUMN password_hash VARCHAR(255) NULL AFTER created_by,
ADD COLUMN is_protected TINYINT DEFAULT 0 AFTER password_hash;

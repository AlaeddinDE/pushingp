-- MIGRATION 2025-11-10: Add useful user settings fields

ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER discord_tag;
ALTER TABLE users ADD COLUMN birthday DATE NULL AFTER phone;
ALTER TABLE users ADD COLUMN team_role VARCHAR(100) NULL AFTER bio;
ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL AFTER team_role;
ALTER TABLE users ADD COLUMN event_notifications TINYINT(1) DEFAULT 1 AFTER notifications_enabled;
ALTER TABLE users ADD COLUMN shift_notifications TINYINT(1) DEFAULT 1 AFTER event_notifications;

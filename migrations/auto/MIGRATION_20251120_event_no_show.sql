-- MIGRATION 2025-11-20: Add no_show status to event_participants
ALTER TABLE event_participants MODIFY COLUMN status ENUM('coming', 'declined', 'no_show') DEFAULT 'coming';

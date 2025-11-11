-- MIGRATION 2025-11-11: Chat Invitations Support + Blackjack Game
-- Adds columns to support invitation messages (casino games, events, etc.)
-- Adds blackjack to casino games

-- Chat invitations
ALTER TABLE chat_messages 
ADD COLUMN message_type VARCHAR(20) DEFAULT 'text' AFTER message,
ADD COLUMN invitation_type VARCHAR(50) NULL AFTER message_type,
ADD COLUMN invitation_data TEXT NULL AFTER invitation_type;

-- Index for faster lookups
CREATE INDEX idx_message_type ON chat_messages(message_type);
CREATE INDEX idx_invitation_type ON chat_messages(invitation_type);

-- Add blackjack to casino games
ALTER TABLE casino_history 
MODIFY COLUMN game_type ENUM('slots','wheel','crash','plinko','blackjack') NOT NULL;

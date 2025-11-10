-- MIGRATION 2025-01-10: Add crash_point column to casino_active_games
-- Reason: Server-side crash point generation for provably fair gaming

ALTER TABLE casino_active_games 
ADD COLUMN crash_point DECIMAL(10,2) NULL AFTER bet_amount;

-- Add unique index to prevent duplicate active games per user
ALTER TABLE casino_active_games
ADD UNIQUE INDEX idx_user_game (user_id, game_type);

-- MIGRATION 2025-11-11: add_plinko_game_type
-- Fügt 'plinko' zur game_type ENUM-Spalte hinzu

ALTER TABLE casino_history 
MODIFY COLUMN game_type ENUM('slots', 'wheel', 'crash', 'plinko') NOT NULL;

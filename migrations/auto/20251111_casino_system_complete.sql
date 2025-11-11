-- MIGRATION 2025-11-11: Complete Casino System
-- Extends casino_history, adds balance logs, updates multiplayer

-- 1. Update casino_history game types
ALTER TABLE casino_history 
MODIFY COLUMN game_type ENUM(
    'slots',
    'wheel',
    'crash',
    'plinko',
    'blackjack',
    'chicken',
    'multiplayer',
    'roulette',
    'dice'
) NOT NULL;

-- 2. Create casino balance logs for audit trail
CREATE TABLE IF NOT EXISTS casino_balance_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_id INT UNSIGNED NULL,
    game_type VARCHAR(20) NOT NULL,
    action ENUM('bet','win','cashout','refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_time (user_id, created_at),
    INDEX idx_game_type (game_type),
    INDEX idx_action (action),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES casino_history(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Bet limits already exist in casino_history

-- 4. Create casino settings table
CREATE TABLE IF NOT EXISTS casino_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Insert default casino settings
INSERT INTO casino_settings (setting_key, setting_value, description) VALUES
('min_bet', '0.01', 'Minimum bet amount in EUR'),
('max_bet', '10.00', 'Maximum bet amount in EUR'),
('min_balance_reserve', '10.00', 'Minimum balance reserve required'),
('casino_enabled', '1', 'Casino system enabled (1=yes, 0=no)'),
('multiplayer_enabled', '1', 'Multiplayer games enabled (1=yes, 0=no)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 6. Add indexes for better performance (skip if exists)

-- 7. Update multiplayer tables for better state management
ALTER TABLE casino_multiplayer_tables
MODIFY COLUMN game_type ENUM('blackjack','poker','roulette') NOT NULL DEFAULT 'blackjack';

-- 8. Add casino statistics view
CREATE OR REPLACE VIEW v_casino_stats AS
SELECT 
    u.id as user_id,
    u.username,
    u.name,
    COUNT(ch.id) as total_games,
    SUM(ch.bet_amount) as total_wagered,
    SUM(ch.win_amount) as total_won,
    SUM(ch.bet_amount - ch.win_amount) as total_lost,
    ROUND((SUM(ch.win_amount) / NULLIF(SUM(ch.bet_amount), 0)) * 100, 2) as win_rate_pct,
    MAX(ch.created_at) as last_played
FROM users u
LEFT JOIN casino_history ch ON u.id = ch.user_id
GROUP BY u.id, u.username, u.name;

-- 9. Active games already has game_data column

-- 10. Migration complete
SELECT 'Casino System Migration Complete' as Status;

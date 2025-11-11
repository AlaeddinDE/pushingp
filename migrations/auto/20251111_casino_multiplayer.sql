-- MIGRATION 2025-11-11: Casino Multiplayer Tables
-- Adds support for multiplayer casino games (Blackjack, Poker, etc.)

CREATE TABLE IF NOT EXISTS casino_multiplayer_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_user_id INT NOT NULL,
    game_type ENUM('blackjack', 'poker') NOT NULL DEFAULT 'blackjack',
    table_name VARCHAR(100) NOT NULL,
    max_players INT DEFAULT 4,
    current_players INT DEFAULT 1,
    min_bet DECIMAL(10,2) DEFAULT 1.00,
    max_bet DECIMAL(10,2) DEFAULT 50.00,
    status ENUM('waiting', 'playing', 'finished') DEFAULT 'waiting',
    game_state JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_game_type (game_type),
    INDEX idx_host (host_user_id),
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS casino_multiplayer_players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    bet_amount DECIMAL(10,2) NOT NULL,
    hand JSON NULL,
    hand_value INT DEFAULT 0,
    status ENUM('waiting', 'playing', 'stand', 'bust', 'win', 'lose', 'push') DEFAULT 'waiting',
    position INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_table (table_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (table_id) REFERENCES casino_multiplayer_tables(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

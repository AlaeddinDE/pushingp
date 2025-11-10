-- CASINO SYSTEM TABLES
-- Created: 2025-11-10

CREATE TABLE IF NOT EXISTS `casino_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `game_type` ENUM('slots', 'wheel', 'crash') NOT NULL,
  `bet_amount` DECIMAL(10,2) NOT NULL,
  `win_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `multiplier` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `result` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_game (game_type),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `casino_active_games` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `game_type` VARCHAR(20) NOT NULL,
  `bet_amount` DECIMAL(10,2) NOT NULL,
  `game_data` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_game (user_id, game_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

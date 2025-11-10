-- MIGRATION 2025-11-10: Apple Wallet Integration
-- Tables for managing Apple Wallet passes and push notifications

-- Wallet Pass Registrations (devices that have added the pass)
CREATE TABLE IF NOT EXISTS wallet_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_library_identifier VARCHAR(255) NOT NULL,
    pass_type_identifier VARCHAR(255) NOT NULL,
    serial_number VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    push_token VARCHAR(255),
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_registration (device_library_identifier, pass_type_identifier, serial_number),
    INDEX idx_user (user_id),
    INDEX idx_serial (serial_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wallet Authentication Tokens (per-user tokens for pass updates)
CREATE TABLE IF NOT EXISTS wallet_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pass Update Log (track when passes were last modified)
CREATE TABLE IF NOT EXISTS wallet_pass_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    serial_number VARCHAR(255) NOT NULL,
    update_tag VARCHAR(32),
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255),
    INDEX idx_user (user_id),
    INDEX idx_serial (serial_number),
    INDEX idx_modified (last_modified),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add wallet fields to users table
ALTER TABLE users ADD COLUMN wallet_serial VARCHAR(255) UNIQUE;
ALTER TABLE users ADD COLUMN wallet_last_updated TIMESTAMP NULL;

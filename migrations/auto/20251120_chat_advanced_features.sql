-- MIGRATION 2025-11-20: Chat Advanced Features (Reactions, Pin, Read Receipts)

-- Create chat_reactions table
CREATE TABLE IF NOT EXISTS chat_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (message_id, user_id, emoji),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_message_reactions (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create chat_pinned_messages table
CREATE TABLE IF NOT EXISTS chat_pinned_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNIQUE NOT NULL,
    pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    INDEX idx_pinned (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create chat_read_receipts table
CREATE TABLE IF NOT EXISTS chat_read_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_read (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_message_reads (message_id),
    INDEX idx_user_reads (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add updated_at column to chat_messages if not exists (check first)
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'pushingp' 
    AND TABLE_NAME = 'chat_messages' 
    AND COLUMN_NAME = 'updated_at'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE chat_messages ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
    'SELECT "Column updated_at already exists" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add is_pinned flag to chat_messages for quick access (check first)
SET @column_exists2 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'pushingp' 
    AND TABLE_NAME = 'chat_messages' 
    AND COLUMN_NAME = 'is_pinned'
);

SET @sql2 = IF(@column_exists2 = 0, 
    'ALTER TABLE chat_messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0',
    'SELECT "Column is_pinned already exists" AS Info'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

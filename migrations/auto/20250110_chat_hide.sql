-- MIGRATION 2025-01-10: Chat Hide Functionality
-- Ermöglicht Usern, Chats auszublenden

CREATE TABLE IF NOT EXISTS chat_hidden (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chat_type ENUM('user', 'group') NOT NULL,
    chat_id INT NOT NULL,
    hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_hidden_chat (user_id, chat_type, chat_id),
    INDEX idx_user_chat (user_id, chat_type, chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

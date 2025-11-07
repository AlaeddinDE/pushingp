-- MIGRATION 2025-11-07: Complete schema upgrade (MySQL 8 compatible)
-- Following architecture.md, AGENTS.md, kasse.md, crew.md, events.md, schichten.md

SET @dbname = DATABASE();

-- ============================================================================
-- 1) Users & Roles Enhancement
-- ============================================================================

-- Add columns only if they don't exist
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='name') = 0,
    "ALTER TABLE users ADD COLUMN name VARCHAR(100) DEFAULT NULL AFTER username",
    "SELECT 'Column name already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='email') = 0,
    "ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER name",
    "SELECT 'Column email already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='discord_tag') = 0,
    "ALTER TABLE users ADD COLUMN discord_tag VARCHAR(50) DEFAULT NULL AFTER email",
    "SELECT 'Column discord_tag already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='avatar') = 0,
    "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER discord_tag",
    "SELECT 'Column avatar already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='roles') = 0,
    "ALTER TABLE users ADD COLUMN roles JSON DEFAULT NULL AFTER role",
    "SELECT 'Column roles already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='status') = 0,
    "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'locked') DEFAULT 'active' AFTER roles",
    "SELECT 'Column status already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='aktiv_ab') = 0,
    "ALTER TABLE users ADD COLUMN aktiv_ab DATE DEFAULT NULL AFTER status",
    "SELECT 'Column aktiv_ab already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='inaktiv_ab') = 0,
    "ALTER TABLE users ADD COLUMN inaktiv_ab DATE DEFAULT NULL AFTER aktiv_ab",
    "SELECT 'Column inaktiv_ab already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='pin_hash') = 0,
    "ALTER TABLE users ADD COLUMN pin_hash VARCHAR(255) DEFAULT NULL AFTER password",
    "SELECT 'Column pin_hash already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='last_login') = 0,
    "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL",
    "SELECT 'Column last_login already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='updated_at') = 0,
    "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    "SELECT 'Column updated_at already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_email ON users(email);

-- ============================================================================
-- 2) Settings Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  theme ENUM('dark', 'light') DEFAULT 'dark',
  monthly_fee DECIMAL(10,2) DEFAULT 10.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_settings (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3) Shifts, Vacations, Sick Days
-- ============================================================================

CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  type ENUM('early', 'late', 'night', 'day') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_shifts_user_date (user_id, date),
  INDEX idx_shifts_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vacations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_vacations_user (user_id),
  INDEX idx_vacations_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sickdays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_sickdays_user (user_id),
  INDEX idx_sickdays_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4) Events Enhancement
-- ============================================================================

-- Add columns to events
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='description') = 0,
    "ALTER TABLE events ADD COLUMN description TEXT AFTER title",
    "SELECT 'Column description already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='start_time') = 0,
    "ALTER TABLE events ADD COLUMN start_time DATETIME DEFAULT NULL",
    "SELECT 'Column start_time already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='end_time') = 0,
    "ALTER TABLE events ADD COLUMN end_time DATETIME DEFAULT NULL",
    "SELECT 'Column end_time already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='location') = 0,
    "ALTER TABLE events ADD COLUMN location VARCHAR(255) DEFAULT NULL",
    "SELECT 'Column location already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='cost') = 0,
    "ALTER TABLE events ADD COLUMN cost DECIMAL(10,2) DEFAULT 0.00",
    "SELECT 'Column cost already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='paid_by') = 0,
    "ALTER TABLE events ADD COLUMN paid_by ENUM('pool', 'anteilig', 'private') DEFAULT 'private'",
    "SELECT 'Column paid_by already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='created_by') = 0,
    "ALTER TABLE events ADD COLUMN created_by INT DEFAULT NULL, ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
    "SELECT 'Column created_by already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='event_status') = 0,
    "ALTER TABLE events ADD COLUMN event_status ENUM('active', 'canceled', 'completed') DEFAULT 'active'",
    "SELECT 'Column event_status already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='events' AND COLUMN_NAME='updated_at') = 0,
    "ALTER TABLE events ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    "SELECT 'Column updated_at already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update event_participants
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='event_participants' AND COLUMN_NAME='state') = 0,
    "ALTER TABLE event_participants ADD COLUMN state ENUM('yes', 'no', 'pending') DEFAULT 'pending' AFTER user_id",
    "SELECT 'Column state already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='event_participants' AND COLUMN_NAME='availability') = 0,
    "ALTER TABLE event_participants ADD COLUMN availability ENUM('free', 'vacation', 'shift', 'sick') DEFAULT 'free' AFTER state",
    "SELECT 'Column availability already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='event_participants' AND COLUMN_NAME='created_at') = 0,
    "ALTER TABLE event_participants ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "SELECT 'Column created_at already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='event_participants' AND COLUMN_NAME='updated_at') = 0,
    "ALTER TABLE event_participants ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    "SELECT 'Column updated_at already exists'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 5) Finance/Kasse System
-- ============================================================================

CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  typ ENUM('EINZAHLUNG', 'AUSZAHLUNG', 'GRUPPENAKTION_KASSE', 'GRUPPENAKTION_ANTEILIG', 
           'SCHADEN', 'UMBUCHUNG', 'KORREKTUR', 'STORNO', 'RESERVIERUNG', 'AUSGLEICH') NOT NULL,
  betrag DECIMAL(10,2) NOT NULL,
  mitglied_id INT DEFAULT NULL,
  event_id INT DEFAULT NULL,
  beschreibung TEXT,
  erstellt_von INT DEFAULT NULL,
  korrigiert_durch INT DEFAULT NULL,
  reversal_von INT DEFAULT NULL,
  status ENUM('gebucht', 'gesperrt', 'storniert') DEFAULT 'gebucht',
  beleg_ref VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mitglied_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  FOREIGN KEY (erstellt_von) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (korrigiert_durch) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (reversal_von) REFERENCES transactions(id) ON DELETE SET NULL,
  INDEX idx_transactions_type (typ),
  INDEX idx_transactions_member (mitglied_id),
  INDEX idx_transactions_status (status),
  INDEX idx_transactions_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  betrag DECIMAL(10,2) NOT NULL,
  status ENUM('active', 'released', 'consumed') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  released_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_reservations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6) Admin Logs
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  admin_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id INT DEFAULT NULL,
  payload_hash VARCHAR(64) DEFAULT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_admin_logs_admin (admin_id),
  INDEX idx_admin_logs_timestamp (ts),
  INDEX idx_admin_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7) Balance Snapshots
-- ============================================================================

CREATE TABLE IF NOT EXISTS balance_snapshot (
  id INT AUTO_INCREMENT PRIMARY KEY,
  snapshot_date DATE NOT NULL UNIQUE,
  balance_brutto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance_reserviert DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance_verfuegbar DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  member_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_snapshot_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8) CSRF Tokens
-- ============================================================================

CREATE TABLE IF NOT EXISTS csrf_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_csrf_token (token),
  INDEX idx_csrf_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9) System Settings
-- ============================================================================

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT,
  setting_type ENUM('string', 'int', 'float', 'json', 'boolean') DEFAULT 'string',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by INT DEFAULT NULL,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type) VALUES
  ('monthly_fee', '10.00', 'float'),
  ('due_day', '15', 'int'),
  ('overdue_grace_days', '7', 'int'),
  ('discord_webhook_enabled', 'false', 'boolean'),
  ('maintenance_mode', 'false', 'boolean');

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

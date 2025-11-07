-- MIGRATION 2025-11-07: Complete schema upgrade to match all .md specifications
-- Following architecture.md, AGENTS.md, kasse.md, crew.md, events.md, schichten.md

-- ============================================================================
-- 1) Users & Roles Enhancement
-- ============================================================================

-- Add missing fields to users table
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS name VARCHAR(100) DEFAULT NULL AFTER username,
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER name,
  ADD COLUMN IF NOT EXISTS discord_tag VARCHAR(50) DEFAULT NULL AFTER email,
  ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL AFTER discord_tag,
  ADD COLUMN IF NOT EXISTS roles JSON DEFAULT NULL AFTER role,
  ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'locked') DEFAULT 'active' AFTER roles,
  ADD COLUMN IF NOT EXISTS aktiv_ab DATE DEFAULT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS inaktiv_ab DATE DEFAULT NULL AFTER aktiv_ab,
  ADD COLUMN IF NOT EXISTS pin_hash VARCHAR(255) DEFAULT NULL AFTER password,
  ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add index for performance
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- ============================================================================
-- 2) Settings Table (Theme, Preferences)
-- ============================================================================

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  theme ENUM('dark', 'light') DEFAULT 'dark',
  monthly_fee DECIMAL(10,2) DEFAULT 10.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_settings (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3) Shifts, Vacations, Sick Days (schichten.md)
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
-- 4) Events Enhancement (events.md)
-- ============================================================================

-- Update events table structure
ALTER TABLE events 
  ADD COLUMN IF NOT EXISTS description TEXT AFTER title,
  ADD COLUMN IF NOT EXISTS start_time DATETIME DEFAULT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS end_time DATETIME DEFAULT NULL AFTER start_time,
  ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL AFTER end_time,
  ADD COLUMN IF NOT EXISTS cost DECIMAL(10,2) DEFAULT 0.00 AFTER location,
  ADD COLUMN IF NOT EXISTS paid_by ENUM('pool', 'anteilig', 'private') DEFAULT 'private' AFTER cost,
  ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER paid_by,
  ADD COLUMN IF NOT EXISTS event_status ENUM('active', 'canceled', 'completed') DEFAULT 'active' AFTER created_by,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update event_participants structure
ALTER TABLE event_participants
  ADD COLUMN IF NOT EXISTS state ENUM('yes', 'no', 'pending') DEFAULT 'pending' AFTER user_id,
  ADD COLUMN IF NOT EXISTS availability ENUM('free', 'vacation', 'shift', 'sick') DEFAULT 'free' AFTER state,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ============================================================================
-- 5) Kasse/Finance System (kasse.md)
-- ============================================================================

-- Enhanced transactions table
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

-- Reservations tracking
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
-- 6) Admin Logs (admin.md, architecture.md)
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
-- 7) Balance History for Charts (startseite.md, kasse.md)
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
-- 8) CSRF Token Table
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

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type) VALUES
  ('monthly_fee', '10.00', 'float'),
  ('due_day', '15', 'int'),
  ('overdue_grace_days', '7', 'int'),
  ('discord_webhook_enabled', 'false', 'boolean'),
  ('maintenance_mode', 'false', 'boolean');

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

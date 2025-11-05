-- MIGRATION 2024-07-09: system_modules
ALTER TABLE members_v2
  ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS discord_tag VARCHAR(100) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL AFTER discord_tag,
  ADD COLUMN IF NOT EXISTS roles VARCHAR(120) NOT NULL DEFAULT 'member' AFTER avatar_url,
  ADD COLUMN IF NOT EXISTS timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin' AFTER roles,
  ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

ALTER TABLE transactions_v2
  MODIFY COLUMN type ENUM('Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Ausgleich','Korrektur','Umbuchung') NOT NULL,
  ADD COLUMN IF NOT EXISTS event_id BIGINT UNSIGNED NULL AFTER member_id,
  ADD COLUMN IF NOT EXISTS payment_request_id BIGINT UNSIGNED NULL AFTER event_id,
  ADD COLUMN IF NOT EXISTS reservation_id BIGINT UNSIGNED NULL AFTER payment_request_id,
  ADD COLUMN IF NOT EXISTS status ENUM('gebucht','gesperrt','storniert') NOT NULL DEFAULT 'gebucht' AFTER reason,
  ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER status,
  ADD KEY IF NOT EXISTS idx_transactions_status (status),
  ADD KEY IF NOT EXISTS idx_transactions_event (event_id),
  ADD KEY IF NOT EXISTS idx_transactions_payment (payment_request_id);

DROP VIEW IF EXISTS v2_member_real_balance;
CREATE VIEW v2_member_real_balance AS
SELECT
  m.id AS member_id,
  m.name,
  ROUND(COALESCE(SUM(
    CASE
      WHEN t.status='gebucht' AND t.type='Einzahlung' THEN t.amount
      WHEN t.status='gebucht' AND t.type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -t.amount
      WHEN t.status='gebucht' AND t.type IN ('Korrektur','Ausgleich') THEN t.amount
      ELSE 0
    END
  ),0),2) AS real_balance
FROM members_v2 m
LEFT JOIN transactions_v2 t ON t.member_id = m.id
GROUP BY m.id, m.name;

DROP VIEW IF EXISTS v2_kassenstand_real;
CREATE VIEW v2_kassenstand_real AS
SELECT
  ROUND(COALESCE(SUM(
    CASE
      WHEN status='gebucht' AND type='Einzahlung' THEN amount
      WHEN status='gebucht' AND type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -amount
      WHEN status='gebucht' AND type IN ('Korrektur','Ausgleich') THEN amount
      ELSE 0
    END
  ),0),2) AS kassenstand
FROM transactions_v2;

CREATE TABLE IF NOT EXISTS user_settings (
  member_id INT UNSIGNED NOT NULL,
  theme ENUM('light','dark') NOT NULL DEFAULT 'dark',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  CONSTRAINT fk_user_settings_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_status_cache (
  member_id INT UNSIGNED NOT NULL,
  status ENUM('online','away','busy','offline') NOT NULL DEFAULT 'offline',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  CONSTRAINT fk_discord_status_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shifts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT UNSIGNED NOT NULL,
  shift_date DATE NOT NULL,
  type ENUM('early','late','night','day') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shift_member_date (member_id, shift_date),
  KEY idx_shifts_date (shift_date),
  CONSTRAINT fk_shifts_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vacations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vacations_member (member_id),
  CONSTRAINT fk_vacations_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sickdays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sickdays_member (member_id),
  CONSTRAINT fk_sickdays_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays_cache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  holiday_date DATE NOT NULL,
  name VARCHAR(150) NOT NULL,
  region VARCHAR(32) NOT NULL DEFAULT 'NRW',
  PRIMARY KEY (id),
  UNIQUE KEY uq_holiday_region (holiday_date, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  start DATETIME NOT NULL,
  end DATETIME NULL,
  location VARCHAR(255) NULL,
  cost DECIMAL(10,2) NULL,
  paid_by ENUM('pool','private') NOT NULL DEFAULT 'private',
  created_by INT UNSIGNED NULL,
  status ENUM('active','canceled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_start (start),
  KEY idx_events_status (status),
  CONSTRAINT fk_events_member FOREIGN KEY (created_by) REFERENCES members_v2(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_participants (
  event_id BIGINT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  state ENUM('yes','no','pending') NOT NULL DEFAULT 'pending',
  availability ENUM('free','vacation','shift','sick') NOT NULL DEFAULT 'free',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id, member_id),
  KEY idx_event_participants_state (state),
  CONSTRAINT fk_ep_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ep_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations_v2 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NULL,
  member_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('active','released','consumed','cancelled') NOT NULL DEFAULT 'active',
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_reservations_status (status),
  KEY idx_reservations_event (event_id),
  CONSTRAINT fk_reservations_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reservations_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  token CHAR(32) NOT NULL,
  external_reference VARCHAR(120) NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  paid_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_token (token),
  KEY idx_payment_member (member_id),
  CONSTRAINT fk_payment_member FOREIGN KEY (member_id) REFERENCES members_v2(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NULL,
  message TEXT NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  user_agent VARCHAR(255) NULL,
  status ENUM('new','read') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_feedback_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NULL,
  payload JSON NOT NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_notifications_status (status),
  CONSTRAINT fk_notifications_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id VARCHAR(60) NULL,
  payload_hash CHAR(64) NOT NULL,
  details JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_logs_action (action),
  KEY idx_admin_logs_entity (entity_type, entity_id),
  CONSTRAINT fk_admin_logs_member FOREIGN KEY (admin_id) REFERENCES members_v2(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Pushing P – Bereinigter Datenbank-Stand (MySQL 8)
-- Erstellt: 2025-11-06
-- Zweck: Entfernt Altlasten aus dem Dump und stellt das Minimal-Schema
--        für die aktive Plattform bereit (Legacy v1 + aktuelles v2).
--
-- Ausführungshinweis:
-- 1. Auf einer frischen Datenbank ausführen.
-- 2. Anschließend die Migrationsdateien im Verzeichnis `migrations/`
--    in aufsteigender Reihenfolge ausführen, um spätere Erweiterungen
--    (Events, Reservierungen, Notifications, etc.) einzuspielen.
-- =========================================================
SET NAMES utf8mb4;
SET SESSION sql_require_primary_key = 0;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------
-- Aufräumen von Tabellen, die im Projekt nicht mehr benutzt werden.
-- ---------------------------------------------------------
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS chat;
DROP TABLE IF EXISTS config;
DROP TABLE IF EXISTS ledger;
DROP TABLE IF EXISTS ledger_items;
DROP TABLE IF EXISTS monthly_payments;
DROP TABLE IF EXISTS monthly_settings;
DROP TABLE IF EXISTS pool_amounts;
DROP TABLE IF EXISTS pool_cache;
DROP TABLE IF EXISTS proposals;
DROP TABLE IF EXISTS proposal_votes;

-- ---------------------------------------------------------
-- Legacy-Bestand (v1) – weiterhin benötigt für Alt-APIs.
-- Diese Tabellen enthalten KEINE Beispiel-Daten mehr.
-- ---------------------------------------------------------
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  pin         VARCHAR(6)  NOT NULL,
  member_name VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (pin),
  KEY idx_member_name (member_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS members;
CREATE TABLE members (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(255) NOT NULL,
  flag       VARCHAR(32) DEFAULT NULL,
  start_date DATE DEFAULT NULL,
  pic        VARCHAR(255) DEFAULT NULL,
  pin        VARCHAR(6) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
  uid     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id      VARCHAR(64) NOT NULL,
  name    VARCHAR(255) NOT NULL,
  amount  DECIMAL(10,2) NOT NULL,
  date    DATE NOT NULL,
  type    ENUM('Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion') NOT NULL,
  reason  TEXT,
  PRIMARY KEY (uid),
  UNIQUE KEY uq_transactions_id (id),
  KEY idx_transactions_name (name),
  KEY idx_transactions_date (date),
  KEY idx_transactions_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS shifts;
CREATE TABLE shifts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id   INT UNSIGNED NOT NULL,
  member_name VARCHAR(255) DEFAULT NULL,
  shift_date  DATE NOT NULL,
  shift_start TIME DEFAULT NULL,
  shift_end   TIME DEFAULT NULL,
  start_time  TIME DEFAULT NULL,
  end_time    TIME DEFAULT NULL,
  shift_type  ENUM('early','late','night','day','custom') NOT NULL DEFAULT 'custom',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shifts_member (member_id),
  KEY idx_shifts_date (shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Aktiver Kern (v2)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS members_v2;
CREATE TABLE members_v2 (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(190) DEFAULT NULL,
  discord_tag VARCHAR(100) DEFAULT NULL,
  avatar_url VARCHAR(255) DEFAULT NULL,
  roles      VARCHAR(120) NOT NULL DEFAULT 'member',
  timezone   VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
  flag       VARCHAR(10) DEFAULT NULL,
  joined_at  DATE NOT NULL DEFAULT (CURRENT_DATE),
  left_at    DATE DEFAULT NULL,
  status     ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  is_locked  TINYINT(1) NOT NULL DEFAULT 0,
  pin_plain  VARCHAR(6) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_v2_name (name),
  KEY idx_members_v2_status (status),
  KEY idx_members_v2_joined (joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admins_v2;
CREATE TABLE admins_v2 (
  member_id  INT UNSIGNED NOT NULL,
  is_admin   TINYINT(1) NOT NULL DEFAULT 0,
  granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  CONSTRAINT fk_admins_v2_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS settings_v2;
CREATE TABLE settings_v2 (
  key_name  VARCHAR(64) NOT NULL,
  value     TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS transactions_v2;
CREATE TABLE transactions_v2 (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id           INT UNSIGNED NOT NULL,
  event_id            BIGINT UNSIGNED DEFAULT NULL,
  payment_request_id  BIGINT UNSIGNED DEFAULT NULL,
  reservation_id      BIGINT UNSIGNED DEFAULT NULL,
  type                ENUM('Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Ausgleich','Korrektur','Umbuchung') NOT NULL,
  amount              DECIMAL(10,2) NOT NULL,
  reason              VARCHAR(255) DEFAULT NULL,
  status              ENUM('gebucht','gesperrt','storniert') NOT NULL DEFAULT 'gebucht',
  metadata            JSON DEFAULT NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tx_v2_member (member_id),
  KEY idx_tx_v2_created (created_at),
  KEY idx_tx_v2_type (type),
  KEY idx_tx_v2_status (status),
  KEY idx_tx_v2_event (event_id),
  KEY idx_tx_v2_payment (payment_request_id),
  CONSTRAINT fk_tx_v2_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_board;
CREATE TABLE admin_board (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type          ENUM('event','announcement') NOT NULL,
  title         VARCHAR(150) NOT NULL,
  content       TEXT NULL,
  scheduled_for DATETIME DEFAULT NULL,
  created_by    VARCHAR(120) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_board_schedule (scheduled_for),
  KEY idx_admin_board_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS user_settings;
CREATE TABLE user_settings (
  member_id INT UNSIGNED NOT NULL,
  theme     ENUM('light','dark') NOT NULL DEFAULT 'dark',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  CONSTRAINT fk_user_settings_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS discord_status_cache;
CREATE TABLE discord_status_cache (
  member_id  INT UNSIGNED NOT NULL,
  status     ENUM('online','away','busy','offline') NOT NULL DEFAULT 'offline',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  CONSTRAINT fk_discord_status_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS vacations;
CREATE TABLE vacations (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id  INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vacations_member (member_id),
  CONSTRAINT fk_vacations_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS sickdays;
CREATE TABLE sickdays (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id  INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sickdays_member (member_id),
  CONSTRAINT fk_sickdays_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS holidays_cache;
CREATE TABLE holidays_cache (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  holiday_date DATE NOT NULL,
  name        VARCHAR(150) NOT NULL,
  region      VARCHAR(32) NOT NULL DEFAULT 'NRW',
  PRIMARY KEY (id),
  UNIQUE KEY uq_holiday_region (holiday_date, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS events;
CREATE TABLE events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(150) NOT NULL,
  description TEXT NULL,
  start       DATETIME NOT NULL,
  end         DATETIME NULL,
  location    VARCHAR(255) NULL,
  cost        DECIMAL(10,2) NULL,
  paid_by     ENUM('pool','private') NOT NULL DEFAULT 'private',
  created_by  INT UNSIGNED NULL,
  status      ENUM('active','canceled') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_start (start),
  KEY idx_events_status (status),
  CONSTRAINT fk_events_member FOREIGN KEY (created_by)
    REFERENCES members_v2(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS event_participants;
CREATE TABLE event_participants (
  event_id     BIGINT UNSIGNED NOT NULL,
  member_id    INT UNSIGNED NOT NULL,
  state        ENUM('yes','no','pending') NOT NULL DEFAULT 'pending',
  availability ENUM('free','vacation','shift','sick') NOT NULL DEFAULT 'free',
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id, member_id),
  KEY idx_event_participants_state (state),
  CONSTRAINT fk_ep_event FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ep_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS reservations_v2;
CREATE TABLE reservations_v2 (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id   BIGINT UNSIGNED DEFAULT NULL,
  member_id  INT UNSIGNED DEFAULT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  status     ENUM('active','released','consumed','cancelled') NOT NULL DEFAULT 'active',
  notes      VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  expires_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_reservations_status (status),
  KEY idx_reservations_event (event_id),
  CONSTRAINT fk_reservations_event FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reservations_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payment_requests;
CREATE TABLE payment_requests (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id          INT UNSIGNED NOT NULL,
  amount             DECIMAL(10,2) NOT NULL,
  status             ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  token              CHAR(32) NOT NULL,
  external_reference VARCHAR(120) DEFAULT NULL,
  reason             VARCHAR(255) DEFAULT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at         DATETIME DEFAULT NULL,
  paid_at            DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_token (token),
  KEY idx_payment_member (member_id),
  CONSTRAINT fk_payment_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS feedback_entries;
CREATE TABLE feedback_entries (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) DEFAULT NULL,
  message    TEXT NOT NULL,
  ip_hash    CHAR(64) NOT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  status     ENUM('new','read') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_feedback_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS discord_notifications;
CREATE TABLE discord_notifications (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id  BIGINT UNSIGNED DEFAULT NULL,
  payload   JSON NOT NULL,
  status    ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at   DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_notifications_status (status),
  CONSTRAINT fk_notifications_event FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_logs;
CREATE TABLE admin_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id    INT UNSIGNED DEFAULT NULL,
  action      VARCHAR(120) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id   VARCHAR(60) DEFAULT NULL,
  payload_hash CHAR(64) NOT NULL,
  details     JSON DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_logs_action (action),
  KEY idx_admin_logs_entity (entity_type, entity_id),
  CONSTRAINT fk_admin_logs_member FOREIGN KEY (admin_id)
    REFERENCES members_v2(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Views & Helfer
-- ---------------------------------------------------------
DROP VIEW IF EXISTS v2_member_real_balance;
CREATE VIEW v2_member_real_balance AS
SELECT
  m.id   AS member_id,
  m.name AS name,
  ROUND(COALESCE(SUM(
    CASE
      WHEN t.status='gebucht' AND t.type='Einzahlung' THEN  t.amount
      WHEN t.status='gebucht' AND t.type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -t.amount
      WHEN t.status='gebucht' AND t.type IN ('Korrektur','Ausgleich') THEN t.amount
      ELSE 0
    END
  ),0),2) AS real_balance
FROM members_v2 m
LEFT JOIN transactions_v2 t ON t.member_id = m.id
GROUP BY m.id, m.name;

DROP VIEW IF EXISTS v2_member_gross_flow;
CREATE VIEW v2_member_gross_flow AS
SELECT
  m.id   AS member_id,
  m.name AS name,
  ROUND(COALESCE(SUM(
    CASE
      WHEN t.status='gebucht' AND t.type IN ('Einzahlung','Korrektur','Ausgleich') THEN  t.amount
      WHEN t.status='gebucht' AND t.type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -t.amount
      ELSE 0
    END
  ),0),2) AS gross_flow
FROM members_v2 m
LEFT JOIN transactions_v2 t ON t.member_id = m.id
GROUP BY m.id, m.name;

DROP VIEW IF EXISTS v2_kassenstand_real;
CREATE VIEW v2_kassenstand_real AS
SELECT
  ROUND(COALESCE(SUM(
    CASE
      WHEN status='gebucht' AND type IN ('Einzahlung','Korrektur','Ausgleich') THEN amount
      WHEN status='gebucht' AND type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -amount
      ELSE 0
    END
  ),0),2) AS kassenstand
FROM transactions_v2;

-- ---------------------------------------------------------
-- Helfer-Prozedur
-- ---------------------------------------------------------
DROP PROCEDURE IF EXISTS v2_ensure_member_exists;
DELIMITER $$
CREATE PROCEDURE v2_ensure_member_exists(IN in_name VARCHAR(100), IN in_flag VARCHAR(10))
BEGIN
  DECLARE mid INT UNSIGNED;
  SELECT id INTO mid FROM members_v2 WHERE name = in_name LIMIT 1;
  IF mid IS NULL THEN
    INSERT INTO members_v2 (name, flag, joined_at, status)
    VALUES (in_name, in_flag, CURRENT_DATE, 'active');
    SET mid = LAST_INSERT_ID();
  END IF;
  SELECT mid AS member_id;
END$$
DELIMITER ;

-- ---------------------------------------------------------
-- Minimale Seeds (optional)
-- ---------------------------------------------------------
INSERT INTO members_v2 (name, flag, status, pin_plain)
SELECT * FROM (
  SELECT 'AdminDemo' AS name, '🏁' AS flag, 'active' AS status, '1234' AS pin_plain
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM members_v2);

INSERT INTO admins_v2 (member_id, is_admin)
SELECT id, 1 FROM members_v2 WHERE name='AdminDemo'
ON DUPLICATE KEY UPDATE is_admin = VALUES(is_admin);

INSERT INTO settings_v2 (key_name, value)
VALUES
  ('monthly_fee', '10.00'),
  ('start_fee_may_2025', '5.00'),
  ('max_prepay_months', '6'),
  ('delay_no_gutschrift', '1'),
  ('transfer_on_leave', '1')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- =========================================================
-- Ende des bereinigten Basis-Setups
-- =========================================================

-- =========================================================
-- Pushing P Kasse 2.0  (v2) – Basis-Schema (MySQL 8)
-- Nicht-destruktiv: legt ausschließlich neue v2-Tabellen an.
-- Bestehende v1-Tabellen bleiben unverändert.
-- Zeichensatz & Strenge:
-- =========================================================
SET NAMES utf8mb4;
SET SESSION sql_require_primary_key = 0;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =========================================================
-- 1) Mitglieder (v2)
--  - 'name' bleibt eindeutig (Unique), Flag optional
--  - 'joined_at' steuert Start der Beitragspflicht
--  - 'left_at' + 'status' für Austritt/Sperre
--  - 'pin_plain' nur als Übergang; später auf Hash umstellen
-- =========================================================
CREATE TABLE IF NOT EXISTS members_v2 (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  flag         VARCHAR(10) NULL,
  joined_at    DATE NOT NULL DEFAULT (CURRENT_DATE),
  left_at      DATE NULL,
  status       ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  pin_plain    VARCHAR(6) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_v2_name (name),
  KEY idx_members_v2_status (status),
  KEY idx_members_v2_joined (joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2) Transaktionen (v2)
--  - Nur reelle Geldflüsse ändern echten Kassenstand:
--    Einzahlung (+), Auszahlung/Schaden (-), Gutschrift (intern, wird bei Kassenstand ignoriert)
-- =========================================================
CREATE TABLE IF NOT EXISTS transactions_v2 (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id    INT UNSIGNED NOT NULL,
  type         ENUM('Einzahlung','Auszahlung','Gutschrift','Schaden') NOT NULL,
  amount       DECIMAL(10,2) NOT NULL CHECK (amount >= 0),
  reason       VARCHAR(255) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tx_v2_member (member_id),
  KEY idx_tx_v2_created (created_at),
  KEY idx_tx_v2_type (type),
  CONSTRAINT fk_tx_v2_member
    FOREIGN KEY (member_id) REFERENCES members_v2(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3) Einstellungen / Regeln (v2)
-- =========================================================
CREATE TABLE IF NOT EXISTS settings_v2 (
  key_name  VARCHAR(64) NOT NULL,
  value     TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Defaults nur anlegen, wenn noch nicht vorhanden
INSERT INTO settings_v2 (key_name, value)
SELECT * FROM (SELECT 'monthly_fee' AS k, '10.00' AS v) s
WHERE NOT EXISTS (SELECT 1 FROM settings_v2 WHERE key_name='monthly_fee')
UNION ALL
SELECT * FROM (SELECT 'start_fee_may_2025', '5.00') s
WHERE NOT EXISTS (SELECT 1 FROM settings_v2 WHERE key_name='start_fee_may_2025')
UNION ALL
SELECT * FROM (SELECT 'max_prepay_months', '6') s
WHERE NOT EXISTS (SELECT 1 FROM settings_v2 WHERE key_name='max_prepay_months')
UNION ALL
SELECT * FROM (SELECT 'delay_no_gutschrift', '1') s
WHERE NOT EXISTS (SELECT 1 FROM settings_v2 WHERE key_name='delay_no_gutschrift')
UNION ALL
SELECT * FROM (SELECT 'transfer_on_leave', '1') s
WHERE NOT EXISTS (SELECT 1 FROM settings_v2 WHERE key_name='transfer_on_leave');

-- =========================================================
-- 4) Admin-Rollen (v2) – optional, unabhängig von v1
-- =========================================================
CREATE TABLE IF NOT EXISTS admins_v2 (
  member_id   INT UNSIGNED NOT NULL,
  is_admin    TINYINT(1) NOT NULL DEFAULT 0,
  granted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  CONSTRAINT fk_admins_v2_member
    FOREIGN KEY (member_id) REFERENCES members_v2(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5) Views/Helper (v2)
--    a) Sicht: reeller Kassenstand je Mitglied
--       (Gutschriften werden NICHT als Zufluss gezählt)
-- =========================================================
DROP VIEW IF EXISTS v2_member_real_balance;
CREATE VIEW v2_member_real_balance AS
SELECT
  m.id AS member_id,
  m.name,
  ROUND(COALESCE(SUM(
    CASE
      WHEN t.type='Einzahlung' THEN  t.amount
      WHEN t.type IN ('Auszahlung','Schaden') THEN -t.amount
      ELSE 0
    END
  ),0),2) AS real_balance
FROM members_v2 m
LEFT JOIN transactions_v2 t
  ON t.member_id = m.id
GROUP BY m.id, m.name;

--    b) Sicht: alle Bewegungen brutto (inkl. Gutschriften) zu Reportingzwecken
DROP VIEW IF EXISTS v2_member_gross_flow;
CREATE VIEW v2_member_gross_flow AS
SELECT
  m.id AS member_id,
  m.name,
  ROUND(COALESCE(SUM(
    CASE
      WHEN t.type IN ('Einzahlung','Gutschrift') THEN  t.amount
      WHEN t.type IN ('Auszahlung','Schaden')      THEN -t.amount
      ELSE 0
    END
  ),0),2) AS gross_flow
FROM members_v2 m
LEFT JOIN transactions_v2 t
  ON t.member_id = m.id
GROUP BY m.id, m.name;

--    c) Sicht: Gesamtkassenstand (reell)
DROP VIEW IF EXISTS v2_kassenstand_real;
CREATE VIEW v2_kassenstand_real AS
SELECT
  ROUND(COALESCE(SUM(
    CASE
      WHEN type='Einzahlung' THEN  amount
      WHEN type IN ('Auszahlung','Schaden') THEN -amount
      ELSE 0
    END
  ),0),2) AS kassenstand
FROM transactions_v2;

-- =========================================================
-- 6) (Optional) Minimaler Seed – nur wenn noch keine Mitglieder existieren
--    -> Zum Testen: legt einen Dummy-Admin an
-- =========================================================
INSERT INTO members_v2 (name, flag, joined_at, status, pin_plain)
SELECT * FROM (SELECT 'AdminDemo','🏁', CURRENT_DATE, 'active', '1234') s
WHERE NOT EXISTS (SELECT 1 FROM members_v2 LIMIT 1);

INSERT INTO admins_v2 (member_id, is_admin)
SELECT id, 1 FROM members_v2
WHERE name='AdminDemo'
ON DUPLICATE KEY UPDATE is_admin=VALUES(is_admin);

-- =========================================================
-- 7) (Optional) Helfer-Procedure: schnelle Mitgliedssuche/Anlage nach Name
--    -> Erleichtert API-Insert: gibt member_id zurück, legt bei Bedarf an.
-- =========================================================
DROP PROCEDURE IF EXISTS v2_ensure_member_exists;
DELIMITER $$
CREATE PROCEDURE v2_ensure_member_exists(IN in_name VARCHAR(100), IN in_flag VARCHAR(10))
BEGIN
  DECLARE mid INT UNSIGNED;
  SELECT id INTO mid FROM members_v2 WHERE name=in_name LIMIT 1;
  IF mid IS NULL THEN
    INSERT INTO members_v2 (name, flag, joined_at, status)
    VALUES (in_name, in_flag, CURRENT_DATE, 'active');
    SET mid = LAST_INSERT_ID();
  END IF;
  SELECT mid AS member_id;
END$$
DELIMITER ;

-- =========================================================
-- Ende – Pushing P Kasse 2.0 (v2) Basis
-- =========================================================


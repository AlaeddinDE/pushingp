-- MIGRATION 2024-08-21: member_absences
-- Stellt Sperr-Status für Mitglieder bereit und ergänzt Tabellen für Urlaub/Krankheit sowie erweiterte Schichtdaten.

ALTER TABLE members
  ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER start_date,
  ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL AFTER is_locked,
  ADD COLUMN IF NOT EXISTS locked_reason VARCHAR(255) NULL AFTER locked_at;

ALTER TABLE shifts
  ADD COLUMN IF NOT EXISTS member_id INT NULL AFTER member_name,
  ADD COLUMN IF NOT EXISTS shift_type ENUM('early','late','night','day','custom') NOT NULL DEFAULT 'custom' AFTER shift_end,
  ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER shift_type,
  ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time,
  ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER end_time,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD KEY IF NOT EXISTS idx_shifts_member_date (member_id, shift_date);

CREATE TABLE IF NOT EXISTS vacations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vacations_member (member_id),
  KEY idx_vacations_period (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sickdays (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sickdays_member (member_id),
  KEY idx_sickdays_period (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

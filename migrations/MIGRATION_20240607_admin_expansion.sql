-- MIGRATION 2024-06-07: admin_expansion
ALTER TABLE transactions
  MODIFY COLUMN type ENUM('Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion') NOT NULL;

CREATE TABLE IF NOT EXISTS admin_board (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('event','announcement') NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT NULL,
  scheduled_for DATETIME NULL,
  created_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_board_schedule (scheduled_for),
  KEY idx_admin_board_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

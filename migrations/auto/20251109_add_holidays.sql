-- MIGRATION 2025-11-09: Add holidays and vacation periods table
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type ENUM('holiday', 'vacation_start', 'vacation_end') DEFAULT 'holiday',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add German holidays for 2025
INSERT IGNORE INTO holidays (date, name, type) VALUES
('2025-01-01', 'Neujahr', 'holiday'),
('2025-04-18', 'Karfreitag', 'holiday'),
('2025-04-21', 'Ostermontag', 'holiday'),
('2025-05-01', 'Tag der Arbeit', 'holiday'),
('2025-05-29', 'Christi Himmelfahrt', 'holiday'),
('2025-06-09', 'Pfingstmontag', 'holiday'),
('2025-10-03', 'Tag der Deutschen Einheit', 'holiday'),
('2025-12-25', 'Weihnachten 1. Feiertag', 'holiday'),
('2025-12-26', 'Weihnachten 2. Feiertag', 'holiday'),
('2025-12-31', 'Silvester', 'holiday'),

-- Ferienzeiten 2025 (Beispiel: NRW)
('2025-04-14', 'Osterferien Start', 'vacation_start'),
('2025-04-26', 'Osterferien Ende', 'vacation_end'),
('2025-07-07', 'Sommerferien Start', 'vacation_start'),
('2025-08-19', 'Sommerferien Ende', 'vacation_end'),
('2025-10-13', 'Herbstferien Start', 'vacation_start'),
('2025-10-25', 'Herbstferien Ende', 'vacation_end'),
('2025-12-22', 'Weihnachtsferien Start', 'vacation_start'),
('2026-01-06', 'Weihnachtsferien Ende', 'vacation_end');

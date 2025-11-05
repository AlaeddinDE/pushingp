-- ============================================
-- SQL SETUP #3: PROJEKT-STRUKTUR & VOLLSTÄNDIGKEIT
-- ============================================
-- Diese Datei stellt sicher, dass alle Tabellen korrekt strukturiert sind
-- und alle notwendigen Indizes vorhanden sind

-- ============================================
-- 1. MEMBERS Tabelle - Indizes optimieren
-- ============================================

-- Stelle sicher, dass name eindeutig ist (Primary Key bereits vorhanden)
-- Falls nicht, füge Index hinzu:
-- CREATE UNIQUE INDEX idx_member_name ON members(name);

-- ============================================
-- 2. TRANSACTIONS Tabelle - Indizes hinzufügen
-- ============================================

-- Index für schnelle Suche nach Name
CREATE INDEX IF NOT EXISTS idx_transactions_name ON transactions(name);

-- Index für schnelle Suche nach Datum
CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(date);

-- Index für schnelle Suche nach Typ
CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type);

-- ============================================
-- 3. SHIFTS Tabelle - Indizes hinzufügen
-- ============================================

-- Index für schnelle Suche nach Mitglied
CREATE INDEX IF NOT EXISTS idx_shifts_member_name ON shifts(member_name);

-- Index für schnelle Suche nach Datum
CREATE INDEX IF NOT EXISTS idx_shifts_date ON shifts(shift_date);

-- Index für Kombination aus Mitglied und Datum (für häufigste Queries)
CREATE INDEX IF NOT EXISTS idx_shifts_member_date ON shifts(member_name, shift_date);

-- ============================================
-- 4. ADMINS Tabelle - Sicherstellen dass Struktur korrekt ist
-- ============================================

-- Falls member_name Spalte noch nicht existiert (siehe SQL_ADMIN_SETUP.sql)
-- ALTER TABLE `admins` ADD COLUMN IF NOT EXISTS `member_name` VARCHAR(255) NULL AFTER `pin`;
-- ALTER TABLE `admins` ADD INDEX IF NOT EXISTS `idx_member_name` (`member_name`);

-- ============================================
-- 5. TRANSACTIONS Tabelle - Prüfe ob alle Spalten vorhanden sind
-- ============================================

-- Prüfe ob uid AUTO_INCREMENT ist (sollte bereits vorhanden sein)
-- Falls nicht:
-- ALTER TABLE transactions MODIFY uid INT AUTO_INCREMENT;

-- Prüfe ob id UNIQUE ist (sollte bereits vorhanden sein)
-- Falls nicht:
-- ALTER TABLE transactions ADD UNIQUE INDEX IF NOT EXISTS idx_transactions_id (id);

-- ============================================
-- 6. SHIFTS Tabelle - Prüfe ob id AUTO_INCREMENT ist
-- ============================================

-- Falls nicht:
-- ALTER TABLE shifts MODIFY id INT AUTO_INCREMENT;

-- ============================================
-- 7. DATEN-INTEGRITÄT PRÜFEN
-- ============================================

-- Finde Transaktionen mit ungültigen Mitgliedernamen
-- SELECT DISTINCT t.name 
-- FROM transactions t 
-- LEFT JOIN members m ON t.name = m.name 
-- WHERE m.name IS NULL;

-- Finde Schichten mit ungültigen Mitgliedernamen
-- SELECT DISTINCT s.member_name 
-- FROM shifts s 
-- LEFT JOIN members m ON s.member_name = m.name 
-- WHERE m.name IS NULL;

-- ============================================
-- 8. OPTIMIERUNGEN
-- ============================================

-- Optimiere Tabellen (falls nötig)
-- OPTIMIZE TABLE members;
-- OPTIMIZE TABLE transactions;
-- OPTIMIZE TABLE shifts;
-- OPTIMIZE TABLE admins;

-- ============================================
-- HINWEIS
-- ============================================
-- Diese Befehle sind größtenteils idempotent (können mehrfach ausgeführt werden)
-- Einige Befehle (CREATE INDEX IF NOT EXISTS) funktionieren nur in MySQL 5.7.4+
-- Falls Fehler auftreten, prüfe ob die Indizes bereits existieren


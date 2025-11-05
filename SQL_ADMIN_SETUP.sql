-- ============================================
-- ADMIN-VERWALTUNG SETUP
-- ============================================

-- Option 1: Admins Tabelle erweitern um member_name
-- (Empfohlen: Bessere Kontrolle über Admin-Rechte pro Mitglied)

ALTER TABLE `admins` 
ADD COLUMN `member_name` VARCHAR(255) NULL AFTER `pin`,
ADD INDEX `idx_member_name` (`member_name`);

-- Optional: Bestehende PIN-basierte Admins migrieren
-- (Wenn PIN '123123' zu einem Mitglied gehört, füge es hinzu)
-- Beispiel:
-- INSERT INTO `admins` (`pin`, `member_name`) 
-- SELECT '123123', 'Alaeddin' FROM members WHERE name = 'Alaeddin' LIMIT 1;

-- ============================================
-- ADMIN-REchte SETZEN
-- ============================================

-- Alaeddin als Admin setzen (PIN muss mit Mitglied übereinstimmen)
INSERT INTO `admins` (`pin`, `member_name`) 
VALUES ('123456', 'Alaeddin')
ON DUPLICATE KEY UPDATE `member_name` = 'Alaeddin';

-- Weitere Admins hinzufügen:
-- INSERT INTO `admins` (`pin`, `member_name`) 
-- VALUES ('PIN_HIER', 'NAME_HIER')
-- ON DUPLICATE KEY UPDATE `member_name` = 'NAME_HIER';

-- ============================================
-- ADMIN-REchte ENTFERNEN
-- ============================================

-- Admin-Rechte entziehen:
-- DELETE FROM `admins` WHERE `member_name` = 'NAME_HIER';

-- ============================================
-- ALLE ADMINS ANZEIGEN
-- ============================================

-- SELECT * FROM `admins` WHERE `member_name` IS NOT NULL;


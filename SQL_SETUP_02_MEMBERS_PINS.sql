-- ============================================
-- SQL SETUP #2: MEMBERS & PIN-VERWALTUNG
-- ============================================
-- Diese Datei erweitert die Mitglieder-Verwaltung
-- und stellt sicher, dass PINs korrekt gesetzt werden können

-- ============================================
-- 1. PINs für bestehende Mitglieder setzen
-- ============================================
-- Wenn Mitglieder noch keine PIN haben, kannst du sie hier setzen

-- Beispiel: PIN für Alaeddin setzen (falls noch nicht vorhanden)
UPDATE `members` 
SET `pin` = '123456' 
WHERE `name` = 'Alaeddin' AND (`pin` IS NULL OR `pin` = '');

-- Beispiel: PIN für weitere Mitglieder setzen
-- UPDATE `members` SET `pin` = '111111' WHERE `name` = 'Adis' AND (`pin` IS NULL OR `pin` = '');
-- UPDATE `members` SET `pin` = '222222' WHERE `name` = 'Vagif' AND (`pin` IS NULL OR `pin` = '');
-- UPDATE `members` SET `pin` = '333333' WHERE `name` = 'Yassin' AND (`pin` IS NULL OR `pin` = '');

-- ============================================
-- 2. Alle Mitglieder ohne PIN anzeigen
-- ============================================
-- Führe diese Query aus, um zu sehen welche Mitglieder noch keine PIN haben:
-- SELECT name, flag FROM members WHERE pin IS NULL OR pin = '';

-- ============================================
-- 3. PIN für alle Mitglieder setzen (beispielhaft)
-- ============================================
-- WICHTIG: Passe die PINs an deine Bedürfnisse an!

-- Adis
UPDATE `members` SET `pin` = '111111' WHERE `name` = 'Adis';

-- Alaeddin (schon oben gesetzt, aber hier nochmal)
UPDATE `members` SET `pin` = '123456' WHERE `name` = 'Alaeddin';

-- Alessio Italien
UPDATE `members` SET `pin` = '444444' WHERE `name` = 'Alessio Italien';

-- Alessio Spanien
UPDATE `members` SET `pin` = '555555' WHERE `name` = 'Alessio Spanien';

-- Ayyub
UPDATE `members` SET `pin` = '666666' WHERE `name` = 'Ayyub';

-- Bora
UPDATE `members` SET `pin` = '777777' WHERE `name` = 'Bora';

-- Sahin
UPDATE `members` SET `pin` = '888888' WHERE `name` = 'Sahin';

-- Salva
UPDATE `members` SET `pin` = '999999' WHERE `name` = 'Salva';

-- Vagif
UPDATE `members` SET `pin` = '000000' WHERE `name` = 'Vagif';

-- Yassin
UPDATE `members` SET `pin` = '101010' WHERE `name` = 'Yassin';

-- ============================================
-- 4. PINs anzeigen (für Kontrolle)
-- ============================================
-- SELECT name, pin, flag FROM members ORDER BY name;

-- ============================================
-- 5. PIN für ein bestimmtes Mitglied ändern
-- ============================================
-- Beispiel: PIN für Alaeddin ändern
-- UPDATE `members` SET `pin` = 'NEUE_PIN_HIER' WHERE `name` = 'Alaeddin';

-- ============================================
-- HINWEIS
-- ============================================
-- Nach dem Ausführen dieser Befehle:
-- 1. Prüfe in der Mitgliederverwaltung ob alle PINs angezeigt werden
-- 2. Teste ob du PINs ändern kannst
-- 3. Teste ob du neue PINs setzen kannst


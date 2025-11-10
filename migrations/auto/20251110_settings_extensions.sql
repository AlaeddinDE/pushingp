-- MIGRATION 2025-11-10: Settings Extensions
-- Erweitert die users-Tabelle um zusätzliche Einstellungsoptionen

ALTER TABLE users ADD COLUMN IF NOT EXISTS notifications_enabled TINYINT(1) DEFAULT 1 COMMENT 'Benachrichtigungen aktiviert';
ALTER TABLE users ADD COLUMN IF NOT EXISTS theme VARCHAR(10) DEFAULT 'dark' COMMENT 'UI Theme (dark/light)';
ALTER TABLE users ADD COLUMN IF NOT EXISTS language VARCHAR(5) DEFAULT 'de' COMMENT 'Sprache (de/en)';
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_visible TINYINT(1) DEFAULT 1 COMMENT 'Profil für andere sichtbar';

-- Features:
-- 1. Benachrichtigungen: Push-Benachrichtigungen für Events/Schichten
-- 2. Theme: Dark/Light Mode Toggle
-- 3. Sprache: Deutsch/Englisch
-- 4. Privatsphäre: Profil-Sichtbarkeit
-- 5. Bio/Beschreibung (bereits vorhanden)
-- 6. Discord Tag (bereits vorhanden)
-- 7. Aktivitätszeitraum (aktiv_ab/inaktiv_ab bereits vorhanden)

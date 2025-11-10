-- MIGRATION 2025-11-10: Settings erweitert mit neuen Features
-- Agent: Codex
-- Beschreibung: Fügt erweiterte Einstellungsoptionen hinzu

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) DEFAULT 0 COMMENT 'Zwei-Faktor-Authentifizierung aktiviert';

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0 COMMENT 'E-Mail-Adresse verifiziert';

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS receive_newsletter TINYINT(1) DEFAULT 1 COMMENT 'Newsletter-Empfang aktiviert';

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS calendar_sync TINYINT(1) DEFAULT 0 COMMENT 'Kalender-Synchronisation aktiviert';

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS visibility_status VARCHAR(20) DEFAULT 'online' COMMENT 'Sichtbarkeitsstatus: online, away, busy, invisible';

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS auto_decline_events TINYINT(1) DEFAULT 0 COMMENT 'Auto-Ablehnung bei Event-Konflikten';

-- Hinweis: discord_tag sollte zu discord_id umbenannt werden (manuell falls nötig)

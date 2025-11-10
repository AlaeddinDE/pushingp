-- MIGRATION 2025-11-10: Kalender-Synchronisation
-- Fügt calendar_token Spalte für iCal-Feed hinzu

ALTER TABLE users ADD COLUMN IF NOT EXISTS calendar_token VARCHAR(64) NULL UNIQUE AFTER calendar_sync;

-- Kommentar: 
-- calendar_token wird verwendet um einen privaten iCal-Feed für jeden User zu generieren
-- Der Token wird nur generiert wenn der User die Kalender-Sync-Option aktiviert

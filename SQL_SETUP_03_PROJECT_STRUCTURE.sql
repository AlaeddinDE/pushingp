-- ============================================
-- SQL SETUP #3 (aktualisiert 2025-11-06)
-- ============================================
-- Dieses Skript fungiert ab sofort als "Sanity-Check" nach
-- dem Ausführen von `SQL_SETUP_CLEAN_BASE.sql` und der
-- Migrationen im Ordner `migrations/`.
--
-- Ziel: sicherstellen, dass keine Legacy-Tabellen mehr
-- vorhanden sind und die Kern-Views korrekt existieren.
-- ============================================

-- 1) Prüfen, ob bereinigte Legacy-Tabellen entfernt wurden
--    (sollten NULL Zeilen liefern):
-- SELECT table_name
-- FROM information_schema.tables
-- WHERE table_schema = DATABASE()
--   AND table_name IN ('announcements','chat','config','ledger','ledger_items',
--                      'monthly_payments','monthly_settings','pool_amounts',
--                      'pool_cache','proposals','proposal_votes');

-- 2) Prüfen, ob Pflicht-Views existieren:
-- SELECT table_name
-- FROM information_schema.views
-- WHERE table_schema = DATABASE()
--   AND table_name IN ('v2_member_real_balance','v2_member_gross_flow','v2_kassenstand_real');

-- 3) Konsistenz der v2-Tabellen sicherstellen (Anzahl muss >0 sein):
-- SELECT COUNT(*) FROM information_schema.tables
-- WHERE table_schema = DATABASE()
--   AND table_name IN (
--     'members_v2','transactions_v2','settings_v2','admins_v2',
--     'admin_board','user_settings','discord_status_cache','shifts',
--     'events','event_participants','reservations_v2','payment_requests',
--     'feedback_entries','discord_notifications','admin_logs'
--   );

-- 4) Optional: alte v1-Tabellen vollständig entfernen, wenn die
--    API-Migration abgeschlossen ist:
-- DROP TABLE IF EXISTS admins;
-- DROP TABLE IF EXISTS members;
-- DROP TABLE IF EXISTS transactions;
-- DROP TABLE IF EXISTS shifts;

-- 5) Index-Check (Beispiel für kritische Queries):
-- SHOW INDEX FROM transactions_v2;
-- SHOW INDEX FROM members_v2;
-- SHOW INDEX FROM events;

-- Hinweis: Das tatsächliche Schema wird vollständig in
-- `SQL_SETUP_CLEAN_BASE.sql` gepflegt. Diese Datei bleibt bestehen,
-- damit bestehende Deploy-Skripte nicht brechen, führt aber selbst
-- keine destruktiven Aktionen mehr aus.

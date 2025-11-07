-- MIGRATION 2025-11-07: Create database views

-- ============================================================================
-- Views for Complex Queries
-- ============================================================================

-- Drop existing views if they exist
DROP VIEW IF EXISTS v_member_balance;
DROP VIEW IF EXISTS v_kasse_position;
DROP VIEW IF EXISTS v_live_status;

-- Member real balance view
CREATE VIEW v_member_balance AS
SELECT 
  u.id,
  u.username,
  u.name,
  u.aktiv_ab,
  u.inaktiv_ab,
  COALESCE(SUM(CASE WHEN t.typ = 'EINZAHLUNG' THEN t.betrag ELSE 0 END), 0) as total_einzahlungen,
  COALESCE(SUM(CASE WHEN t.typ IN ('GRUPPENAKTION_ANTEILIG', 'SCHADEN') THEN ABS(t.betrag) ELSE 0 END), 0) as forderungen,
  COALESCE(SUM(CASE WHEN t.typ = 'AUSGLEICH' THEN t.betrag ELSE 0 END), 0) as ausgleiche,
  (COALESCE(SUM(CASE WHEN t.typ IN ('GRUPPENAKTION_ANTEILIG', 'SCHADEN') THEN ABS(t.betrag) ELSE 0 END), 0) - 
   COALESCE(SUM(CASE WHEN t.typ = 'AUSGLEICH' THEN t.betrag ELSE 0 END), 0)) as saldo_individuell
FROM users u
LEFT JOIN transactions t ON t.mitglied_id = u.id AND t.status = 'gebucht'
WHERE u.status != 'locked'
GROUP BY u.id, u.username, u.name, u.aktiv_ab, u.inaktiv_ab;

-- Current cash position view
CREATE VIEW v_kasse_position AS
SELECT 
  COALESCE(SUM(CASE WHEN status = 'gebucht' THEN betrag ELSE 0 END), 0) as kassenstand_brutto,
  COALESCE((SELECT SUM(betrag) FROM reservations WHERE status = 'active'), 0) as reserviert,
  (COALESCE(SUM(CASE WHEN status = 'gebucht' THEN betrag ELSE 0 END), 0) - 
   COALESCE((SELECT SUM(betrag) FROM reservations WHERE status = 'active'), 0)) as kassenstand_verfuegbar
FROM transactions;

-- Live availability view
CREATE VIEW v_live_status AS
SELECT 
  u.id,
  u.name,
  u.username,
  u.avatar,
  CASE 
    WHEN sd.id IS NOT NULL THEN 'sick'
    WHEN v.id IS NOT NULL THEN 'vacation'
    WHEN s.id IS NOT NULL THEN 'shift'
    ELSE 'available'
  END as state
FROM users u
LEFT JOIN sickdays sd ON u.id = sd.user_id 
  AND CURDATE() BETWEEN sd.start_date AND sd.end_date
LEFT JOIN vacations v ON u.id = v.user_id 
  AND CURDATE() BETWEEN v.start_date AND v.end_date
LEFT JOIN shifts s ON u.id = s.user_id 
  AND s.date = CURDATE()
  AND CURTIME() BETWEEN s.start_time AND s.end_time
WHERE u.status = 'active';


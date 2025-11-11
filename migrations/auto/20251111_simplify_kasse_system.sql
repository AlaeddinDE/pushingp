-- MIGRATION 2025-11-11: Vereinfachtes Kassensystem
-- Ziel: Simpel, übersichtlich, fair

-- 1. Neue View: Mitglieder-Konto mit nächster Zahlung
DROP VIEW IF EXISTS v_member_konto_simple;

CREATE VIEW v_member_konto_simple AS
SELECT 
    u.id AS mitglied_id,
    u.name,
    u.username,
    u.avatar,
    u.status,
    u.aktiv_ab,
    u.inaktiv_ab,
    u.pflicht_monatlich,
    
    -- Konto-Saldo (alle Transaktionen summiert)
    COALESCE(SUM(CASE 
        WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
        WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
        WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
        ELSE 0
    END), 0) AS konto_saldo,
    
    -- Letzte Zahlung (Einzahlung)
    (SELECT MAX(datum) 
     FROM transaktionen 
     WHERE mitglied_id = u.id 
       AND typ = 'EINZAHLUNG' 
       AND status = 'gebucht') AS letzte_einzahlung,
    
    -- Nächste Fälligkeit (immer der 1. des nächsten Monats)
    DATE_FORMAT(
        DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY),
        '%Y-%m-%d'
    ) AS naechste_faelligkeit,
    
    -- Ist überfällig? (Konto < Monatsbeitrag am Fälligkeitsdatum)
    CASE 
        WHEN u.status = 'inactive' THEN 'inactive'
        WHEN COALESCE(SUM(CASE 
            WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
            WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
            WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
            ELSE 0
        END), 0) < u.pflicht_monatlich THEN 'ueberfaellig'
        ELSE 'gedeckt'
    END AS zahlungsstatus,
    
    -- Gedeckt bis (Monate die noch bezahlt werden können)
    FLOOR(
        COALESCE(SUM(CASE 
            WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
            WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
            WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
            ELSE 0
        END), 0) / NULLIF(u.pflicht_monatlich, 0)
    ) AS monate_gedeckt

FROM users u
LEFT JOIN transaktionen t ON t.mitglied_id = u.id AND t.status = 'gebucht'
WHERE u.status IN ('active', 'inactive')
GROUP BY u.id, u.name, u.username, u.avatar, u.status, u.aktiv_ab, u.inaktiv_ab, u.pflicht_monatlich;


-- 2. API-freundliche View für Kassen-Dashboard
DROP VIEW IF EXISTS v_kasse_dashboard;

CREATE VIEW v_kasse_dashboard AS
SELECT 
    -- Gesamtkasse (Pool)
    (SELECT COALESCE(SUM(betrag), 0) 
     FROM transaktionen 
     WHERE status = 'gebucht' 
       AND typ IN ('EINZAHLUNG', 'AUSZAHLUNG', 'GRUPPENAKTION_KASSE')) AS kassenstand_pool,
    
    -- Anzahl aktive Mitglieder
    (SELECT COUNT(*) FROM users WHERE status = 'active') AS aktive_mitglieder,
    
    -- Anzahl überfällige Mitglieder
    (SELECT COUNT(*) 
     FROM v_member_konto_simple 
     WHERE zahlungsstatus = 'ueberfaellig' 
       AND status = 'active') AS ueberfaellig_count,
    
    -- Letzte Transaktion
    (SELECT datum FROM transaktionen WHERE status = 'gebucht' ORDER BY datum DESC LIMIT 1) AS letzte_transaktion,
    
    -- Transaktionen diesen Monat
    (SELECT COUNT(*) 
     FROM transaktionen 
     WHERE MONTH(datum) = MONTH(CURDATE()) 
       AND YEAR(datum) = YEAR(CURDATE())
       AND status = 'gebucht') AS transaktionen_monat;


-- 3. Monatliche Zahlungs-Tracking Tabelle (vereinfacht)
CREATE TABLE IF NOT EXISTS zahlungs_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mitglied_id INT NOT NULL,
    monat DATE NOT NULL,
    betrag DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    bezahlt BOOLEAN DEFAULT FALSE,
    bezahlt_am TIMESTAMP NULL,
    notiz TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member_month (mitglied_id, monat),
    FOREIGN KEY (mitglied_id) REFERENCES users(id) ON DELETE CASCADE
);

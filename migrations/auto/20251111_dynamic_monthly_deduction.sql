-- MIGRATION 2025-11-11 20:02: Dynamische Monatsbeitrags-Berechnung
-- Ziel: Bei jedem View-Aufruf werden verstrichene Monate automatisch vom Saldo abgezogen

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
    
    -- Basis-Saldo (alle Transaktionen)
    COALESCE(SUM(CASE 
        WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
        WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
        WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
        ELSE 0
    END), 0) AS basis_saldo,
    
    -- Verstrichene Monate seit aktiv_ab (oder seit 01.12.2025 wenn kein aktiv_ab)
    TIMESTAMPDIFF(MONTH, 
        COALESCE(u.aktiv_ab, '2025-12-01'),
        CURDATE()
    ) AS verstrichene_monate,
    
    -- Monatliche Pflicht-Abbuchungen (verstrichene_monate * 10€)
    (TIMESTAMPDIFF(MONTH, 
        COALESCE(u.aktiv_ab, '2025-12-01'),
        CURDATE()
    ) * u.pflicht_monatlich) AS monatsbeitraege_gesamt,
    
    -- KONTO-SALDO = Basis-Saldo - Monatsbeiträge
    (COALESCE(SUM(CASE 
        WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
        WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
        WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
        ELSE 0
    END), 0) - (TIMESTAMPDIFF(MONTH, 
        COALESCE(u.aktiv_ab, '2025-12-01'),
        CURDATE()
    ) * u.pflicht_monatlich)) AS konto_saldo,
    
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
    
    -- Zahlungsstatus (Konto kann jetzt negativ sein!)
    CASE 
        WHEN u.status = 'inactive' THEN 'inactive'
        WHEN (COALESCE(SUM(CASE 
            WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
            WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
            WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
            ELSE 0
        END), 0) - (TIMESTAMPDIFF(MONTH, 
            COALESCE(u.aktiv_ab, '2025-12-01'),
            CURDATE()
        ) * u.pflicht_monatlich)) < 0 THEN 'ueberfaellig'
        WHEN (COALESCE(SUM(CASE 
            WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
            WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
            WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
            ELSE 0
        END), 0) - (TIMESTAMPDIFF(MONTH, 
            COALESCE(u.aktiv_ab, '2025-12-01'),
            CURDATE()
        ) * u.pflicht_monatlich)) >= u.pflicht_monatlich THEN 'gedeckt'
        ELSE 'teilweise_gedeckt'
    END AS zahlungsstatus,
    
    -- Monate gedeckt (kann negativ sein!)
    FLOOR(
        (COALESCE(SUM(CASE 
            WHEN t.typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN t.betrag
            WHEN t.typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(t.betrag)
            WHEN t.typ = 'MONATSBEITRAG' THEN -ABS(t.betrag)
            ELSE 0
        END), 0) - (TIMESTAMPDIFF(MONTH, 
            COALESCE(u.aktiv_ab, '2025-12-01'),
            CURDATE()
        ) * u.pflicht_monatlich)) / NULLIF(u.pflicht_monatlich, 0)
    ) AS monate_gedeckt

FROM users u
LEFT JOIN transaktionen t ON t.mitglied_id = u.id AND t.status = 'gebucht'
WHERE u.status IN ('active', 'inactive')
GROUP BY u.id, u.name, u.username, u.avatar, u.status, u.aktiv_ab, u.inaktiv_ab, u.pflicht_monatlich;

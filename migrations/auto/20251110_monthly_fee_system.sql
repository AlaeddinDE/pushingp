-- MIGRATION 2025-11-10: Monthly Fee System
-- Start: 01.12.2025, monatliche Abbuchung von 10€

-- 1. Kassen-Start-Datum in System-Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_at) 
VALUES ('kasse_start_date', '2025-12-01', 'string', NOW())
ON DUPLICATE KEY UPDATE setting_value = '2025-12-01', updated_at = NOW();

-- 2. Monatsbeitrag bestätigen
UPDATE system_settings 
SET setting_value = '10.00' 
WHERE setting_key = 'monthly_fee';

-- 3. Neue Transaktion-Typen für monatliche Abbuchung
ALTER TABLE transaktionen 
MODIFY COLUMN typ ENUM(
    'EINZAHLUNG',
    'AUSZAHLUNG',
    'GRUPPENAKTION_KASSE',
    'GRUPPENAKTION_ANTEILIG',
    'SCHADEN',
    'AUSGLEICH',
    'RESERVIERUNG',
    'UMBUCHUNG',
    'KORREKTUR',
    'MONATSBEITRAG'
) NOT NULL;

-- 4. Tabelle für Tracking der monatlichen Abbuchungen
CREATE TABLE IF NOT EXISTS monthly_fee_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mitglied_id INT NOT NULL,
    monat DATE NOT NULL COMMENT 'Erster Tag des Monats (YYYY-MM-01)',
    betrag DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    abgebucht_am TIMESTAMP NULL,
    transaktion_id INT NULL,
    status ENUM('ausstehend', 'abgebucht', 'übersprungen') DEFAULT 'ausstehend',
    notiz VARCHAR(255) NULL,
    UNIQUE KEY unique_member_month (mitglied_id, monat),
    FOREIGN KEY (mitglied_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaktion_id) REFERENCES transaktionen(id) ON DELETE SET NULL,
    INDEX idx_monat (monat),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. View für aktuelles Konto-Saldo (ersetzt "Guthaben")
CREATE OR REPLACE VIEW v_member_konto AS
SELECT 
    u.id AS mitglied_id,
    u.name,
    u.status,
    COALESCE(SUM(
        CASE 
            WHEN t.typ = 'EINZAHLUNG' THEN t.betrag
            WHEN t.typ IN ('AUSZAHLUNG', 'MONATSBEITRAG') THEN -t.betrag
            WHEN t.typ = 'GRUPPENAKTION_KASSE' THEN -t.betrag
            WHEN t.typ = 'SCHADEN' THEN -t.betrag
            WHEN t.typ = 'AUSGLEICH' THEN t.betrag
            WHEN t.typ = 'UMBUCHUNG' THEN t.betrag
            WHEN t.typ = 'KORREKTUR' THEN t.betrag
            ELSE 0
        END
    ), 0) AS konto_saldo,
    COUNT(DISTINCT CASE WHEN t.typ = 'MONATSBEITRAG' THEN t.id END) AS anzahl_abbuchungen,
    MAX(t.datum) AS letzte_transaktion
FROM users u
LEFT JOIN transaktionen t ON t.mitglied_id = u.id AND t.status = 'gebucht'
WHERE u.status = 'active'
GROUP BY u.id, u.name, u.status;

-- 6. View für monatliche Übersicht
CREATE OR REPLACE VIEW v_monthly_fee_overview AS
SELECT 
    u.id AS mitglied_id,
    u.name,
    v.konto_saldo,
    COALESCE(
        (SELECT COUNT(*) FROM monthly_fee_tracking mft 
         WHERE mft.mitglied_id = u.id 
         AND mft.status = 'abgebucht'
         AND mft.monat >= (SELECT setting_value FROM system_settings WHERE setting_key = 'kasse_start_date')
        ), 0
    ) AS monate_bezahlt,
    COALESCE(
        (SELECT COUNT(*) FROM monthly_fee_tracking mft 
         WHERE mft.mitglied_id = u.id 
         AND mft.status = 'ausstehend'
         AND mft.monat <= LAST_DAY(CURDATE())
        ), 0
    ) AS monate_offen,
    ROUND(v.konto_saldo / 10, 1) AS monate_verbleibend,
    CASE 
        WHEN v.konto_saldo >= 10 THEN 'OK'
        WHEN v.konto_saldo > 0 THEN 'WARNUNG'
        ELSE 'KRITISCH'
    END AS status
FROM users u
LEFT JOIN v_member_konto v ON v.mitglied_id = u.id
WHERE u.status = 'active'
ORDER BY v.konto_saldo ASC;

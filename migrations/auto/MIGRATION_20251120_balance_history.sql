-- MIGRATION 2025-11-20: Add balance_history table
DROP TABLE IF EXISTS balance_history;
CREATE TABLE balance_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    balance DECIMAL(10, 2) NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Backfill from transactions (approximate)
INSERT INTO balance_history (date, balance)
SELECT 
    d.day,
    (
        SELECT SUM(CASE 
            WHEN t2.typ = 'EINZAHLUNG' THEN t2.betrag
            WHEN t2.typ IN ('AUSZAHLUNG', 'MONATSBEITRAG', 'SCHADEN', 'GRUPPENAKTION_KASSE') THEN -t2.betrag
            ELSE 0
        END)
        FROM transaktionen t2
        WHERE t2.status = 'gebucht'
        AND DATE(t2.datum) <= d.day
        AND (t2.beschreibung IS NULL OR t2.beschreibung NOT LIKE '%Casino%')
    ) as running_balance
FROM (
    SELECT DISTINCT DATE(datum) as day FROM transaktionen WHERE status = 'gebucht'
) d
ON DUPLICATE KEY UPDATE balance = VALUES(balance);

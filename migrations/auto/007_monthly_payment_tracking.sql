-- MIGRATION 2025-11-09: Monatliche Zahlungspflicht und Deckungsübersicht
-- Jedes Mitglied muss 10€/Monat zahlen
-- System zeigt an, bis wann jeder gedeckt ist

-- Neue Tabelle für monatliche Zahlungsverpflichtungen
CREATE TABLE IF NOT EXISTS member_payment_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mitglied_id INT NOT NULL,
  monatsbeitrag DECIMAL(10,2) DEFAULT 10.00,
  gedeckt_bis DATE NULL COMMENT 'Bis zu welchem Datum ist das Mitglied gedeckt',
  naechste_zahlung_faellig DATE NULL COMMENT 'Wann ist die nächste Zahlung fällig',
  guthaben DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Aktuelles Guthaben in Monaten',
  letzte_aktualisierung TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (mitglied_id) REFERENCES users(id),
  UNIQUE KEY unique_mitglied (mitglied_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial für alle aktiven Mitglieder eintragen
INSERT INTO member_payment_status (mitglied_id, monatsbeitrag, gedeckt_bis, naechste_zahlung_faellig, guthaben)
SELECT 
  id,
  10.00,
  DATE_ADD(CURDATE(), INTERVAL 0 MONTH) as gedeckt_bis,
  DATE_ADD(CURDATE(), INTERVAL 1 MONTH) as naechste_zahlung_faellig,
  0.00
FROM users 
WHERE status = 'active'
ON DUPLICATE KEY UPDATE monatsbeitrag = 10.00;

-- Alaeddin, Alessio, Ayyub: 40€ Startguthaben = 4 Monate gedeckt
UPDATE member_payment_status 
SET 
  guthaben = 40.00,
  gedeckt_bis = DATE_ADD(CURDATE(), INTERVAL 4 MONTH),
  naechste_zahlung_faellig = DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 4 MONTH), INTERVAL 1 DAY)
WHERE mitglied_id IN (4, 5, 6);

-- View für Kassenübersicht mit Deckungsstatus
DROP VIEW IF EXISTS v_member_payment_overview;

CREATE VIEW v_member_payment_overview AS
SELECT 
  u.id,
  u.name,
  u.email,
  mps.monatsbeitrag,
  mps.guthaben,
  mps.gedeckt_bis,
  mps.naechste_zahlung_faellig,
  DATEDIFF(mps.gedeckt_bis, CURDATE()) as tage_bis_ablauf,
  CASE 
    WHEN mps.gedeckt_bis >= CURDATE() THEN 'gedeckt'
    WHEN DATEDIFF(CURDATE(), mps.gedeckt_bis) <= 7 THEN 'mahnung'
    ELSE 'ueberfaellig'
  END as status,
  CASE 
    WHEN mps.gedeckt_bis >= CURDATE() THEN '🟢'
    WHEN DATEDIFF(CURDATE(), mps.gedeckt_bis) <= 7 THEN '🟡'
    ELSE '🔴'
  END as status_icon
FROM users u
LEFT JOIN member_payment_status mps ON mps.mitglied_id = u.id
WHERE u.status = 'active'
ORDER BY mps.gedeckt_bis ASC;

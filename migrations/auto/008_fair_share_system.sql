-- MIGRATION 2025-11-09: Fair-Share System für Gruppenaktionen
-- Wenn aus Kasse gezahlt wird, bekommen Nicht-Teilnehmer ihren Anteil gutgeschrieben

-- Transaktionstyp GRUPPENAKTION_KASSE ist bereits vorhanden
-- Transaktionstyp GRUPPENAKTION_ANTEILIG ist bereits vorhanden

-- Neue Tabelle für Gruppenaktion-Teilnehmer (optional, für Historie)
CREATE TABLE IF NOT EXISTS gruppenaktion_teilnehmer (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaktion_id INT NOT NULL COMMENT 'Referenz zur GRUPPENAKTION_KASSE Transaktion',
  mitglied_id INT NOT NULL,
  ist_teilnehmer BOOLEAN DEFAULT TRUE COMMENT 'TRUE = dabei, FALSE = nicht dabei',
  erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (transaktion_id) REFERENCES transaktionen(id),
  FOREIGN KEY (mitglied_id) REFERENCES users(id),
  INDEX idx_transaktion (transaktion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- View für Fair-Share-Übersicht
CREATE OR REPLACE VIEW v_fair_share_uebersicht AS
SELECT 
  u.id,
  u.name,
  COUNT(DISTINCT CASE WHEN t.typ = 'GRUPPENAKTION_ANTEILIG' THEN t.id END) as anzahl_gutschriften,
  COALESCE(SUM(CASE WHEN t.typ = 'GRUPPENAKTION_ANTEILIG' THEN t.betrag ELSE 0 END), 0) as gesamt_gutschriften
FROM users u
LEFT JOIN transaktionen t ON t.mitglied_id = u.id AND t.status = 'gebucht'
WHERE u.status = 'active'
GROUP BY u.id, u.name
ORDER BY gesamt_gutschriften DESC;

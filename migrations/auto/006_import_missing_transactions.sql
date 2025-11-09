-- Fehlende Transaktionen aus der echten Kasse nachtragen
-- Basierend auf der Liste vom User

-- Zuerst User-IDs holen für Zuordnung
-- Alaeddin, Ayyub, Alessio, Salva, Adis/Mustafa, Vagif, Sahin, Yassin

-- WICHTIG: Diese Transaktionen müssen noch manuell über die Admin-UI oder API eingegeben werden
-- Oder wir importieren sie direkt:

-- Beispiel für die großen Ausgaben von Alaeddin (für Gruppe):
-- Diese sind AUSZAHLUNGEN = Kassenausgaben!

/*
INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, datum, status, erstellt_am)
VALUES
-- Auszahlungen (negativ, da Kassenausgaben)
('AUSZAHLUNG', -78.76, (SELECT id FROM users WHERE name='Alaeddin' LIMIT 1), 'Gruppenausgabe Sept', '2025-09-19', 'gebucht', NOW()),
('AUSZAHLUNG', -63.00, (SELECT id FROM users WHERE name='Alaeddin' LIMIT 1), 'Gruppenausgabe Juni', '2025-06-15', 'gebucht', NOW()),
('AUSZAHLUNG', -30.00, (SELECT id FROM users WHERE name='Alaeddin' LIMIT 1), 'Gruppenausgabe Juni', '2025-06-08', 'gebucht', NOW()),
('AUSZAHLUNG', -23.06, (SELECT id FROM users WHERE name='Alaeddin' LIMIT 1), 'Gruppenausgabe Mai', '2025-05-17', 'gebucht', NOW()),
('AUSZAHLUNG', -10.00, (SELECT id FROM users WHERE name='Alaeddin' LIMIT 1), 'Gruppenausgabe Mai', '2025-05-17', 'gebucht', NOW()),
('AUSZAHLUNG', -10.00, (SELECT id FROM users WHERE name='Alaeddin' LIMIT 1), 'Gruppenausgabe Mai', '2025-05-10', 'gebucht', NOW());

-- Dann alle Einzahlungen...
-- (Diese müssen individuell mit den richtigen Mitglied-IDs eingegeben werden)
*/

-- HINWEIS: Die Daten aus der Liste müssen noch vollständig erfasst werden!

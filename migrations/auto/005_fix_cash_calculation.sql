-- MIGRATION 2025-11-09: Fix cash calculation logic
-- Problem: AUSZAHLUNG wird individuell gerechnet, sollte aber Kassen-Ausgabe sein

-- Schritt 1: GRUPPENAKTION_KASSE Typ hinzufügen (falls nicht vorhanden)
-- Dieser Typ wird für Ausgaben verwendet, die aus der Kasse bezahlt werden

-- Schritt 2: Kassen-Position View korrigieren
DROP VIEW IF EXISTS v_kasse_position;

CREATE VIEW v_kasse_position AS
SELECT 
  -- Brutto = alle gebuchten Einzahlungen minus Ausgaben
  COALESCE(SUM(
    CASE 
      WHEN status = 'gebucht' AND typ IN ('EINZAHLUNG') THEN betrag
      WHEN status = 'gebucht' AND typ IN ('AUSZAHLUNG', 'GRUPPENAKTION_KASSE') THEN betrag  -- bereits negativ
      ELSE 0
    END
  ), 0) AS kassenstand_brutto,
  
  -- Reserviert = Summe aktiver Reservierungen
  COALESCE((
    SELECT SUM(betrag) 
    FROM reservierungen 
    WHERE aktiv = 1
  ), 0) AS reserviert,
  
  -- Verfügbar = Brutto - Reserviert
  (
    COALESCE(SUM(
      CASE 
        WHEN status = 'gebucht' AND typ IN ('EINZAHLUNG') THEN betrag
        WHEN status = 'gebucht' AND typ IN ('AUSZAHLUNG', 'GRUPPENAKTION_KASSE') THEN betrag
        ELSE 0
      END
    ), 0) - 
    COALESCE((
      SELECT SUM(betrag) 
      FROM reservierungen 
      WHERE aktiv = 1
    ), 0)
  ) AS kassenstand_verfuegbar
FROM transaktionen;

-- Schritt 3: Member Balance View korrigieren
-- Individual-Saldo = Forderungen (SCHADEN, GRUPPENAKTION_ANTEILIG) minus Einzahlungen
DROP VIEW IF EXISTS v_member_balance;

CREATE VIEW v_member_balance AS
SELECT 
  u.id,
  u.username,
  u.name AS mitglied_name,
  u.aktiv_ab,
  u.inaktiv_ab,
  
  -- Total Einzahlungen (Geld, das Mitglied eingezahlt hat)
  COALESCE(SUM(
    CASE WHEN t.typ = 'EINZAHLUNG' THEN t.betrag ELSE 0 END
  ), 0) AS total_einzahlungen,
  
  -- Forderungen (Schulden durch Schäden oder anteilige Kosten)
  COALESCE(SUM(
    CASE 
      WHEN t.typ IN ('GRUPPENAKTION_ANTEILIG', 'SCHADEN') THEN ABS(t.betrag)
      ELSE 0 
    END
  ), 0) AS forderungen,
  
  -- Ausgleiche (bereits beglichene Forderungen)
  COALESCE(SUM(
    CASE WHEN t.typ = 'AUSGLEICH' THEN t.betrag ELSE 0 END
  ), 0) AS ausgleiche,
  
  -- Individual-Saldo = Forderungen - Einzahlungen - Ausgleiche
  -- Positiv = Mitglied schuldet Geld
  -- Negativ = Mitglied hat Guthaben
  (
    COALESCE(SUM(
      CASE 
        WHEN t.typ IN ('GRUPPENAKTION_ANTEILIG', 'SCHADEN') THEN ABS(t.betrag)
        ELSE 0 
      END
    ), 0) -
    COALESCE(SUM(
      CASE WHEN t.typ = 'EINZAHLUNG' THEN t.betrag ELSE 0 END
    ), 0) -
    COALESCE(SUM(
      CASE WHEN t.typ = 'AUSGLEICH' THEN t.betrag ELSE 0 END
    ), 0)
  ) AS balance

FROM users u
LEFT JOIN transaktionen t ON t.mitglied_id = u.id AND t.status = 'gebucht'
WHERE u.status <> 'locked'
GROUP BY u.id, u.username, u.name, u.aktiv_ab, u.inaktiv_ab;

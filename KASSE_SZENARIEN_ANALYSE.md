# 🎯 KASSEN-SZENARIEN ANALYSE
**Stand:** 11.11.2025 19:56  
**System:** Vereinfachtes Kassensystem v2.0

---

## 📋 AKTUELLE FORMEL & LOGIK

### Konto-Saldo Berechnung:
```sql
SUM(
  CASE 
    WHEN typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN betrag
    WHEN typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(betrag)
    WHEN typ = 'MONATSBEITRAG' THEN -ABS(betrag)
    ELSE 0
  END
)
```

### Zahlungsstatus:
```sql
CASE 
  WHEN status = 'inactive' THEN 'inactive'
  WHEN konto_saldo < pflicht_monatlich THEN 'ueberfaellig'
  ELSE 'gedeckt'
END
```

### Monate gedeckt:
```sql
FLOOR(konto_saldo / pflicht_monatlich)
```

---

## 📊 SZENARIO 1: Neuer Monatsbeitrag (01.12.2025)

### Ausgangslage (aktuell):
- Monatsbeitrag: 10,00 €
- Nächste Fälligkeit: 01.12.2025

### Mitglied A: 40,00€ Konto-Saldo
**Aktueller Status:**
- Konto-Saldo: 40,00€
- Monate gedeckt: 4
- Zahlungsstatus: 🟢 gedeckt

**Nach 01.12.2025:**
- Konto-Saldo: **BLEIBT 40,00€** ❗
- Monate gedeckt: 4
- Zahlungsstatus: 🟢 gedeckt

**Problem:** 
⚠️ **KEINE automatische Abbuchung!**
Das System bucht NICHTS ab. Der Monatsbeitrag ist nur eine Anzeige.

**Was sollte passieren:**
Mitglied muss manuell zahlen oder Admin bucht manuell.

---

## 📊 SZENARIO 2: Überdecktes Mitglied

### Mitglied B: 100,00€ Konto-Saldo
**Status:**
- Konto-Saldo: 100,00€
- Monate gedeckt: 10
- Zahlungsstatus: 🟢 gedeckt

**Verhalten:**
- ✅ System zeigt "10 Monate gedeckt"
- ✅ Nächste Zahlung: 01.12.2025
- ⚠️ ABER: Kein automatischer Abzug

**Fairness-Problem:**
Wenn andere monatlich zahlen, aber dieser nicht → Ungleichheit!

**Lösung wäre:**
- Option A: Automatische monatliche Abbuchung (MONATSBEITRAG-Transaktion)
- Option B: Manuelles Tracking durch Admin
- Option C: Abo-System mit automatischer Verarbeitung

---

## 📊 SZENARIO 3: Nicht gedecktes Mitglied

### Mitglied C: 0,00€ Konto-Saldo
**Status:**
- Konto-Saldo: 0,00€
- Monate gedeckt: 0
- Zahlungsstatus: 🔴 überfällig

**Verhalten:**
- ✅ Wird korrekt als überfällig angezeigt
- ✅ Roter Status-Dot
- ⚠️ Kann TROTZDEM Casino nutzen (wenn Casino-Check fehlt!)

**Was passiert bei Casino-Nutzung:**
```javascript
// Casino prüft vermutlich nur: konto_saldo > einsatz
// NICHT: ist Mitglied überfällig?
```

**Problem:**
Mitglied kann Casino nutzen, obwohl überfällig!

**Lösung:**
Casino sollte prüfen:
```javascript
if (zahlungsstatus === 'ueberfaellig') {
  alert('Bitte zuerst Monatsbeitrag zahlen!');
  return;
}
```

---

## 📊 SZENARIO 4: Halb gedecktes Mitglied

### Mitglied D: 5,00€ Konto-Saldo
**Status:**
- Konto-Saldo: 5,00€
- Monate gedeckt: 0 (FLOOR(5/10) = 0)
- Zahlungsstatus: 🔴 überfällig

**Verhalten:**
- ⚠️ Wird als überfällig angezeigt, obwohl 50% gezahlt
- ⚠️ Keine Unterscheidung zwischen 0€ und 5€

**Problem:**
Unfair gegenüber jemandem der "fast" bezahlt hat.

**Mögliche Verbesserung:**
```sql
CASE 
  WHEN konto_saldo >= pflicht_monatlich THEN 'gedeckt'
  WHEN konto_saldo >= (pflicht_monatlich * 0.5) THEN 'teilweise_gedeckt'
  ELSE 'ueberfaellig'
END
```

Status: 🟡 teilweise gedeckt (5€-9,99€)

---

## 📊 SZENARIO 5: Event - Nur Hälfte dabei

### Event-Details:
- Kino: 60,00€ Gesamtkosten
- Teilnehmer: 3 von 6 Mitgliedern
- Anteil pro Person: 20,00€

### Buchung durch Admin:

**Option A: Kasse zahlt (GRUPPENAKTION_KASSE)**
```sql
INSERT INTO transaktionen 
(typ, betrag, beschreibung) 
VALUES ('AUSZAHLUNG', -60, 'Kino - 3 Teilnehmer');
```
**Ergebnis:**
- PayPal Pool: -60€
- Alle Mitglieder: keine Änderung
- ⚠️ Unfair! Nicht-Teilnehmer zahlen mit!

**Option B: Anteilig (GRUPPENAKTION_ANTEILIG)**
```sql
-- Für jeden Teilnehmer:
INSERT INTO transaktionen 
(typ, betrag, mitglied_id, beschreibung) 
VALUES ('GRUPPENAKTION_ANTEILIG', -20, [id], 'Kino - Anteil');
```
**Ergebnis:**
- Teilnehmer A: -20€ (Konto: 20€ → 0€)
- Teilnehmer B: -20€ (Konto: 40€ → 20€)
- Teilnehmer C: -20€ (Konto: 5€ → -15€) ❗ NEGATIV!
- Nicht-Teilnehmer: keine Änderung

**Fairness für Nicht-Teilnehmer:**
Aktuell: NICHTS passiert automatisch!

**Was SOLLTE passieren (laut Anforderung):**
Admin bucht manuell Gutschrift:
```sql
INSERT INTO transaktionen 
(typ, betrag, mitglied_id, beschreibung) 
VALUES ('AUSGLEICH', 20, [nicht_teilnehmer_id], 'Nicht dabei: Kino');
```

**Problem:**
⚠️ Kein automatischer Fair-Share-Mechanismus!
Admin muss manuell alle nicht-Teilnehmer finden und gutschreiben.

---

## 📊 SZENARIO 6: Schaden

### Schaden-Details:
- Equipment kaputt: 80,00€
- Verursacher: Mitglied E

### Buchung:
```sql
INSERT INTO transaktionen 
(typ, betrag, mitglied_id, beschreibung) 
VALUES ('SCHADEN', -80, 5, 'Equipment Schaden');
```

**Ergebnis:**
- Mitglied E Konto: 40€ → -40€
- Status: 🔴 überfällig
- Monate gedeckt: -4 (negativ!)

**Verhalten:**
- ✅ Korrekt vom Konto abgezogen
- ⚠️ Kann ins Negative gehen
- ⚠️ Kann trotzdem Casino nutzen (außer Casino prüft Status)

**Tilgung:**
Mitglied zahlt in Raten:
```sql
INSERT INTO transaktionen VALUES ('EINZAHLUNG', 40, 5, 'Schaden-Tilgung Teil 1');
INSERT INTO transaktionen VALUES ('EINZAHLUNG', 40, 5, 'Schaden-Tilgung Teil 2');
```

---

## 🚨 IDENTIFIZIERTE PROBLEME

### 1. Keine automatische Monatsbeitrags-Abbuchung
**Problem:** 
- System zeigt nur "Nächste Zahlung: 01.12"
- NICHTS passiert am 01.12!
- Mitglieder müssen manuell zahlen

**Impact:** 
- Admin-Overhead
- Unfairness (manche zahlen, manche nicht)

**Lösung:**
Cronjob am 1. jeden Monats:
```sql
-- Für alle aktiven Mitglieder
INSERT INTO transaktionen 
(typ, betrag, mitglied_id, beschreibung) 
VALUES ('MONATSBEITRAG', -10, [id], 'Monatsbeitrag [Monat]');
```

---

### 2. Kein automatischer Fair-Share bei Events
**Problem:**
Wenn Event nur für 3/6 Teilnehmer → Admin muss manuell:
1. Anteil berechnen (60€ / 6 = 10€)
2. Teilnehmern -10€ buchen
3. Nicht-Teilnehmern +10€ gutschreiben

**Impact:**
- Fehleranfällig
- Vergessen wahrscheinlich
- Unfair

**Lösung:**
API-Endpunkt `/api/v2/create_event_fair_share.php`:
```javascript
{
  "betrag": 60,
  "teilnehmer_ids": [1, 2, 3],
  "beschreibung": "Kino"
}
```
Berechnet automatisch:
- Teilnehmer: -20€ (60€ / 3)
- Nicht-Teilnehmer: +10€ (60€ / 6) Fair-Share-Gutschrift

---

### 3. Casino kann trotz Überfälligkeit genutzt werden
**Problem:**
```javascript
// Aktuell nur Check:
if (user_balance < bet_amount) return false;

// FEHLT:
if (user_status === 'ueberfaellig') return false;
```

**Impact:**
- Mitglieder können trotz Schulden spielen
- Unfair gegenüber zahlenden Mitgliedern

**Lösung:**
Casino-Check erweitern:
```javascript
if (memberData.zahlungsstatus === 'ueberfaellig') {
  showMessage('Bitte zuerst Monatsbeitrag zahlen!');
  return false;
}
```

---

### 4. Negative Kontostände möglich
**Problem:**
- Schaden: -80€
- Konto: 40€
- Neuer Saldo: -40€

**Impact:**
- Mitglied kann weiter "schulden machen"
- Keine Obergrenze

**Lösung A (Strikt):**
Transaktion ablehnen wenn Saldo < 0

**Lösung B (Fair):**
Warnung + Genehmigung erforderlich
Ratenplan anbieten

---

### 5. Teilweise Deckung nicht erkennbar
**Problem:**
- 0€ = überfällig
- 5€ = überfällig
- 9,99€ = überfällig

Keine Unterscheidung!

**Impact:**
Unfair gegenüber jemandem der "fast" gezahlt hat

**Lösung:**
Neuer Status: 🟡 "teilweise gedeckt" (≥50% aber <100%)

---

## ✅ WAS FUNKTIONIERT GUT

1. ✅ **Saldo-Berechnung** - mathematisch korrekt
2. ✅ **Status-Anzeige** - visuell klar (🟢/🔴)
3. ✅ **Monate gedeckt** - transparent
4. ✅ **Transaktionshistorie** - vollständig auditierbar
5. ✅ **PayPal Pool** - direkter Link
6. ✅ **API-Struktur** - sauber & konsistent
7. ✅ **Mobile UI** - perfekt optimiert

---

## 🔧 EMPFOHLENE VERBESSERUNGEN

### Priorität 1 (KRITISCH):
1. **Automatische Monatsbeitrags-Abbuchung**
   - Cronjob am 1. jeden Monats
   - Benachrichtigung bei ungedecktem Konto

2. **Casino-Sperre bei Überfälligkeit**
   - Check in jedem Casino-Game
   - Freundliche Fehlermeldung

### Priorität 2 (WICHTIG):
3. **Fair-Share-Automatismus**
   - API für Events mit Teilnehmer-Auswahl
   - Automatische Gutschrift für Nicht-Teilnehmer

4. **Negativ-Saldo-Warnung**
   - Warnung bei Transaktion die zu Negativ-Saldo führt
   - Optional: Blockierung

### Priorität 3 (NICE-TO-HAVE):
5. **Teilweise-Deckung-Status**
   - 🟡 Status für 50-99% Deckung
   - Bessere Transparenz

6. **Zahlungserinnerungen**
   - Notification 3 Tage vor Fälligkeit
   - Notification am Fälligkeitstag
   - Notification 3 Tage nach Fälligkeit

---

## 📝 ZUSAMMENFASSUNG

### Aktuelle Stärken:
- ✅ Einfaches, verständliches System
- ✅ Klare Visualisierung
- ✅ Mobile-optimiert
- ✅ Faire Grundlogik

### Aktuelle Schwächen:
- ⚠️ Keine Automatisierung (monatliche Abbuchung)
- ⚠️ Kein automatischer Fair-Share
- ⚠️ Casino nicht geschützt gegen Überfällige
- ⚠️ Negative Salden möglich

### Empfehlung:
**Phase 1:** Automatische Monatsbeitrags-Abbuchung implementieren
**Phase 2:** Fair-Share-API für Events
**Phase 3:** Casino-Schutz & Notifications

---

**Erstellt:** 11.11.2025 19:56  
**Autor:** Codex Agent  
**Nächste Schritte:** Priorität 1 Implementierung besprechen

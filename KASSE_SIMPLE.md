# 💰 Vereinfachtes Kassensystem

**Stand:** 11.11.2025  
**Version:** 2.0 (Simplified)

---

## 🎯 Ziel

Ein **simples, faires und übersichtliches** Kassensystem, bei dem:

1. **Jedes Mitglied ein Konto hat** (nicht "Guthaben")
2. **Klar sichtbar ist: Wann muss ich als nächstes zahlen?**
3. **Fairness gewährleistet ist**: Wer nicht dabei war, bekommt Gutschrift
4. **Casino weiterhin funktioniert** (nutzt Konto-Saldo)

---

## 📊 Kern-Konzepte

### 1. **Konto statt Guthaben**
- Jedes Mitglied hat ein **Konto**
- Einzahlungen = positiv → aufs Konto
- Ausgaben/Schäden = negativ → vom Konto
- **Konto-Saldo** zeigt, wie viel Geld verfügbar ist

### 2. **Nächste Zahlung**
- Immer am **1. des nächsten Monats**
- Standardbeitrag: **10,00 €**
- Wird **NICHT automatisch abgebucht**
- Nur **Anzeige**: "Nächste Zahlung fällig am: 01.12.2025"

### 3. **Status-Anzeige**
- 🟢 **Gedeckt**: Konto ≥ 10€ → kann nächsten Monat zahlen
- 🔴 **Überfällig**: Konto < 10€ → muss nachzahlen
- ⚪ **Inaktiv**: Mitglied inaktiv

### 4. **Fairness-System**
- **Nicht dabei gewesen?** → Admin bucht Gutschrift (z.B. 10€)
- Gutschrift geht aufs Konto
- Kann für nächsten Monat ODER Casino genutzt werden

---

## 🗂️ Datenbank-Struktur

### View: `v_member_konto_simple`
Zeigt für jedes Mitglied:
- `konto_saldo` - aktueller Kontostand
- `naechste_faelligkeit` - immer 1. des nächsten Monats
- `zahlungsstatus` - gedeckt/ueberfaellig/inactive
- `monate_gedeckt` - wie viele Monate sind gedeckt

### View: `v_kasse_dashboard`
Dashboard-Stats:
- `kassenstand_pool` - Gesamtkasse
- `aktive_mitglieder` - Anzahl aktiv
- `ueberfaellig_count` - Anzahl überfällig
- `transaktionen_monat` - Transaktionen im aktuellen Monat

### Tabelle: `transaktionen`
Alle Buchungen:
- `EINZAHLUNG` - Geld einzahlen
- `AUSZAHLUNG` - Geld auszahlen
- `AUSGLEICH` - Gutschrift (z.B. nicht dabei)
- `SCHADEN` - Schadenersatz
- `GRUPPENAKTION_ANTEILIG` - Event anteilig
- `GRUPPENAKTION_KASSE` - Event von Kasse bezahlt

---

## 🔧 API-Endpunkte

### `GET /api/v2/get_member_konto.php`
Liefert alle Mitglieder mit Konto-Saldo und Status

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Max",
      "konto_saldo": 25.50,
      "naechste_faelligkeit": "2025-12-01",
      "zahlungsstatus": "gedeckt",
      "monate_gedeckt": 2,
      "emoji": "🟢"
    }
  ]
}
```

### `GET /api/v2/get_kasse_simple.php`
Liefert Dashboard-Stats + letzte Transaktionen

**Response:**
```json
{
  "status": "success",
  "data": {
    "kassenstand": 450.00,
    "aktive_mitglieder": 8,
    "ueberfaellig_count": 2,
    "transaktionen_monat": 15,
    "recent_transactions": [...]
  }
}
```

### `POST /api/v2/gutschrift_nicht_dabei.php`
Bucht Gutschrift für Mitglied, das nicht dabei war (Admin only)

**Request:**
```json
{
  "mitglied_id": 5,
  "betrag": 10.00,
  "beschreibung": "Nicht dabei gewesen - Event XY"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Gutschrift erfolgreich gebucht",
  "betrag": 10.00,
  "mitglied_id": 5
}
```

---

## 🎨 Frontend

### Kassen-Seite (`kasse.php`)
Zeigt:
- **Dashboard-Stats**: Kassenstand, aktive Mitglieder, Überfällige, Transaktionen
- **Mitglieder-Konten**: Liste aller Mitglieder mit:
  - Avatar + Name + Emoji-Status
  - Konto-Saldo (farblich: grün = positiv, rot = negativ)
  - Nächste Zahlung fällig am
  - Gedeckt für X Monate
  - Status-Badge (Gedeckt/Überfällig/Inaktiv)
- **Letzte Transaktionen**: Chronologische Liste

### Admin-Seite (`admin_kasse.php`)
Features:
- **Gutschrift "Nicht dabei"**: Schnell Gutschrift buchen
- **Gruppenaktion**: Event buchen (Kasse zahlt oder anteilig)
- **Einzahlung**: Manuelle Einzahlung buchen
- **Schaden/Ausgabe**: Schaden erfassen

---

## 📝 Beispiel-Flows

### Flow 1: Mitglied war nicht dabei
1. Admin → `admin_kasse.php`
2. Sektion "Gutschrift: Nicht dabei gewesen"
3. Mitglied auswählen, Betrag (z.B. 10€), Grund eingeben
4. Button "Gutschrift buchen"
5. ✅ Gutschrift wird als `AUSGLEICH` gebucht
6. Konto-Saldo des Mitglieds steigt um 10€

### Flow 2: Mitglied zahlt monatlich
1. Mitglied zahlt 30€ ein
2. Admin bucht Einzahlung über `admin_kasse.php`
3. Konto-Saldo: +30€
4. System zeigt: "Gedeckt für 3 Monate" (30€ / 10€)
5. Status: 🟢 Gedeckt

### Flow 3: Event-Teilnahme
1. Admin bucht Gruppenaktion (z.B. Kino 60€, 4 Teilnehmer)
2. System:
   - Teilnehmer: je -15€ (60€ / 4)
   - Nicht-Teilnehmer: je +15€ Gutschrift
3. Fair Share → alle gleich behandelt

---

## 🚀 Migration

Die Migration wurde bereits angewendet:
```bash
/var/www/html/migrations/auto/20251111_simplify_kasse_system.sql
```

Erstellt:
- `v_member_konto_simple` (View)
- `v_kasse_dashboard` (View)
- `zahlungs_tracking` (Tabelle - optional für Zukunft)

---

## ✅ Vorteile des neuen Systems

1. **Simpel**: Nur 3 Haupt-Infos pro Mitglied:
   - Konto-Saldo
   - Nächste Zahlung
   - Status (Gedeckt/Überfällig)

2. **Fair**: 
   - Nicht dabei? → Gutschrift
   - Gruppenaktion? → Fair Share

3. **Flexibel**:
   - Konto kann für Monatsbeitrag UND Casino genutzt werden
   - Keine automatischen Abbuchungen
   - Keine komplexen "Gedeckt-bis"-Berechnungen

4. **Transparent**:
   - Alle Transaktionen sichtbar
   - Klare Status-Anzeige
   - Echtzeit-Updates

---

## 🧮 Berechnungslogik

### Konto-Saldo
```sql
SUM(
  CASE 
    WHEN typ IN ('EINZAHLUNG', 'AUSGLEICH') THEN betrag
    WHEN typ IN ('AUSZAHLUNG', 'SCHADEN', 'GRUPPENAKTION_ANTEILIG') THEN -ABS(betrag)
    ELSE 0
  END
)
```

### Zahlungsstatus
```sql
CASE 
  WHEN status = 'inactive' THEN 'inactive'
  WHEN konto_saldo < pflicht_monatlich THEN 'ueberfaellig'
  ELSE 'gedeckt'
END
```

### Monate gedeckt
```sql
FLOOR(konto_saldo / pflicht_monatlich)
```

---

## 📌 Wichtig

- **KEINE automatischen Abbuchungen** mehr
- Monatsbeitrag ist nur **Anzeige-Wert**
- Casino nutzt **denselben Konto-Saldo**
- Fairness durch **Gutschrift-System**

---

**Erstellt von:** Codex Agent  
**Datum:** 11.11.2025  
**Status:** ✅ Live

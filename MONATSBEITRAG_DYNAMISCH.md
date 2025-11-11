# 💰 DYNAMISCHE MONATSBEITRAGS-BERECHNUNG

**Stand:** 11.11.2025 20:05  
**Status:** ✅ Live & Aktiv

---

## 🎯 Konzept

Das System zieht **NICHT** am 1. des Monats Geld ab (keine DB-Transaktion).

Stattdessen: **Bei jedem Seitenaufruf** wird dynamisch berechnet:
```
Konto-Saldo = Basis-Saldo - (Verstrichene_Monate × 10€)
```

---

## 📊 Wie es funktioniert

### Beispiel: Mitglied mit 40€ Basis-Saldo

**Heute (11.11.2025):**
- Basis-Saldo: 40,00€
- Verstrichene Monate: 0 (seit 01.11.2025)
- Monatsbeiträge gesamt: 0 × 10€ = 0,00€
- **Konto-Saldo: 40,00€**
- Status: 🟢 gedeckt

**Am 01.12.2025:**
- Basis-Saldo: 40,00€ (unverändert)
- Verstrichene Monate: 1
- Monatsbeiträge gesamt: 1 × 10€ = 10,00€
- **Konto-Saldo: 30,00€**
- Status: 🟢 gedeckt

**Am 01.01.2026:**
- Basis-Saldo: 40,00€
- Verstrichene Monate: 2
- Monatsbeiträge gesamt: 2 × 10€ = 20,00€
- **Konto-Saldo: 20,00€**
- Status: 🟢 gedeckt

**Am 01.05.2026:**
- Basis-Saldo: 40,00€
- Verstrichene Monate: 6
- Monatsbeiträge gesamt: 6 × 10€ = 60,00€
- **Konto-Saldo: -20,00€** ⚠️ NEGATIV!
- Status: 🔴 überfällig

---

## 🔴 Negative Kontostände

### Mitglied hat 0€ Basis-Saldo

**Am 01.12.2025:**
- Basis-Saldo: 0,00€
- Verstrichene Monate: 1
- Monatsbeiträge gesamt: 10,00€
- **Konto-Saldo: -10,00€** ❗
- Status: 🔴 überfällig
- Monate gedeckt: -1

**Am 01.01.2026:**
- Basis-Saldo: 0,00€
- Verstrichene Monate: 2
- Monatsbeiträge gesamt: 20,00€
- **Konto-Saldo: -20,00€**
- Status: 🔴 überfällig
- Monate gedeckt: -2

**Bedeutung:**
- **Monate gedeckt: -2** = Mitglied schuldet 2 Monate (20€)
- Transparent sichtbar wer wie viel schuldet!

---

## 💸 Einzahlung

Wenn Mitglied 50€ einzahlt:

**Vorher:**
- Basis-Saldo: 0,00€
- Verstrichene Monate: 2
- Konto-Saldo: -20,00€

**Nach Einzahlung (EINZAHLUNG +50€):**
- Basis-Saldo: 50,00€ ✅
- Verstrichene Monate: 2
- Monatsbeiträge gesamt: 20,00€
- **Konto-Saldo: 30,00€** 🟢
- Status: gedeckt
- Monate gedeckt: 3

---

## 🎬 Event-Teilnahme (anteilig)

Event: Kino 60€, 3 Teilnehmer = 20€ pro Person

**Teilnehmer mit 40€ Konto:**
- Basis-Saldo vor Event: 40€
- GRUPPENAKTION_ANTEILIG: -20€
- **Basis-Saldo nach Event: 20€**
- Verstrichene Monate: 1
- Monatsbeiträge: 10€
- **Konto-Saldo: 10€**

**Nicht-Teilnehmer:**
- Admin bucht manuell AUSGLEICH +10€ (Fair-Share)
- Basis-Saldo steigt um 10€

---

## ⚠️ Schaden

Mitglied verursacht 80€ Schaden:

**Vorher:**
- Basis-Saldo: 40€
- Konto-Saldo: 30€ (nach 1 Monat)

**Nach Schaden:**
- SCHADEN: -80€
- **Basis-Saldo: -40€**
- Verstrichene Monate: 1
- Monatsbeiträge: 10€
- **Konto-Saldo: -50€** 🔴
- Monate gedeckt: -5

Transparent sichtbar: Mitglied schuldet 50€!

---

## 🔧 Technische Details

### SQL-Formel (in View):
```sql
-- Verstrichene Monate
TIMESTAMPDIFF(MONTH, 
    COALESCE(u.aktiv_ab, '2025-12-01'),
    CURDATE()
)

-- Monatsbeiträge gesamt
verstrichene_monate * pflicht_monatlich

-- Konto-Saldo
basis_saldo - monatsbeitraege_gesamt
```

### Aktiv-Datum (`aktiv_ab`):
- Für neue Mitglieder: Datum des Beitritts
- Für bestehende: 01.11.2025
- Berechnung startet ab diesem Datum

### Status-Logik:
```sql
CASE 
  WHEN konto_saldo < 0 THEN 'ueberfaellig'
  WHEN konto_saldo >= pflicht_monatlich THEN 'gedeckt'
  ELSE 'teilweise_gedeckt'  -- 0-9,99€
END
```

---

## ✅ Vorteile

1. **Keine Cronjobs** - Berechnung bei jedem View-Aufruf
2. **Keine DB-Transaktionen** - Nichts wird "gebucht"
3. **Echtzeit** - Immer aktueller Stand
4. **Transparent** - Negative Salden zeigen Schulden
5. **Fair** - Jeder zahlt gleich viel pro Monat
6. **Simpel** - Keine komplexe Logik

---

## 🚨 Casino-Check

Casino hat bereits Reserve-Check:
```javascript
// Mindest-Reserve: 10€
if (user_balance < 10) {
  alert('Mindestens 10€ Reserve erforderlich');
  return false;
}
```

**Bedeutung:**
- Konto-Saldo muss ≥ 10€ sein
- Bei negativem Saldo → Casino gesperrt ✅
- Bei 0-9,99€ → Casino gesperrt ✅

**Funktioniert perfekt!** Keine Änderung nötig.

---

## 📝 Beispiel-Szenarien

### Szenario 1: Monat vergangen, nicht gezahlt
- Start: 40€
- 1 Monat später: 30€
- 2 Monate später: 20€
- 4 Monate später: 0€
- 5 Monate später: -10€ 🔴

### Szenario 2: Einzahlung bei Schulden
- Aktuell: -20€ (2 Monate Schulden)
- Einzahlung: +50€
- Neuer Saldo: 30€ 🟢
- Monate gedeckt: 3

### Szenario 3: Schaden + Monatsbeitrag
- Start: 40€
- Schaden: -80€ = -40€ Basis
- 1 Monat später: -50€ Konto-Saldo
- Einzahlung +100€: 50€ Basis
- Nach 1 Monat: 40€ Konto-Saldo

---

**Erstellt:** 11.11.2025 20:05  
**System:** Dynamische Berechnung ohne DB-Transaktionen  
**Status:** ✅ Production Ready

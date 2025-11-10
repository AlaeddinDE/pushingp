# 💳 Monatliches Zahlungssystem

## Übersicht

**Start:** 01.12.2025  
**Monatsbeitrag:** 10,00 €  
**Rhythmus:** Monatlich am 1. des Monats

---

## 📌 Konzept

### Konto statt Guthaben
- Jedes Mitglied hat ein **Konto** (vorher "Guthaben")
- Das Konto wird durch Einzahlungen aufgefüllt
- Ab 01.12.2025 wird **monatlich automatisch 10 €** vom Konto abgebucht

### Beispiel
- Mitglied zahlt am 15.11. **40 €** ein → Konto: **40,00 €**
- Am 01.12.: Abbuchung 10 € → Konto: **30,00 €**
- Am 01.01.: Abbuchung 10 € → Konto: **20,00 €**
- Am 01.02.: Abbuchung 10 € → Konto: **10,00 €**
- Am 01.03.: Abbuchung 10 € → Konto: **0,00 €**
- Am 01.04.: **Keine Abbuchung** (kein Guthaben) → Mitglied muss nachzahlen

---

## 🔧 Technische Umsetzung

### 1. Datenbank-Struktur

#### Tabelle: `monthly_fee_tracking`
Trackt alle monatlichen Abbuchungen.

#### View: `v_member_konto`
Zeigt aktuelles Konto-Saldo jedes Mitglieds.

#### View: `v_monthly_fee_overview`
Übersicht über Zahlungsstatus.

---

## 🤖 Automatische Abbuchung

### API-Endpunkt
**Datei:** `/api/v2/process_monthly_fees.php`

**Aufruf:**
```bash
# Manuell als Admin:
https://pushingp.de/api/v2/process_monthly_fees.php

# Via Cronjob (am 1. des Monats):
curl -k "https://pushingp.de/api/v2/process_monthly_fees.php?secret=pushingp_cron_2025"
```

---

## ⚙️ Cronjob einrichten

```bash
# Am 1. jeden Monats um 00:05 Uhr
5 0 1 * * curl -k "https://pushingp.de/api/v2/process_monthly_fees.php?secret=pushingp_cron_2025" >> /var/log/monthly_fees.log 2>&1
```

---

## 📝 Migration anwenden

```bash
cd /var/www/html
mysql -u root -p pushingp < migrations/auto/20251110_monthly_fee_system.sql
```

---

**Erstellt:** 10.11.2025  
**Agent:** Codex  
**Version:** 1.0

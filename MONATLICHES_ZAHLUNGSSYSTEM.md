# 📅 Monatliches Zahlungssystem

## 🎯 Konzept

**Zahlungen sind IMMER am Monatsersten fällig.**

### Wie es funktioniert:

1. **Guthaben** = Summe aller Ein-/Auszahlungen (live berechnet)
2. **Gedeckt bis** = 1. des Monats + (Guthaben ÷ Monatsbeitrag) Monate
3. **Nächste Zahlung fällig** = 1. des Monats NACH "Gedeckt bis"
4. **Abbuchung** = Nur am 1. des Monats automatisch

---

## 📊 Beispiel-Rechnung

**Mitglied:** Alessio  
**Monatsbeitrag:** 10,00 €  
**Aktuelles Guthaben:** 35,00 €  
**Heute:** 09.11.2025

### Berechnung:

```
Gedeckte Monate = floor(35 / 10) = 3 Monate

Start = 01.11.2025 (Erster des aktuellen Monats)
Gedeckt bis = 01.11.2025 + 3 Monate = 01.02.2026
Nächste Zahlung = 01.03.2026
```

### Timeline:

```
01.11  01.12  01.01  01.02  01.03
  |------|------|------|------|
  ✅     ✅     ✅     ✅     🔴
                            ZAHLUNG
                            FÄLLIG
```

---

## 🤖 Automatische Abbuchung

**Cronjob läuft:** Jeden 1. des Monats um 00:00 Uhr

```bash
0 0 1 * * cd /var/www/html/api && php cron_monatliche_abbuchung.php
```

### Was passiert:

1. Prüft alle aktiven Mitglieder
2. Wenn Guthaben ≥ Monatsbeitrag → Abbuchung als Transaktion
3. Neu-Berechnung von "Gedeckt bis" und "Nächste Zahlung"
4. Log in `/var/log/monthly_billing.log`

---

## 🔧 Manuelle Neuberechnung

### Für ein Mitglied:

```bash
php -r "
require_once '/var/www/html/api/berechne_zahlungsstatus.php';
\$result = berechneZahlungsstatus(4); // Mitglied ID
print_r(\$result);
"
```

### Für alle Mitglieder:

```bash
mysql -u root pushingp -e "
    SELECT id FROM users WHERE status='active'
" | tail -n +2 | while read id; do
    php -r "
        require_once '/var/www/html/api/berechne_zahlungsstatus.php';
        berechneZahlungsstatus($id);
    "
done
```

---

## 📈 Status-Anzeige

### In der Kasse:

- **Guthaben:** Live aus Transaktionen
- **Gedeckt bis:** Berechnet nach Formel
- **Nächste Zahlung:** Immer 1. des Monats nach "Gedeckt bis"

### Farben:

- 🟢 Grün = Gedeckt > 2 Monate
- 🟡 Gelb = Gedeckt 1-2 Monate
- 🔴 Rot = Gedeckt < 1 Monat (Zahlung fällig!)

---

## 🔄 API-Endpunkte

### `berechne_zahlungsstatus.php`

Berechnet für ein Mitglied:
- Guthaben
- Gedeckte Monate
- Gedeckt bis
- Nächste Zahlung fällig

### `cron_monatliche_abbuchung.php`

Führt monatliche Abbuchungen durch (nur am 1. des Monats).

---

## ⚙️ Konfiguration

### Monatsbeitrag ändern:

```sql
UPDATE member_payment_status 
SET monatsbeitrag = 15.00 
WHERE mitglied_id = 4;
```

### Startguthaben setzen:

```sql
INSERT INTO transaktionen 
(mitglied_id, typ, betrag, beschreibung, status, datum)
VALUES
(4, 'EINZAHLUNG', 40.00, 'Startguthaben', 'gebucht', NOW());
```

Dann neu berechnen:

```bash
php -r "
require_once '/var/www/html/api/berechne_zahlungsstatus.php';
berechneZahlungsstatus(4);
"
```

---

## 🛡️ Sicherheit

- Nur Admins können Transaktionen erstellen/bearbeiten
- Abbuchungen werden als Transaktionen geloggt (nachvollziehbar)
- Kein Löschen, nur Stornieren möglich
- Cronjob läuft als root → Log-Überwachung wichtig

---

## 📝 Logs

```bash
# Monatliche Abbuchungen
tail -f /var/log/monthly_billing.log

# Cronjob-Status
grep cron_monatliche_abbuchung /var/log/syslog
```

---

**Stand:** 09.11.2025  
**Version:** 1.0  
**Autor:** Codex Agent

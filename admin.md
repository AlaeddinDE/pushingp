# Admin-Bereich – Pushing P

## Zweck
Zentrale Verwaltung aller Module, Mitglieder und Systemdaten.  
Nur für Nutzer mit Admin-Rechten.

## Aktivierung
- Admin erkannt → Button „Admin-Modus“ im Header.  
- Klick = roter Overlay-Header mit Admin-Navigation.  
- Optik leicht rötlich (Glassmorphism).

## Navigation
- Dashboard  
- Crew  
- Kasse  
- Schichten  
- Events  
- System

### Dashboard
- Kennzahlen: Mitglieder, Saldo, Events, Fehlerlogs.  
- Schnellaktionen: Mitglied + / Event freigeben / Kasse buchen.

### Crew
- Liste aller Nutzer mit Bearbeiten / Sperren / Löschen.  
- Rollen, Urlaub, Krankheit, PIN ändern.  
- Neue Mitglieder anlegen.

### Kasse
- Transaktionen hinzufügen / ändern / löschen.  
- CSV/PDF-Export, Korrekturen, Saldo anpassen.

### Schichten
- Alle Kalendereinträge sichtbar.  
- Schichten, Urlaube, Krankheitstage bearbeiten.

### Events
- Events bearbeiten, absagen, Teilnehmer verwalten.  
- Kosten / Kasse / Ort ändern, Duplikate erstellen.

### System
- Serverstatus, Logs, Backups, Benachrichtigungen, Theme.

## Sicherheit
- Aktionen werden in `admin_logs` erfasst.  
- Session-Timeout, Bestätigungsdialoge, optionale 2FA.

## APIs
- `admin_get_users.php`
- `admin_update_user.php`
- `admin_lock_user.php`
- `admin_add_transaction.php`
- `admin_update_event.php`
- `admin_logs.php`

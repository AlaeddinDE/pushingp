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


# Admin – Zentrale Verwaltung (nur Admin)

## 1) Zweck & Ziel
- Vollzugriff zur Moderation und Pflege aller Module.
- Modus-Schalter: **Admin-Modus** blendet roten Admin-Header ein (zweite Leiste).

## 2) Architektur (funktional)
- **Rollen**: `admin`, `kassenaufsicht`, `planer`, `member`.
- **Admin-Header (Tabs)**: Dashboard | Crew | Kasse | Schichten | Events | System.
- **APIs** (nur Admin-Rechte):
  - Crew: `admin_get_users`, `admin_update_user`, `admin_lock_user`, `admin_create_user`.
  - Schichten/Urlaub/Krank: `admin_upsert_shift`, `admin_upsert_vacation`, `admin_upsert_sickday`.
  - Events: `admin_update_event`, `admin_cancel_event`, `admin_participants`.
  - Kasse: **(Kassen-APIs separat definiert in kasse.md; hier nur Verweis)**.
  - System: `admin_get_logs`, `admin_backup`, `admin_notify`.

## 3) UX/Design
- **Admin-Modus**: rot getönt, Glass-UI; Warn-Badges auf kritischen Aktionen.
- **Dashboard**: Kennzahlen (aktive/gesperrte Nutzer, Kassenstand, offene Zahlungen, kommende Events), Schnellaktionen.
- **Crew**: Tabelle (Avatar, Name, Rollen, Status), Aktionen: Bearbeiten, Sperren, Löschen, PIN/Passwort-Reset, Rollenverwaltung.
- **Schichten**: Kalender-Matrix über alle Nutzer mit Admin-Schreibrechten.
- **Events**: bearbeiten, absagen (auch wenn nicht Ersteller), Kostenstelle ändern, duplizieren.
- **System**: Logs, Backups, Notifier (E-Mail/Discord), globale Theme-Defaults.

## 4) Datenmodell (zusatz)
- **UserAdmin**: `{ id, name, email, roles: string[], status: "active"|"locked", discordTag?, pinSet?: boolean }`
- **AdminLog**: `{ id, ts, adminId, action, entityType, entityId, payloadHash }`

## 5) Flows
- **Admin-Modus**: Toggle im globalen Header → Admin-Header sichtbar.
- **Bearbeitung**:
  - Crew: Update-Rolle/Status → `admin_update_user`.
  - Schichten/Urlaub/Krank: Upsert → entsprechende Endpunkte.
  - Events: Update/Cancel → Endpunkte; optional Discord-Notify.
  - Kasse: Buchungen/Korrekturen gem. Kassen-Regelwerk (siehe `kasse.md`).
- **Logs**: Jede Admin-Änderung → `admin_logs` Append.

## 6) Validierungen
- Kritische Aktionen mit Confirm-Dialog + reason-Text (z. B. Sperrung, Löschung).
- Rollenmatrix darf nie leer sein (mind. `member`).

## 7) Sicherheit
- Strikte Session-Prüfung + Rollenprüfung auf jedem Endpunkt.
- CSRF-Schutz, Rate-Limiting, IP/UA-Logging (nur für Admin).
- Optionale 2FA (E-Mail/Discord-OTP).

## 8) Performance
- Serverseitige Pagination (100er Seiten).
- Async-Tab-Laden (nur aktive Tab-Daten fetchen).

## 9) Edge-Cases
- Admin sperrt sich selbst → zulassen? **Standard: Nein** (blockieren).
- Löschen von Nutzern: Soft-Delete (Historie bleibt, besonders für Kasse).

architecture.md

# Systemarchitektur – Pushing P

## 1) Gesamtübersicht
- **Frontend**: HTML (Tailwind), GSAP (Animation/Scroll), modulare Seiten.
- **Backend**: PHP (API-Endpunkte), MySQL (InnoDB), Sessions (PHP-Session).
- **Integrationen**: Discord (Status/Webhooks), Pay-Provider (Paypoint-Pool).
- **Security**: Session + Rollen, CSRF-Tokens, prepared statements (MySQLi `bind_result()`/`fetch()`).

## 2) Modulabhängigkeiten
- **Startseite** → Balance (Mini-Chart), Crew-Preview.
- **Crew** → Member, Discord, Kassen-Flags.
- **Schichten** → persönliche Daten; liefert Verfügbarkeit an **Status** & **Events**.
- **Events** → liest Verfügbarkeit (Schichten), prüft Kassen-Deckung; kann Reservierungen in Kasse auslösen.
- **Kasse** → (eigene Spezifikation) liefert Flags und Balance an Crew/Start/Events/Status.
- **Admin** → Vollzugriff auf alle Module; Audit-Logs.

## 3) Rollen & Rechte
- `member`: eigene Daten + lesen.
- `planer`: Events erstellen/bearbeiten (eigene), Verfügbarkeiten einsehen.
- `kassenaufsicht`: Kassenbuchungen/Korrekturen gemäß Kassenregeln.
- `admin`: alle Rechte, inkl. Sperren/Löschen, Systemfunktionen.

## 4) Kommunikation/Endpoints (Beispiele)
- **Auth**: `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`
- **Common**: alle `api/*.php` prüfen Session + Rolle; JSON nur mit `Content-Type: application/json`.
- **Rate Limits**: user+IP-basiert; sensible Admin-Calls strenger.

## 5) Datenhaltung & Tabellen (fachlich)
- `users(id, name, email, discord, avatar, roles, status, created_at, ...)`
- `settings(user_id, theme)`
- `shifts(id, user_id, date, type, start, end)`
- `vacations(id, user_id, start, end)`
- `sickdays(id, user_id, start, end)`
- `events(id, title, description, start, end, location, cost, paidBy, createdBy, status)`
- `event_participants(event_id, user_id, state)`
- **Kasse**: (siehe `kasse.md`) `transactions`, `reservations` (oder per typ), `admin_logs`
- `admin_logs(id, ts, admin_id, action, entity_type, entity_id, payload_hash)`

## 6) Sicherheit
- **Sessions**: httpOnly, SameSite=Lax/Strict; Session Regeneration nach Login.
- **CSRF**: Token auf allen mutierenden Requests (POST).
- **SQL**: ausschließlich prepared statements; keine dynamischen Feldnamen.
- **XSS**: Server-seitiges Escaping; CSP (script-src self + cdns whitelisted).
- **Passwords/PIN**: argon2id; PIN pro Nutzer optional (z. B. 6-stellig).
- **Logging**: Admin-Änderungen immer geloggt (unveränderbar).

## 7) Telemetrie & Fehler
- Server-Logs (PHP error_log), strukturierte `admin_logs`.
- Frontend: Soft-Error-Reporter (console beacon) ohne PII.

## 8) Performance
- Caching: Short-term in-memory (APCu) für Feiertage/Discord-Status (≤60 s).
- Pagination überall > 50 Einträge.
- Bildoptimierung (WebP, lazy).

## 9) Deployment/Umgebung
- Apache vhost (HTTP→HTTPS Redirect), Let’s Encrypt.
- `env.php` (DB creds, Discord webhook URL, Pay-Provider keys).
- Migrationsskripte für Tabellen.

## 10) Edge-Cases
- Benutzer verlässt Gruppe → `status=inactive`, Daten bleiben historisch.
- Zeitlogik (Nacht-Schichten) → über Tagesgrenze konsistent behandeln.
- Discord down → Präsenz optional, fällt auf „offline“ zurück.

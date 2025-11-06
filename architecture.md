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

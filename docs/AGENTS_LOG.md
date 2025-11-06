## [2024-06-07] landing + admin overhaul
- Überarbeitete Startseite mit erweitertem Schicht-Radar, Partikel-Animationen und GSAP-Hero-Animationen.
- Neues Admin-Board (Events & Ankündigungen) inklusive API (`admin_board_create`, `get_admin_board`, `admin_board_delete`) und Migration.
- Admin-Bereich auf roten Stil umgestellt, einheitliche Header via `includes/admin_header.php`, neues `admin/theme.css` für gemeinsame Styles.
- Transaktionslogik auf neue Typen (Schaden, Gruppenaktion) erweitert, Gruppenaktionen verteilen Beträge automatisch auf alle Mitglieder.
- Admin-Button im Member-Dashboard ergänzt und Kassensummenberechnung angepasst.

## [2024-06-08] shift timeline enhancements
- Shift-Radar um Tooltips, Tastaturfokus und Overnight-Indikatoren ergänzt, inklusive besserer Darstellung von Schichten über Mitternacht in `index.html`.
- Datenabruf der Landingpage parallelisiert (`Promise.allSettled`), um Crew-, Shift- und Statusdaten schneller zu laden.

## [2024-07-09] vollständige API-Implementierung laut Spezifikation
- Sämtliche `api/v2`-Endpunkte für Startseite, Crew, Kasse, Schichten, Events, Status und Admin umgesetzt inkl. CSRF-Prüfung, Rollenlogik und Rate-Limiting-Hilfen in `includes/api_bootstrap.php`.
- Umfassende Datenbankmigration `MIGRATION_20240709_system_modules.sql` ergänzt neue Tabellen (Events, Shifts, Vacations, Sickdays, Payment Requests, Feedback, Notifications) sowie Spaltenerweiterungen und Views für die Finanzlogik.
- Logging und Admin-Funktionen erweitert (`admin_logs`, Benachrichtigungs-Queue), Payment-Flows (Requests, Status) und Verfügbarkeitsaggregation implementiert.

## [2025-11-05] Admin Navigation & Seiten-Refactor
- Dashboard, Kasse, Schichten und Board auf separate Admin-Seiten aufgeteilt (`admin/index.php`, `admin/kasse.php`, `admin/schichten.php`, `admin/board.php`).
- Neues JavaScript-Modul `assets/js/admin.js` erstellt, das mobile Navigation, Partikel-Hintergrund sowie Dashboard-, Kassen-, Schicht- und Board-Logik zentral steuert.
- `includes/admin_header.php` und `admin/theme.css` für mobile Navigation optimiert, Header-Dopplungen entfernt und Single-Page-Layout zugunsten einzelner Seiten ersetzt.

## [2024-08-21] Member-Abwesenheiten & Sperrlogik
- Schicht-Erfassung repariert und um Schichttypen erweitert (`api/set_shift.php`, `api/get_shifts.php`, `member.php`).
- Neue Urlaub-/Krankmeldungs-Workflows inklusive UI, CSRF-gestützter v2-API-Calls und Tabellen (`member.php`, `migrations/MIGRATION_20240821_member_absences.sql`).
- Mitgliederverwaltung erweitert: Sperrstatus, Entsperren und Status-Badges (`admin/users.php`, `api/admin_ban_user.php`, `api/get_members.php`).
- Login/Auth modernisiert (Session-Struktur, CSRF-Token, v2-Kompatibilität) in `api/login.php`, `includes/auth.php`, `includes/api_bootstrap.php`.

## [2025-11-06] DB-Dump bereinigt
- Neues Setup-Skript `SQL_SETUP_CLEAN_BASE.sql` erstellt, das Altlasten entfernt und ein sauberes v1/v2-Grundschema ohne Beispiel-Daten aufsetzt.
- `DATABASE_SCHEMA.md` komplett aktualisiert, um das bereinigte Schema samt Views, Seeds und Legacy-Hinweisen zu dokumentieren.
- `SQL_SETUP_03_PROJECT_STRUCTURE.sql` in einen kommentierten Sanity-Check umgewandelt, damit Deploy-Skripte nicht mehr auf veraltete Tabellen verweisen.

## [2025-11-06] Kasse-Schema als Migration konsolidiert
- `Kasse.sql` auf idempotente Definitionen mit `IF NOT EXISTS` und konsolidierten Fremdschlüsseln/Indizes umgestellt, damit wiederholte Deployments stabil laufen.
- Vollständige Schema-Migration `migrations/MIGRATION_20251106_sync_latest_kasse.sql` hinzugefügt, die den aktuellen Dump als produktive Baseline verfügbar macht.
- `deploy.sh` aktualisiert, damit `Kasse.sql` nicht automatisch verschoben wird und als kanonische Schemaquelle im Web-Verzeichnis verbleibt.

## [2025-11-06] Login auf v2-Schema gehoben
- `api/login.php` liest nun bevorzugt `members_v2`/`admins_v2` und fällt bei Bedarf auf Legacy-Tabellen zurück.
- Verbesserte Fehlerrückgaben mit HTTP-Statuscodes verhindern fatale Fehler bei fehlenden Tabellen.

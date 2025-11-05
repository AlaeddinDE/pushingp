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

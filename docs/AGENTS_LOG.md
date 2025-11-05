## [2024-06-07] landing + admin overhaul
- Überarbeitete Startseite mit erweitertem Schicht-Radar, Partikel-Animationen und GSAP-Hero-Animationen.
- Neues Admin-Board (Events & Ankündigungen) inklusive API (`admin_board_create`, `get_admin_board`, `admin_board_delete`) und Migration.
- Admin-Bereich auf roten Stil umgestellt, einheitliche Header via `includes/admin_header.php`, neues `admin/theme.css` für gemeinsame Styles.
- Transaktionslogik auf neue Typen (Schaden, Gruppenaktion) erweitert, Gruppenaktionen verteilen Beträge automatisch auf alle Mitglieder.
- Admin-Button im Member-Dashboard ergänzt und Kassensummenberechnung angepasst.

## [2024-06-08] shift timeline enhancements
- Shift-Radar um Tooltips, Tastaturfokus und Overnight-Indikatoren ergänzt, inklusive besserer Darstellung von Schichten über Mitternacht in `index.html`.
- Datenabruf der Landingpage parallelisiert (`Promise.allSettled`), um Crew-, Shift- und Statusdaten schneller zu laden.

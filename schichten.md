# Schichten-Seite – Pushing P

## Zweck
Erfassung und Anzeige von Arbeits-, Urlaubs- und Krankheitstagen.  
Bestimmt Verfügbarkeit für Events und Status.

## Funktionen
- Persönliche Schichteinträge: Früh (6–14) / Spät (14–22) / Nacht (22–6) / Tag (7–17:30).
- Urlaub und Krankheit eintragbar.
- Feiertage werden markiert.
- Jahreskalender mit Farbcodes:
  - 🟦 Früh 🟧 Spät 🟪 Nacht 🟩 Tag 🟨 Urlaub 🟥 Krank 🩶 Feiertag
- Private Statistik:
  - Urlaubstage, Kranktage, Schichten gesamt.

## Integration
- `get_availability.php` für Events.
- Verknüpfung mit Status-Seite (wer arbeitet gerade).

## Design
- FullCalendar / Custom GSAP Timeline.  
- Dark/Light umschaltbar pro Benutzer (`update_theme.php`).  

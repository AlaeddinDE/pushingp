# Event- & Ankündigungsseite – Pushing P

## Zweck
Planung und Verwaltung von Crew-Events, inkl. Verfügbarkeitsprüfung und Kassenabgleich.  
Nur für eingeloggte Mitglieder.

## Aufbau
1. **Event-Liste** (kommend / vergangen).  
2. **Event erstellen**  
   - Titel, Beschreibung, Datum, Ort, Kosten, Kasse zahlt?  
   - Teilnehmerauswahl mit Verfügbarkeits-Status (🟢 frei / 🟡 Urlaub / 🔴 Schicht).  
   - Warnung bei nicht gedeckter Kasse.
3. **Event-Detail**
   - Beschreibung, Teilnehmer, Kosten, Finanzquelle.  
   - Buttons „Teilnehmen“ / „Kann nicht“.  
   - Teilen auf Discord und WhatsApp.

## APIs
- `get_events.php`
- `create_event.php`
- `update_event_participation.php`
- `get_availability.php`
- `validate_balance.php`
- `notify_discord.php`

## Design
- Glass-Cards, Pastellfarben, Timeline-Animation.  
- Mobile-optimiert, Fade-in Details.  

# Crew-Seite – Pushing P

## Zweck
Zeigt alle Crew-Mitglieder mit Rollen, Finanz- und Discord-Status.  
Nur für angemeldete Nutzer.

## Aufbau
- Header identisch zur Startseite.
- Grid-Layout mit Member-Cards:
  - Profilbild, Name, Rolle, Discord-Tag.
  - Live-Status (online / idle / busy / offline).
- Klick öffnet Modal:
  - Rollen & Aufgaben.
  - Letzte Einzahlung, Saldo, Verzug.
  - Genutzte Gruppenaktionen.

## APIs
- `get_members.php`  
- `get_discord_status.php`  
- `get_financial_data.php`

## Design
- Dark Glassmorphism, GSAP-Hover-Bewegung.  
- Mobile 2-Spalten-Layout.  

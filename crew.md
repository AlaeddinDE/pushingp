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

# Crew-Seite – Mitgliederübersicht (nur angemeldet)

## 1) Zweck & Ziel
- Vollständige Liste aller Mitglieder mit Rollen, Discord-Status und Finanzindikatoren (nur high-level).
- Detail-Modal je Mitglied (Rollen, Aufgaben, Zahlungsverhalten-Statusindikatoren, **ohne** Transaktionsdetails – die sind auf „Kasse“).

## 2) Architektur
- **Zugriff**: Session-Login erforderlich.
- **Frontend**: Grid/List-Karten mit Modal; Filter & Suche.
- **APIs**:
  - `GET /api/get_members.php` → Stammdaten, Rollen, Avatare.
  - `GET /api/get_discord_status.php` → Präsenz (aggregiert).
  - `GET /api/get_member_flags.php` → Zahlungsstatus-Flag: `paid|open|overdue`.
- **Join** im Frontend: Mergen der drei Responses pro `member.id`.

## 3) UX/Design
- Einheitlicher Header; Glass-UI; sanfte Hover, leichte Tiefe.
- **Karteninhalt**: Avatar, Name, Discord, Hauptrolle (Badge), Status-Flag (🟢/🟡/🔴).
- **Modal** beim Klick:
  - Avatar groß, Name, Rollen/Berechtigungen.
  - Zusammenfassung: „Monatsbeitrag up-to-date“ / „offen“ / „Verzug“ (aus Flags).
  - Aktionen (nur eigene Sicht): Link zu „Einstellungen“ (Theme, Schichten, Urlaub).

## 4) Datenmodell (fachlich)
- **Member**: `{ id, name, avatarUrl, roles: string[], discordTag, joinDate, leaveDate? }`
- **Presence**: `{ id, status: "online"|"away"|"busy"|"offline" }`
- **Flags**: `{ id, dues: "paid"|"open"|"overdue" }`

## 5) API-Endpunkte
- `GET /api/get_members.php`
- `GET /api/get_discord_status.php`
- `GET /api/get_member_flags.php`

## 6) Flows
- Page Load → drei Endpunkte laden → mergen → rendern.
- Suche/Filter (Rolle, Status, Präsenz).
- Klick Karte → Modal.

## 7) Validierungen
- Rollenliste darf nur definierte Rollen enthalten (Admin, Kassenaufsicht, Streitschlichter, Member, Planer).

## 8) Sicherheit
- Endpunkte prüfen Session und minimal erforderliche Rechte.
- Discord-Status nur als Aggregat; kein Token-Leak.

## 9) Performance
- Pagination/Infinite-Scroll ab >48 Mitglieder.
- Avatare als `srcset` (WebP), Lazy-Loading.

## 10) Edge-Cases
- Ausgetretene Mitglieder: grau, Label „inaktiv seit mm/yyyy“.
- Keine Präsenzdaten → Status „offline“.

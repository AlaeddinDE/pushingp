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

# Events & Ankündigungen (nur angemeldet)

## 1) Zweck & Ziel
- Events planen/anzeigen; Teilnehmer und Verfügbarkeiten im Blick.
- Finanzprüfung gegen Kasse (Deckung), optional „Kasse zahlt“.
- Teilbar (Discord/WhatsApp), aber Details nur für eingeloggte Nutzer.

## 2) Architektur
- **Frontend**: Liste + „Event erstellen“-Form + Detail-Modal/Seite.
- **Abhängigkeiten**: Schichten (Verfügbarkeit), Kasse (Budgetprüfung).
- **APIs**:
  - `GET /api/get_events.php?range=upcoming|past`
  - `POST /api/create_event.php`
  - `POST /api/update_event.php`
  - `POST /api/update_event_participation.php`
  - `GET /api/get_availability.php?date=YYYY-MM-DD` (aggregiert)
  - `POST /api/validate_balance.php` (Deckung)
  - `POST /api/notify_discord.php` (Webhook)

## 3) UX/Design
- **Liste**: Karten mit Titel, Datum, Ort, „Kasse zahlt“-Badge, Verfügbarkeitsbalken.
- **Erstellen**:
  - Titel, Beschreibung, Datum/Zeit (Picker), Ort (Text + optional Map-Link),
  - Kosten (€, Pflicht bei „Kasse zahlt“),
  - „Kasse zahlt?“ (Toggle) → Live-Deckungscheck,
  - Teilnehmer (Multi-Select mit Ampel: 🟢 verfügbar, 🟡 Urlaub=verfügbar, 🔴 Schicht).
- **Details**:
  - Beschreibung, Kosten/Quelle, Teilnehmerliste (Teilnahme-Status),
  - Buttons: „Teilnehmen“ / „Kann nicht“,
  - Teilen: Discord (Webhook-Embed), WhatsApp (deeplink).

## 4) Datenmodell
- **Event**: `{ id, title, description, start, end?, location?, cost?, paidBy: "pool"|"private", createdBy, status: "active"|"canceled" }`
- **Participant**: `{ eventId, userId, state: "yes"|"no"|"pending", availability: "free"|"vacation"|"shift" }`

## 5) Flows
- **Create**:
  1) Nutzer füllt Formular → Verfügbarkeitsvorschau.
  2) „Kasse zahlt?“ → `validate_balance`: **OK/Warnung/Block**.
  3) `POST create_event` → bei „Kasse zahlt“ optional **RESERVIERUNG** (separat definierte Transaktion im Kassensystem).
  4) Optional: `notify_discord`.
- **Update**:
  - Nur Ersteller **oder** Admin (Admin überschreibt).
- **Cancel**:
  - Ersteller oder Admin → Status „canceled“, optional Reservierung aufheben.

## 6) Validierungen
- Titel min. 3 Zeichen; Start in Zukunft.
- Bei „Kasse zahlt“: `cost > 0` + Deckungsprüfung.
- Teilnehmerliste darf leer sein (offenes Event).

## 7) Sicherheit
- Rechte: 
  - Erstellen: Member.
  - Bearbeiten/Absagen: Ersteller **oder** Admin.
- Audit-Log: Änderungen an Events.

## 8) Performance
- Paginierte Listen (10/Seite).
- Verfügbarkeit aggregiert auf Anfrage-Datum (Server-seitig).

## 9) Edge-Cases
- Teilnehmer mit „🔴 Schicht“ dennoch eingeladen → UI-Hinweis „nicht verfügbar“.
- Urlaub zählt als **verfügbar** (wie besprochen).

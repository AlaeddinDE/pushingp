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

# Schichten & Verfügbarkeit – Persönlicher Kalender (nur angemeldet)

## 1) Zweck & Ziel
- Nutzer pflegen **eigene** Schichten, Urlaub, Krankheit; Feiertage automatisch markiert.
- Grundlage für **Verfügbarkeiten** (Status-Seite, Event-Planung).
- Benutzerdefiniertes Theme (Dark/Light).

## 2) Architektur
- **Frontend**: Jahres-/Monatskalender (FullCalendar oder Custom Grid + GSAP).
- **APIs**:
  - `GET /api/get_schedule.php?userId=me&range=YYYY-MM` → Schichten.
  - `POST /api/save_schedule.php` → { type, date/range, payload }.
  - `GET /api/get_vacations.php?userId=me` / `POST /api/save_vacation.php`.
  - `GET /api/get_sickdays.php?userId=me` / `POST /api/save_sickday.php`.
  - `POST /api/update_theme.php` → { theme: "dark"|"light" }.
- **Feiertage**: Server-seitig berechnet (z. B. NRW) und als Readonly-Layer geliefert.

## 3) UX/Design
- Jahresübersicht + Monatsdetail; Farbcodes:
  - 🟦 Früh (06–14), 🟧 Spät (14–22), 🟪 Nacht (22–06), 🟩 Tag (07–17:30),
  - 🟨 Urlaub, 🟥 Krank, 🩶 Feiertag.
- Interaktion:
  - Klick → Popover: „Schicht setzen/ändern“, „Urlaub eintragen“, „Krank melden“.
  - Long-Press → Schnellwahl (letzte Auswahl merken).
  - Wiederholungen (wöchentlich/werktags) möglich.

## 4) Datenmodell
- **Shift**: `{ id, userId, date, type: "early"|"late"|"night"|"day", start, end }`
- **Vacation**: `{ id, userId, startDate, endDate }`
- **SickDay**: `{ id, userId, startDate, endDate }`
- **Holiday**(readonly): `{ date, name, region }`
- **Setting**: `{ userId, theme }`

## 5) API-Endpunkte
- s. Architektur – jeweils `GET/POST` mit Session-Check; DB-Operationen mit `bind_result()`/`fetch()`.

## 6) Flows
- Page Load → `get_schedule + get_vacations + get_sickdays + get_holidays`.
- Erstellen/Ändern → `POST ...save_*` → re-render.
- Theme-Toggle → `POST update_theme` + persist in DB/localStorage.

## 7) Validierungen
- Schicht-Überlappungen am selben Tag verhindern (außer explizit erlaubt).
- Urlaub/Kranktage dürfen sich schneiden, wenn gewollt? **Standard: Nein** (Server prüft Überschneidungen).
- Datumsbereiche max. 31 Tage pro Request (Rate-Limit).

## 8) Sicherheit
- Nur `userId=me` oder Admin darf andere Nutzer ändern.
- Änderungslog für Admin-Ansicht (wer hat was gesetzt).

## 9) Performance
- Serverseitiges Clipping auf sichtbaren Range (Monat).
- Client-Caching (ETag/If-None-Match für statische Feiertage).

## 10) Edge-Cases
- Zeitzonenwechsel (Sommer/Winter) → Zeiten in lokaler TZ speichern/anzeigen.
- Nacht-Schicht (22–06) über Tagesgrenze → sauber in UI segmentiert (D+1).

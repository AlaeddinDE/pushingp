# Status – Live-Übersicht (nur angemeldet)

## 1) Zweck & Ziel
- Schneller Überblick: Wer ist gerade „am Arbeiten“, „online“, „verfügbar“, „krank“, „Urlaub“.
- Minimalistisch, performant, mobilfähig.

## 2) Architektur
- **Frontend**: Dashboard mit kleinen Karten/Zählern + Liste nach Kategorien.
- **APIs**:
  - `GET /api/get_live_status.php` → Aggregation:
    - aus Schichten (heute/jetzt),
    - aus Urlaub/Krank,
    - aus Discord-Präsenz (optional, weiche Info).

## 3) UX/Design
- **Counters** oben: 
  - „In Schicht“, „Verfügbar“, „Urlaub“, „Krank“, „Online (Discord)“.
- **Listen** darunter (je Kategorie): Avatare + Namen.
- Filter: „Nur Verfügbare“.

## 4) Datenmodell
- **LiveUser**: `{ id, name, avatarUrl, state: "shift"|"available"|"vacation"|"sick", presence?: "online"|"away"|"busy"|"offline" }`

## 5) Flows
- Page Load → `get_live_status` (Server aggregiert).
- Optional Auto-Refresh alle 60–120 s.

## 6) Validierungen
- Schicht über Mitternacht korrekt zuordnen (22–06 ⇒ Nacht).
- Urlaub > Schicht > Krank Priorität? **Regel**: Krank > Urlaub > Schicht > sonst verfügbar.

## 7) Sicherheit
- Keine sensitiven Details; nur Aggregation.
- Session-Check.

## 8) Performance
- Antwort < 20 KB; Avatare cached; Auto-Refresh gedrosselt.

## 9) Edge-Cases
- Niemand verfügbar → freundlicher Placeholder.

# Startseite – Pushing P

## Zweck
Erster Eindruck und zentrales Hub für alle Besucher und Mitglieder.  
Stellt Crew, Module und Kassen-Kurs visuell ansprechend dar.

## Aufbau
1. **Hero-Bereich**
   - Animiertes Logo „PUSHING P“ (GSAP).
   - Button **„Crew ansehen“** scrollt automatisch zum Crew-Abschnitt.
   - Partikel- / Parallax-Hintergrund im Glassmorphism-Stil.

2. **Crew-Section mit Scroll-Stop-Effekt**
   - Seite stoppt, während die Crew durchscrollt.
   - Zeigt Profilbilder, Namen, Online-Status (marquee-ähnlich).
   - Hover- und Click-Animationen.

3. **Kassen-Kurs (Mini-Chart)**
   - Live-Darstellung des aktuellen **Kassenstandes** wie ein Aktienkurs.
   - Animierte GSAP-Kurve, sanfter Glow-Effekt.
   - Tooltip mit aktuellem Wert, automatisches Update über `get_balance.php`.

4. **Modul-Karten**
   - Kacheln für:
     - 💰 Kasse
     - 👥 Verfügbare Mitglieder
     - 🎉 Event
     - 🗳️ Abstimmungen
   - Hover-Effekte, Fade-in beim Scrollen.

5. **Footer**
   - Formular für **Kontakt / Verbesserungsvorschläge / Problem melden**.
   - Eingabefelder: Name (optional), Nachricht, Senden-Button.
   - Kontakt-Mail: hilfe@pushingp.de.



# Startseite – Pushing P

## 1) Zweck & Ziel
- Erster Eindruck, Navigation-Hub und „State-of-the-Union“ (Crew, Module, Kassen-Kurs).
- Auf Mobilgeräten ebenso hochwertig wie auf Desktop.
- CTA: „Crew ansehen“, Module (Kasse, Verfügbarkeit, Events, Abstimmungen), Kontakt.

## 2) Architektur (funktional)
- **Frontend**: Tailwind + GSAP (ScrollTrigger, ScrollTo, ggf. Lenis).
- **Data-Fetch**: 
  - `GET /api/get_balance.php` → aktueller Kassenstand (für Mini-Chart).
  - `GET /api/get_members_min.php` → kompakte Crew-Liste (Name, Avatar, Status).
- **Zugriff**: öffentlich; „Crew ansehen“ öffnet **Crew-Section** (kein Login nötig), tiefergehende Infos erst nach Login auf Unterseiten.

## 3) UX/Design
- **Hero**: „PUSHING P“ (animiert), Button „Crew ansehen“ (magnetisch, Glass-UI).
- **Crew-Section mit Scroll-Stop**:
  - Beim Erreichen dieser Section „pinnt“ die Seite; nur die Crew-Liste scrollt (horizontal oder vertikal) und spielt animierte Sequenz durch, danach geht der normale Seiten-Scroll weiter.
- **Kassen-Kurs (Mini-Chart)**:
  - Line-/Area-Chart im Apple-Look (zartes Glow, sanfte Kurve), Tooltip „Stand: XX,XX €“.
  - Keine Interaktion außer Tooltip; Detailchart ist auf der Kasse-Seite.
- **Modulkarten**: Kasse, Verfügbare Mitglieder, Events, Abstimmungen → Hover-3D/Parallax.
- **Footer**: kurzes Formular (Kontakt, Verbesserung, Problem melden) + Mail `hilfe@pushingp.de`.

## 4) Datenmodell (nur das Notwendige)
- **BalanceSnapshot** (für Mini-Chart): `{ ts: ISO8601, balance: number }[]` (letzte 7–30 Tage)
- **MemberMini**: `{ id, name, avatarUrl, presence?: "online"|"away"|"busy"|"offline" }`

## 5) API-Endpunkte
- `GET /api/get_balance.php` → `{ balance: number, history: BalanceSnapshot[] }`
- `GET /api/get_members_min.php` → `MemberMini[]`
- `POST /api/feedback.php` → `{ name?: string, message: string }`

## 6) Flows
- **CTA „Crew ansehen“** → `scrollTo(#crew-section)`
- **Page Load** → fetch Balance + MemberMini → render Mini-Chart + Crew-Preview.
- **Footer-Form submit** → `POST /api/feedback.php` → Erfolgs-Toast.

## 7) Validierungen
- Footer: Nachricht ≥ 10 Zeichen; Rate-Limit (IP/Minute).

## 8) Sicherheit
- Footer-Form: CSRF-Token, Spam-Schutz (Honeypot), Server-Side Validation.

## 9) Performance
- Lazy-load GSAP + Chart-Module, IntersectionObserver für Crew-Section.
- Response ≤ 50 KB (Mini-Chart max. 30 Punkte).

## 10) Edge-Cases
- Kein Balance-History → Chart mit Platzhalterlinie + „Keine Daten“.
- Leere Crew-Preview → neutrale Placeholder-Avatare.

## Technik & Design
- Tailwind CSS + GSAP.  
- Glass-UI + Darkmode.  
- Responsiv für Desktop & Mobile.  

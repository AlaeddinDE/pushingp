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

## Technik & Design
- Tailwind CSS + GSAP.  
- Glass-UI + Darkmode.  
- Responsiv für Desktop & Mobile.  

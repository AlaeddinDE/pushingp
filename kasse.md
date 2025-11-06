# Kassen-Seite – Pushing P

## Zweck
Zentrale Finanzübersicht: Einzahlungen, Ausgaben, Saldo, Kursverlauf.  
Nur für eingeloggte Mitglieder.

## Aufbau
1. **Header:** Titel „Gruppenkasse“, aktueller Kontostand (Live-Fetch).
2. **Kursanzeige:**  
   - Großer animierter Chart (Line- oder Area-Chart).  
   - GSAP-Bewegung wie Aktienkurs.  
3. **Mitgliederstatus:**  
   - 🟢 bezahlt / 🟡 offen / 🔴 verzug.  
4. **Transaktionsliste:**  
   - 10 pro Seite (+ „Mehr anzeigen“).  
   - Detail-Modal mit Beleg & Notiz.  
5. **Zahlungsbutton:**  
   - Weiterleitung zu Paypoint Pool (`generate_payment_link.php`).  
6. **Statistik-Boxen:** Kassenstand, letzte Ausgabe, Fälligkeit, Verzüge.

## APIs
- `get_balance.php`
- `get_transactions.php`
- `get_payment_status.php`
- `generate_payment_link.php`
- `update_payment_status.php`

## Design
- iOS-inspiriert, Glass-Look, grün/rot Color Coding.  
- Mobile optimiert, fixer „Einzahlen“-Button.  


# Kassen-Seite – Rechenlogik & Struktur (`kasse.md`)

## 1) Zweck

Transparente, revisionssichere Abbildung der **Gruppenkasse**:

* Einzahlungen, Auszahlungen, Gruppenaktionen, Schäden, Korrekturen
* Pro-Mitglied-Pflichten (Monatsbeitrag), Verzug, Salden, Reserven
* Live-Kurs/Chart des Kassenstands (wie Aktienchart)

Nur sichtbar für **eingeloggte Mitglieder**.

---

## 2) Grundbegriffe & Variablen

**Globale Konstanten (konfigurierbar):**

* `B` = Standard-Monatsbeitrag pro Mitglied (z. B. 10,00 €)
* `FälligkeitsTag` = Tag im Monat, an dem Beitrag fällig wird (z. B. 15)
* `VerzugNachTagen` = Anzahl Tage nach Fälligkeit, ab denen „Verzug“ gilt (z. B. 7)

**Zeiträume:**

* `Monat(m)` = Kalendermonat m (z. B. 2025-11)

**Mitgliedsparameter:**

* `aktiv_ab(i)` = Startmonat, ab dem Mitglied *i* beitragspflichtig ist
* `inaktiv_ab(i)` (optional) = Monat, ab dem *i* beitragsfrei wird (Austritt/Pause)

**Beträge:**

* `Einzahlung` = positiver Betrag
* `Ausgabe` = negativer Betrag
* Geldbeträge werden in **Euro mit 2 Nachkommastellen** geführt.

---

## 3) Datenobjekte (fachlich)

### 3.1 Transaktion `T`

Felder (fachlich):

* `id`, `zeitstempel`, `typ`, `betrag` (+/−), `mitglied_id` (optional), `event_id` (optional),
  `beschreibung`, `erstellt_von`, `korrigiert_durch` (optional), `reversal_von` (optional),
  `status` (gebucht/gesperrt/storniert), `beleg_ref` (optional).

**Transaktionstypen (`typ`)**

1. `EINZAHLUNG` (Mitglied → Kasse)
2. `AUSZAHLUNG` (Kasse → Extern)
3. `GRUPPENAKTION_KASSE` (Eventkosten von Kasse getragen)
4. `GRUPPENAKTION_ANTEILIG` (Eventkosten werden auf Teilnehmer verteilt)
5. `SCHADEN` (ersatzpflichtige Kosten; i. d. R. personengebunden)
6. `UMBUCHUNG` (zwischen Konten/Segmenten intern)
7. `KORREKTUR` / `STORNO` (fehlerhafte Buchung neutralisieren)
8. `RESERVIERUNG` (Betrag für genehmigtes, zukünftiges Event blocken)
9. `AUSGLEICH` (Tilgung offener Anteile/Schulden eines Mitglieds)

### 3.2 Personen-/Mitgliedskonto `K(i)`

Für jedes Mitglied *i* wird fachlich ein Konto/Profil geführt mit:

* **Beitragspflicht** (Soll) je Monat
* **Gezahlte Beiträge** (Haben)
* **Offene Forderungen** (z. B. anteilige Eventkosten, Schäden)
* **Persönlicher Saldo** (Definition s. Abschnitt 6)

### 3.3 Kassenkonto (Pool)

Ein zentrales „Cash“-Konto:

* **Kassenstand brutto** (inkl. Reserven)
* **Reserviert** (blockiert)
* **Verfügbar** (= brutto − reserviert)

---

## 4) Kassenstand & Chart (Kurs)

### 4.1 Kassenstand am Zeitpunkt *t*

[
\text{Kassenstand_brutto}(t) = \sum_{\forall T \le t} \text{betrag}(T)
]
(alle gebuchten Transaktionen bis *t*)

### 4.2 Reserven (Events in der Zukunft)

[
\text{Reserviert}(t) = \sum_{\forall T \le t,, typ = RESERVIERUNG} |\text{betrag}(T)|
]

### 4.3 Verfügbarer Stand

[
\text{Kassenstand_verfügbar}(t) = \text{Kassenstand_brutto}(t) - \text{Reserviert}(t)
]

### 4.4 Kurs/Chart

* **Zeitachse**: Tages-Schlussstände ( \text{Kassenstand_verfügbar}(t_{\text{Tagesende}}) )
* **Linie/Area**: Interpolation zwischen Tagespunkten
* **Tooltips**: Datum, Stand, Delta zum Vortag
* **Startseite**: kompakter Mini-Chart
* **Kasse-Seite**: detailliert (Zeitraumfilter: 7/30/90 Tage)

---

## 5) Beitragspflicht (Soll je Mitglied)

### 5.1 Monatliches Soll

Für aktiviertes Mitglied *i* und Monat *m*:
[
\text{Soll_Beitrag}(i,m) =
\begin{cases}
B & \text{wenn } m \ge aktiv_ab(i) \land (inaktiv_ab(i) \text{ fehlt oder } m < inaktiv_ab(i)) \
0 & \text{sonst}
\end{cases}
]

**Gesamtsoll bis inkl. Monat (M):**
[
\text{Soll_bis}(i,M) = \sum_{m=aktiv_ab(i)}^{M} \text{Soll_Beitrag}(i,m)
]

### 5.2 Gezahlte Beiträge (Ist)

[
\text{Ist_Beiträge_bis}(i,M) = \sum_{\forall T \le \text{Ende}(M),, typ = EINZAHLUNG,, mitglied_id = i} \text{betrag}(T)
]

### 5.3 Offener Beitragsrückstand

[
\text{Rückstand_Beiträge}(i,M) = \max{0,, \text{Soll_bis}(i,M) - \text{Ist_Beiträge_bis}(i,M)}
]

### 5.4 Verzug

Ein Mitglied *i* ist im **Verzug** für Monat *m*, wenn nach
[
\text{Fälligkeit}(m) = \text{Datum}(m,; FälligkeitsTag)
]
die Zahlung *B* nicht bis
[
\text{Fälligkeit}(m) + \text{VerzugNachTagen}
]
eingegangen ist.

**Statusfarben pro Mitglied:**

* 🟢 **grün**: ( \text{Rückstand_Beiträge}(i,M) = 0 )
* 🟡 **gelb**: offener Betrag, aber **nicht** über Verzugsschwelle
* 🔴 **rot**: im Verzug (mind. 1 fälliger Monat überschritten)

*(„M“ = aktueller Monat.)*

---

## 6) Persönlicher Saldo pro Mitglied

Wir unterscheiden **Beiträge/Pool** und **individuelle Verpflichtungen** (Anteile, Schäden):

### 6.1 Individuelle Forderungen ggü. Mitglied *i*

[
\text{Forderungen}(i) =
\sum_{\forall T,, (typ \in {\text{GRUPPENAKTION_ANTEILIG}, \text{SCHADEN}}),, mitglied_id = i} |\text{betrag}(T)|
]
*(Diese Transaktionen erfassen **persönliche** Anteile oder Schadenersatz als **positive** Forderung gegenüber i. Die Kasse hat diese Beträge ggf. vorfinanziert.)*

### 6.2 Persönliche Ausgleiche/Tilgungen

[
\text{Ausgleiche}(i) = \sum_{\forall T,, typ = AUSGLEICH,, mitglied_id = i} \text{betrag}(T)
]
*(AUSGLEICH ist **positiv**, wenn i seine offene Forderung bezahlt hat.)*

### 6.3 Persönlicher Netto-Schuldsaldo

[
\text{Saldo_individuell}(i) = \text{Forderungen}(i) - \text{Ausgleiche}(i)
]

* ( > 0 ): Mitglied schuldet der Kasse noch Geld
* ( = 0 ): alles ausgeglichen
* ( < 0 ): (nur bei Überzahlung) Kasse schuldet dem Mitglied (selten, aber möglich)

**Anmerkung:** Einzahlungen vom Typ `EINZAHLUNG` bedienen **primär** Beitragspflichten (Pool). Will man eine Einzahlung **gezielt** als Tilgung individueller Forderungen nutzen, wird das als `AUSGLEICH` verbucht (oder als `EINZAHLUNG` **plus** interne `UMBUCHUNG` in `AUSGLEICH` – je nach Prozess).

---

## 7) Eventkosten & Gruppenaktionen

### 7.1 „Kasse zahlt“ (Poolfinanzierung)

* Transaktion: `GRUPPENAKTION_KASSE` mit Betrag ( -A )
* Auswirkung:

  * Kassenstand sinkt um ( A )
  * **keine** individuellen Forderungen
* Optional (Budgetkontrolle): Vor der Buchung:
  [
  \text{Kassenstand_verfügbar} \ge A; \Rightarrow; OK;\text{sonst Warnung/Block}
  ]

### 7.2 „Anteilig“ (auf Teilnehmer verteilt)

* Eventkosten ( A ), Teilnehmermenge ( P ) (|P| ≥ 1)
* **Pro-Kopf-Anteil:**
  [
  a = \frac{A}{|P|} \quad \text{(kaufmännisch auf 2 Dezimalen gerundet)}
  ]
* Für jedes ( i \in P ):

  * Transaktion `GRUPPENAKTION_ANTEILIG` mit `mitglied_id = i` und Betrag ( +a ) (Forderung ggü. i)
* Zusätzlich:

  * **Wenn** die Kasse vorfinanziert (z. B. Zahlung an Location): separate `AUSZAHLUNG` ( -A )
  * Danach werden die **Forderungen** durch `AUSGLEICH` (Tilgung) der Mitglieder abgebaut.

**Teilnahme-Sonderfälle**

* Mitglied auf „Urlaub“ ist **trotzdem** verfügbar (Logik aus Schichtsystem).
* Mitglied in **Schicht** = „nicht verfügbar“, kann jedoch **manuell** eingeladen werden → Kennzeichnung „nicht verfügbar (Schicht)“.

---

## 8) Schäden

* Transaktion `SCHADEN` mit `mitglied_id = i` und Betrag ( +S ) (Forderung)
* **Tilgung** über `AUSGLEICH` durch i
* Optional: Ratenplan → mehrere `AUSGLEICH`-Buchungen bis ( \sum ) = ( S )

---

## 9) Umbuchungen & Korrekturen

### 9.1 Umbuchung (intern)

* `UMBUCHUNG` nutzt **Paarbuchungen**, damit sich der Kassenstand **nicht verändert**:

  * Beispiel:  +50 € (Pool → Forderungen),  −50 € (Pool)  ⇒ Netto 0 €
    (Fachlich wird damit ein Betrag von „Beitrags-Topf“ in „individuelle offene Posten“ verschoben.)

### 9.2 Storno / Korrektur

* Fehlerhafte `T_alt` wird **nicht gelöscht**, sondern durch `KORREKTUR` neutralisiert:
  [
  \text{betrag}(KORREKTUR) = - \text{betrag}(T_alt),\quad \text{reversal_von} = T_alt.id
  ]
* Danach **neue** richtige Transaktion erfassen (Audit-Trail bleibt sauber).

---

## 10) Pagination & Anzeige (fachlich)

* **Transaktionsliste**: immer die **letzten 10**; Button „Mehr anzeigen“ zeigt die nächsten 10 (Offset-basiert).
* **Farbkodierung** der Beträge:

  * **Grün**: ( >0 ) (Einzahlung, Forderungseingang/Ausgleich)
  * **Rot**: ( <0 ) (Ausgabe, Kassenabgang)
* **Mitgliederstatus-Badges**:

  * 🟢 bezahlt (keine Rückstände)
  * 🟡 offen (nicht fällig/verzugsfrei)
  * 🔴 im Verzug
* **Info-Karten** oben:

  * „Kassenstand verfügbar“
  * „Reserviert“
  * „Letzte Ausgabe“
  * „Mitglieder im Verzug“

---

## 11) Worked Examples (durchgerechnet)

### 11.1 Monatliche Beiträge

* Crew: 5 Mitglieder, ( B = 10{,}00 ) €
* Neuer Monat M startet, Fälligkeit am 15., Verzug ab 22.

**Zahlungen bis 15.:**

* A zahlt 10 € (`EINZAHLUNG +10`)
* B zahlt 10 € (`EINZAHLUNG +10`)
* C zahlt 0 €
* D zahlt 10 €
* E zahlt 10 €

**Soll bis M:**
[
\text{Soll_bis}(A,M) = 10,; \text{Ist_Beiträge_bis}(A,M)=10;\Rightarrow; \text{Rückstand}=0
]
Analog B, D, E = 0; C = 10 € offen.

**Status 16.–21.:**

* C = 🟡 (offen, aber nicht im Verzug)

**Ab 22.:**

* C = 🔴 (im Verzug)

### 11.2 Event „Kasse zahlt“

* Kassenstand verfügbar vor Event: 400 €
* Eventkosten ( A = 120 ) €
* Buchung: `GRUPPENAKTION_KASSE -120`
  [
  \text{Kassenstand_verfügbar_neu} = 400 - 120 = 280
  ]

### 11.3 Event „anteilig“ (Kasse frontet)

* Teilnehmer P = {A, C, E}, ( |P|=3 ), Gesamtkosten ( A=90 ) €
* Kasse zahlt vorab Rechnung: `AUSZAHLUNG -90`
* Anteile:
  [
  a = 90 / 3 = 30
  ]
* Forderungen:

  * `GRUPPENAKTION_ANTEILIG` ( +30 ) für A
  * `…` ( +30 ) für C
  * `…` ( +30 ) für E
* C zahlt 20 € **nur** für sein Event-Anteil:

  * `AUSGLEICH +20` (C) ⇒ verbleibend 10 € offen

### 11.4 Schaden

* D verursacht Schaden 55 €
* `SCHADEN +55` (Forderung ggü. D)
* D zahlt in 2 Raten: `AUSGLEICH +25` und `AUSGLEICH +30` ⇒ Saldo 0

### 11.5 Reservierung

* Genehmigtes Event in 2 Wochen, prognostizierte Kosten 200 €
* `RESERVIERUNG +200` (blockiert Kassenmittel)
* Anzeige:

  * Kassenstand brutto z. B. 500 €
  * Reserviert 200 € → Verfügbar 300 €

---

## 12) UI-Ableitung (ohne Code)

**Top-Leiste (Cards):**

* **Kassenstand verfügbar** (groß)
* **Reserviert**
* **Im Verzug** (Anzahl, klickbar → Liste)
* **Letzte Ausgabe** (Titel, Datum, Betrag)

**Kurs (Chart):**

* Zeitraum-Tabs: 7/30/90 Tage
* Tooltip zeigt Tagesschlussstand & Delta

**Mitgliederliste:**

* Avatar, Name, Badge 🟢/🟡/🔴
* Kleiner Hinweis: „bezahlt bis mm/yyyy“

**Transaktionen:**

* Tabelle mit Typ-Icon, Beschreibung, Betrag (rot/grün), Datum
* „Mehr anzeigen“ = +10 Einträge

**Buttons:**

* „Jetzt einzahlen“ → externer Pay-Pool (mit Betragsempfehlung B)
* (Admin) „Buchen“ / „Reservieren“ / „Korrigieren“

---

## 13) Berechtigungen (fachlich)

* **Mitglied**: eigene Einzahlungen ansehen, Gesamtkasse sehen, Transaktionsverlauf lesen
* **Kassenaufsicht/Admin**:

  * Alle Transaktionen erfassen/ändern (mit **KORREKTUR** statt Löschen)
  * Reservierungen, Gruppenaktionen, Schäden buchen
  * Export (CSV/PDF)
  * Einsicht in Verzugsliste

---

## 14) Validierungen & Invarianten

* **Nie** negative Reservierungssummen
* `Kassenstand_verfügbar ≥ 0` **vor** Abschluss „Kasse zahlt“ (sonst Warnung/Block)
* Jede Storno-Korrektur hat **exakt** gegenläufigen Betrag
* Rundungen **erst am Endergebnis** pro Teilbetrag (Banker’s Rounding / kaufmännisch)

---

## 15) Erweiterungen (optional)

* **Automatische Zuordnung**: Einzahlung → zuerst Beitragsrückstände tilgen, dann freiwillige Aufstockung; Checkbox zur **Zweckbindung** (Beitrag vs. Ausgleich)
* **Mahnlogik**: Stufe 1 (Hinweis), Stufe 2 (Erinnerung), Stufe 3 (Sperrvorschlag)
* **Budgets**: Monatsbudget für Gruppenaktionen

---

## 16) Status-Definitionen (Farben)

* 🟢 **„bezahlt“**: ( \text{Rückstand_Beiträge} = 0 ) **und** ( \text{Saldo_individuell} \le 0{,}00 )
* 🟡 **„offen“**: ( \text{Rückstand_Beiträge} > 0 ), **aber** noch **nicht** über Fälligkeit+Toleranz
* 🔴 **„im Verzug“**: mind. ein Monatsbeitrag ist über Fälligkeit+Toleranz unbezahlt
* 🔵 **„offene Anteile“**: ( \text{Saldo_individuell} > 0 ) (zusätzlicher Badge)

---

### Kurz-Fazit

Diese Spezifikation definiert **vollständig**:

* Kassenstand (brutto/ reserviert/ verfügbar)
* Beiträge & Verzug
* Eventkosten („Kasse zahlt“ vs. „anteilig“)
* Schäden, Ausgleich, Umbuchung, Korrektur
* Chart-Logik wie Aktienkurs
* Anzeige- und Statusregeln

---

# 🧮 Erweiterung 17 – Mitgliedsein- und -austritte

Damit das Kassensystem fair und nachvollziehbar bleibt, muss jeder finanzielle Beitrag **zeitbezogen gewichtet** werden.
Dazu gelten folgende Regeln und Formeln:

---

## 17.1 Grundprinzip

Jedes Mitglied *i* hat in der Datenbank Felder:

| Feld            | Beschreibung                                                | Beispiel  |
| --------------- | ----------------------------------------------------------- | --------- |
| `aktiv_ab(i)`   | Monat, ab dem Mitglied beitragspflichtig wird               | 2025-03   |
| `inaktiv_ab(i)` | Monat, ab dem Mitglied beitragsfrei wird (Austritt / Pause) | 2025-08   |
| `rejoin(i)`     | Liste späterer Wiedereintritte (optional)                   | [2025-11] |

Diese Werte steuern **automatisch**, für welche Monate ein Mitglied **Beiträge schuldet** und **welche Transaktionen sichtbar** sind.

---

## 17.2 Beitragspflicht-Formel (mit Ein- & Austritt)

[
\text{Soll_Beitrag}(i,m) =
\begin{cases}
B, & \text{wenn } aktiv_ab(i) \le m < inaktiv_ab(i); \text{(falls gesetzt)}[6pt]
B, & \text{wenn } aktiv_ab(i) \le m \text{ und } inaktiv_ab(i); \text{nicht definiert}[6pt]
0, & \text{sonst}
\end{cases}
]

Das bedeutet:

* Wer **später beitritt**, zahlt **erst ab Beitrittsmonat**.
* Wer **austritt**, zahlt **nur bis einschließlich** des Monats **vor** dem Austritt.
* Wer **pausiert** oder **wiedereintritt**, wird **mehrfach segmentiert** (mehrere Aktivphasen).

---

## 17.3 Teilmonat (optional)

Wenn jemand mitten im Monat beitritt (z. B. am 20.),
kann optional eine **anteilige Berechnung** erfolgen:

[
\text{Beitrag_anteilig}(i,m) = B \times \frac{\text{Tage_aktiv}(i,m)}{\text{Tage_gesamt}(m)}
]

Beispiel:
Beitrag = 10 €
Beitritt = 20.03.2025 → 12 aktive Tage von 31 →
[
10 \times \frac{12}{31} = 3{,}87 €
]

Standardmäßig wird aber **immer der volle Monatsbeitrag** gerechnet, wenn mehr als 50 % des Monats aktiv war.

---

## 17.4 Austritte

Wenn ein Mitglied **austritt**, gilt:

* Ab `inaktiv_ab(i)` keine neue Beitragspflicht.
* Alle vorherigen Soll-Monate bleiben bestehen.
* Offene Rückstände müssen **weiterhin beglichen** werden.
* Der Account kann auf **„inaktiv“** gesetzt werden, bleibt aber in der Kassenhistorie (keine Löschung!).

### Beispiel

| Monat | Mitglied aktiv? | Soll  | Zahlung | Status |
| ----- | --------------- | ----- | ------- | ------ |
| 03/25 | ✅               | 10,00 | 10,00   | 🟢     |
| 04/25 | ✅               | 10,00 | 0,00    | 🔴     |
| 05/25 | ❌ (Austritt)    | 0,00  | —       | —      |

---

## 17.5 Wiedereintritt

Bei Wiedereintritt:

* `aktiv_ab(i)` bleibt bestehen (historisch).
* Neuer Eintrag in `rejoin(i)` mit neuem Startmonat.
* Kassenlogik behandelt Wiedereintritt als **zweite Aktivphase**.

Beispiel:

```text
aktiv_ab = 2025-03
inaktiv_ab = 2025-08
rejoin = [2025-11]
```

→ Mitglied ist beitragspflichtig **von März–Juli und ab November wieder**.
Die Monate August–Oktober sind beitragsfrei.

---

## 17.6 Darstellung in der UI

In der Mitgliederliste / Kassenübersicht:

* **Inaktive Mitglieder** = grau hinterlegt.
* **Aktiv (neu beigetreten)** = grün getönt (z. B. „seit 05/25“).
* **Ehemalig (ausgetreten)** = grau + Hinweis „bis 07/25 aktiv“.
* **Pause / Wiedereintritt** = Label „reaktiviert“.

In der Chart- oder Verlauf-Ansicht werden **nur aktive Monate** gezählt.
Wenn jemand austritt, fällt er **automatisch aus allen Soll-Berechnungen** ab dem Folgemonat heraus.

---

## 17.7 Formel für Gesamtsoll (korrekt erweitert)

[
\text{Soll_bis}(i,M) =
\sum_{m=\text{aktiv_ab}(i)}^{M}
\text{Soll_Beitrag}(i,m)
]

wobei:

* `Soll_Beitrag(i,m)` automatisch 0 ist,
  wenn *i* im Monat *m* **nicht aktiv** war.
* Wenn mehrere Aktivphasen existieren:
  [
  \text{Soll_bis}(i,M) =
  \sum_{p=1}^{n} \sum_{m=\text{start}(p)}^{\min(M,\text{ende}(p))} B
  ]
  wobei *p* = Aktivphase, definiert durch (start, ende).

---

## 17.8 Einfluss auf Kassenstand & Kurs

Austritte wirken **nicht rückwirkend**.
Der Kurs/Chart bleibt unverändert, da er reale Buchungen zeigt.
Neue Mitglieder verändern den Kurstrend erst ab ihrer ersten Einzahlung.
Dadurch bleibt der Kurs immer „zeitgeschichtlich korrekt“.

---

## 17.9 Beispiel – gemischte Crew

| Mitglied | Aktiv von | Aktiv bis | Zahlungen          | Status          |
| -------- | --------- | --------- | ------------------ | --------------- |
| A        | 01/25     | —         | 10 €/Monat         | 🟢              |
| B        | 01/25     | —         | 10 €/Monat         | 🟢              |
| C        | 01/25     | 04/25     | 3 × 10 € bezahlt   | 🟢 bis Austritt |
| D        | 03/25     | —         | ab März 10 €/Monat | 🟢              |
| E        | 05/25     | —         | zahlt ab Mai       | 🟡 (neu)        |

### Kassenstand 06/25

[
\text{Kassenstand_brutto} =
(2×10×6) + (1×10×4) + (1×10×4) + (1×10×2)
= 60 + 40 + 40 + 20 = 160 €
]
*(vereinfacht, ohne Ausgaben)*

C wird ab Mai nicht mehr gezählt.
E ist erst ab Mai beitragspflichtig.
Das System behandelt das automatisch durch `aktiv_ab` / `inaktiv_ab`.

---

## 17.10 Fazit

✔ Späteinsteiger werden **erst ab ihrem Eintrittsmonat** belastet.
✔ Austritte stoppen die Beitragspflicht **ab Folgemonat**.
✔ Rückstände aus aktiver Zeit bleiben bestehen.
✔ Wiedereintritte starten neue Aktivphasen.
✔ Der Kassenkurs bleibt **historisch korrekt**, weil alte Transaktionen nie gelöscht werden.

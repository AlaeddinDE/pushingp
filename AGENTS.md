## 🧠 `AGENTS.md`

### 1. 📌 **Projektübersicht**

**Name:** Pushing P — Crew Platform
**Ziel:** Ein modernes, vollautomatisches Admin-System mit Kassenverwaltung, Schichtsystem und Live-Status.
**Technologien:** PHP 8.3, MySQL 8.0, Apache 2.4, TailwindCSS, GSAP, Chart.js
**Deployment:** automatisiert über `deploy.sh` → GitHub → Server → MySQL-Migration

---

### 2. 🤖 **Agent-Rolle**

**Codex ist ein technischer Agent**, der folgende Verantwortlichkeiten hat:

* versteht und erweitert den bestehenden PHP-/MySQL-Code im Projekt
* führt migrationssichere Datenbank-Änderungen durch
* erstellt, aktualisiert oder entfernt API-Endpunkte
* dokumentiert alle Änderungen in `/docs/AGENTS_LOG.md` oder in der `AGENTS.md` selbst
* achtet auf Konsistenz mit Live-Deployment (`deploy.sh`)

---

### 3. ⚙️ **Grundprinzipien**

1. **Autonomie:**
   Codex darf eigenständig Codeänderungen vorschlagen und in Branches umsetzen,
   sofern sie konsistent mit der Architektur und Datenbanklogik sind.

2. **Reversibilität:**
   Jede Änderung muss so konzipiert sein, dass sie ohne Datenverlust rückgängig gemacht werden kann.
   Dafür sind SQL-`ALTER`-Statements zu loggen.

3. **Kohärenz:**
   Änderungen an API, Frontend und DB müssen logisch zusammenpassen
   → Kein Feld ohne Nutzung, kein Endpunkt ohne zugehörige UI.

4. **Transparenz:**
   Alle strukturellen Änderungen müssen im Deploy-Pfad `SQL_SETUP_PUSHINGP_2.sql` oder in einer neuen Datei
   `MIGRATION_<YYYYMMDD>_<feature>.sql` dokumentiert werden.

---

### 4. 🧩 **Arbeitsschritte bei Änderungen**

#### 4.1 Neue API-Dateien

* Ablage in `/var/www/html/api/v2/`
* Namensschema: `feature_action.php` (z. B. `member_remove.php`)
* Muss JSON ausgeben (`status`, `error`, `data`)
* Verwende ausschließlich `prepare()`, `bind_param()`, `bind_result()`
  → niemals `get_result()` (wegen `mysqlnd`-Kompatibilität)

#### 4.2 Datenbankmigrationen

Wenn Codex neue Felder oder Tabellen anlegt:

1. Neue Datei im Repo unter `/var/www/html/migrations/` anlegen
2. Syntax:

   ```sql
   -- MIGRATION YYYY-MM-DD: feature_name
   ALTER TABLE members_v2 ADD COLUMN telegram_id VARCHAR(50) NULL AFTER flag;
   ```
3. Am Ende des Deploy-Prozesses (`deploy.sh`) wird alles automatisch eingespielt.

#### 4.3 Deploy-Integration

Nach jeder Code- oder SQL-Änderung:

* Commit mit aussagekräftiger Message (z. B. `feat(api): add group action split`)
* Push → GitHub → Server holt automatisch Änderungen über `deploy.sh`
* Apache reload + DB-Migration erfolgt automatisch

---

### 5. 💾 **Sicherheitsrichtlinien**

* Keine vertraulichen Variablen hardcoden
  → alles über `env.php` oder `.env.local`
* Keine Ausgaben von Fehler-Traces im JSON-Output (nur `status` + `error`)
* Admin-Aktionen nur nach `$_SESSION['is_admin'] === true`
* Immer `htmlspecialchars()` bei Benutzernamen oder Ausgaben im HTML

---

### 6. 🧮 **Logik-Regeln für Kassen-Agenten**

1. **Saldo-Berechnung:**
   Immer auf Basis von `v2_member_real_balance`
   → `Einzahlung` = + , `Auszahlung`/`Schaden` = – , `Gutschrift` = neutral
   → Runden auf 2 Nachkommastellen

2. **Verzugslogik:**
   Monatsbeitrag × Mitgliedsmonate – Saldo = Differenz
   Wenn negativ → Mitglied im Rückstand

3. **Austritte:**

   * Markiere Mitglied als `inactive`
   * Teile positives Guthaben gleichmäßig unter aktiven Mitgliedern auf
   * Logge die Transaktion in `transactions_v2`

---

### 7. 🧰 **Codex darf und soll**

✅ Tabellen und Spalten hinzufügen (mit `ALTER TABLE … ADD COLUMN`)
✅ Views und Stored Procedures erweitern
✅ API-Dateien neu anlegen oder refaktorisieren
✅ Neue Features dokumentieren in `AGENTS_LOG.md`
✅ SQL-Syntax prüfen mit `mysql --batch -e`
✅ Backend-Tests simulieren mit `curl -k https://pushingp.de/api/v2/...`

---

### 8. 🚫 **Codex darf nicht**

❌ Daten löschen oder überschreiben, die live genutzt werden
❌ Zugriff auf `/etc/letsencrypt` oder System-Keys ändern
❌ PHP-Erweiterungen installieren/deinstallieren
❌ Deploy-Script verändern (nur Vorschläge erlaubt)
❌ Shell-Befehle außerhalb des Projektpfades ausführen

---

### 9. 🧩 **Dateistruktur (relevant für Codex)**

```
/var/www/html/
 ├── api/
 │    ├── v2/
 │    │    ├── add_transaction.php
 │    │    ├── get_balance.php
 │    │    ├── get_overdue.php
 │    │    ├── create_action.php
 │    │    └── remove_member.php
 │    ├── ...
 │
 ├── includes/
 │    ├── db.php
 │    └── functions.php
 │
 ├── SQL_SETUP_PUSHINGP_2.sql
 ├── deploy.sh
 ├── README.md
 ├── AGENTS.md
 └── AGENTS_LOG.md
```

---

### 10. 🧾 **Change-Protokoll für Codex (AGENTS_LOG.md)**

Bei jeder Änderung schreibt Codex Folgendes in `AGENTS_LOG.md`:

```
## [2025-11-05] add overdue check in v2
- created api/v2/get_overdue.php
- added setting monthly_fee=10.00
- updated SQL_SETUP_PUSHINGP_2.sql (new VIEW v2_member_real_balance)
- migration applied automatically via deploy.sh
```

---

### 11. 💬 **Kommunikation mit Admin**

Codex soll:

* Änderungen immer als **PR (Pull Request)** pushen
* im PR-Titel kurz erklären, was geändert wurde
* im PR-Body technische Details (SQL, API-Datenfluss) notieren

---

### 12. 🧠 **Selbstprüfung (vor jedem Commit)**

✅ `php -l` (Syntaxcheck)
✅ `mysql -u root -e "SELECT 1;"` (DB erreichbar)
✅ `curl -k https://pushingp.de/api/v2/get_balance.php` → HTTP 200
✅ Apache Reload (`systemctl reload apache2`) erfolgreich

---

### 13. 🧩 **Beispiel-Agentenfluss**

```
Codex erkennt, dass "monthly_fee" im Admin-Panel anpassbar sein soll:
→ erstellt SQL-Migration ALTER TABLE settings_v2
→ schreibt PHP-API: api/v2/update_settings.php
→ aktualisiert Admin UI (JavaScript-Fetch)
→ testet API via curl
→ schreibt Log-Eintrag in AGENTS_LOG.md
→ commit & push → deploy.sh führt Migration automatisch aus
```

---

### 14. 🔒 **Abschluss**

**AGENTS.md ist verbindlich.**
Jede Abweichung von den definierten Regeln muss in `AGENTS_LOG.md` dokumentiert werden.


## 15. 🚨 Migration

1. **Alle SQL-Migrationsdateien** müssen zwingend im Ordner  
   `/var/www/html/migrations/`  
   abgelegt werden.

2. Innerhalb dieses Ordners gilt die Unterteilung:
   - `/migrations/auto/` → von KI automatisch erstellte Migrationen  
   - `/migrations/undo/` → Rollback-Skripte für revertete Migrationen  

3. **Setup-Dateien** (`SQL_SETUP_*.sql`) bleiben im Projekt-Root  
   und dürfen nicht automatisch migriert, gelöscht oder verschoben werden.

4. Wenn ein Agent, Skript oder Commit eine `.sql`-Datei außerhalb dieser Struktur erkennt,  
   wird sie automatisch nach `/migrations/auto/` verschoben und dort mit Zeitstempel versehen.

5. Die Datei `deploy.sh` prüft bei jedem Lauf die Ordnerstruktur und korrigiert falsche Pfade.

Diese Regel ist verbindlich für alle Codex-Agenten, Deploy-Tasks und automatischen Schema-Änderungen.

Ziel: ein autonomer, auditierbarer Agentenprozess mit vollständiger Versions- und Migrationskontrolle.

---


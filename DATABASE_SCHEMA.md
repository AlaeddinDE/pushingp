# Datenbank-Schema (bereinigter Stand 2025-11-06)

Die Pushing-P-Plattform setzt inzwischen konsequent auf das v2-Datenmodell. Dieses Dokument beschreibt den aufgeräumten Mindest-Stand der Datenbank nach Anwendung von `SQL_SETUP_CLEAN_BASE.sql`. Die Legacy-Tabellen aus v1 bleiben optional für die alten Endpunkte erhalten, enthalten aber keine Demo-Daten mehr.

## Kern-Tabellen (v2)

### `members_v2`
Mitgliederstamm inklusive Rollensystem und Sperrstatus.
```sql
CREATE TABLE members_v2 (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NULL,
  discord_tag VARCHAR(100) NULL,
  avatar_url VARCHAR(255) NULL,
  roles VARCHAR(120) NOT NULL DEFAULT 'member',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
  flag VARCHAR(10) NULL,
  joined_at DATE NOT NULL DEFAULT (CURRENT_DATE),
  left_at DATE NULL,
  status ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  pin_plain VARCHAR(6) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_members_v2_name (name),
  KEY idx_members_v2_status (status),
  KEY idx_members_v2_joined (joined_at)
) ENGINE=InnoDB;
```

### `transactions_v2`
Zentrale Buchungs-Tabelle für Kassenbewegungen.
```sql
CREATE TABLE transactions_v2 (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NULL,
  payment_request_id BIGINT UNSIGNED NULL,
  reservation_id BIGINT UNSIGNED NULL,
  type ENUM('Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Ausgleich','Korrektur','Umbuchung') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255) NULL,
  status ENUM('gebucht','gesperrt','storniert') NOT NULL DEFAULT 'gebucht',
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tx_v2_member (member_id),
  KEY idx_tx_v2_created (created_at),
  KEY idx_tx_v2_type (type),
  KEY idx_tx_v2_status (status),
  KEY idx_tx_v2_event (event_id),
  KEY idx_tx_v2_payment (payment_request_id),
  CONSTRAINT fk_tx_v2_member FOREIGN KEY (member_id)
    REFERENCES members_v2(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;
```

### Weitere v2-Tabellen
- `settings_v2` – Schlüssel/Wert-Einstellungen für die Kasse.
- `admins_v2` – Admin-Flag auf Basis der `members_v2.id`.
- `admin_board` – Dashboard-Ankündigungen & Events.
- `user_settings`, `discord_status_cache` – User- und Status-Präferenzen.
- `shifts`, `vacations`, `sickdays`, `holidays_cache` – Schicht- und Abwesenheitsverwaltung.
- `events`, `event_participants` – Eventplanung samt Teilnehmern.
- `reservations_v2`, `payment_requests` – Finanz-Vorreservierungen & Zahlungsanforderungen.
- `feedback_entries`, `discord_notifications`, `admin_logs` – Feedback, Discord-Queue & Audit-Log.

## Views & Stored Procedures

Nach dem Clean-Setup existieren drei Views:
- `v2_member_real_balance`
- `v2_member_gross_flow`
- `v2_kassenstand_real`

Sie berechnen alle Kennzahlen ausschließlich aus `transactions_v2` und berücksichtigen nur gebuchte Vorgänge.

Zusätzlich stellt `v2_ensure_member_exists` eine Helfer-Prozedur bereit, die bei API-Einträgen automatisch ein Mitglied anlegt.

## Default-Seeds

Das Clean-Setup legt optional einen Dummy-User `AdminDemo` an und setzt Standardwerte in `settings_v2`. Die Einträge werden nur erzeugt, wenn noch keine Daten vorhanden sind.

## Legacy-Kompatibilität (v1)

Für Alt-APIs bleiben die Tabellen `members`, `transactions`, `admins` und `shifts` erhalten. Sie werden ohne Beispiel-Datensätze angelegt und können bei Bedarf vollständig entfernt werden, sobald alle Clients auf die v2-Endpunkte migriert sind.

## Entfernte Altlasten

Folgende Tabellen wurden aus dem Dump gelöscht, weil sie im Code nicht mehr vorkommen oder durch v2 abgelöst wurden:
- `announcements`, `chat`, `config`
- `ledger`, `ledger_items`
- `monthly_payments`, `monthly_settings`
- `pool_amounts`, `pool_cache`
- `proposals`, `proposal_votes`

Damit ist das Schema schlanker und kollisionsfrei mit den aktuellen Migrationen.

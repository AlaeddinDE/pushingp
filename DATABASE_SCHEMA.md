# Datenbank-Schema für Pushing P

## Benötigte Tabellen

### 1. `members`
```sql
CREATE TABLE IF NOT EXISTS `members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `flag` VARCHAR(10) DEFAULT NULL,
  `pin` VARCHAR(6) NOT NULL,
  `start_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2. `transactions`
```sql
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `type` ENUM('Einzahlung', 'Auszahlung', 'Gutschrift') NOT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`)
);
```

### 3. `shifts`
```sql
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_name` VARCHAR(255) NOT NULL,
  `shift_date` DATE NOT NULL,
  `shift_start` TIME NOT NULL,
  `shift_end` TIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_member_name` (`member_name`),
  INDEX `idx_shift_date` (`shift_date`)
);
```

### 4. `admins` (optional - für Admin-Rechte)
```sql
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pin` VARCHAR(6) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Hinweise

- **PINs**: Werden als Plaintext gespeichert (für einfache PIN-Eingabe). Für Produktion sollte man Hashing verwenden.
- **Transaktionen**: Der Balance wird dynamisch aus Transaktionen berechnet (siehe `api/get_balance.php`)
- **Schichten**: Mitglieder können eigene Schichten eintragen, Admins können für alle eintragen

## Beispiel-Daten

```sql
-- Beispiel Admin
INSERT INTO `admins` (`pin`) VALUES ('1234');

-- Beispiel Mitglied
INSERT INTO `members` (`name`, `flag`, `pin`, `start_date`) 
VALUES ('Max Mustermann', '🇩🇪', '5678', CURDATE());

-- Beispiel Transaktion
INSERT INTO `transactions` (`name`, `amount`, `type`, `note`) 
VALUES ('Max Mustermann', 50.00, 'Einzahlung', 'Anfangsbetrag');
```


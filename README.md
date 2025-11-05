# Pushing P - Crew Management System

Modernes Crew-Management-System für Kassenverwaltung, Schichten und Mitglieder-Verwaltung.

## 🚀 Features

- **💰 Kassenverwaltung**: Transaktionen (Einzahlungen, Auszahlungen, Gutschriften)
- **🕓 Schichtenverwaltung**: Schichten eintragen, verwalten und löschen
- **👥 Mitgliederverwaltung**: PINs verwalten, Admin-Rechte vergeben
- **📊 Dashboard**: Übersicht mit Charts und Statistiken
- **🔐 Admin-System**: Vollständige Admin-Verwaltung

## 📁 Projektstruktur

```
/
├── admin/              # Admin-Bereich
│   ├── index.php      # Admin Dashboard
│   └── users.php      # Mitgliederverwaltung
├── api/               # API-Endpunkte
│   ├── login.php
│   ├── get_members.php
│   ├── get_balance.php
│   ├── get_shifts.php
│   ├── get_transactions.php
│   ├── add_transaction.php
│   ├── set_shift.php
│   └── ...
├── includes/          # Backend-Logik
│   ├── auth.php      # Authentifizierung
│   ├── db.php        # Datenbankverbindung
│   └── functions.php # Hilfsfunktionen
├── assets/           # CSS, JS, Bilder
├── index.html        # Landing Page
├── login.php         # Login-Seite
├── member.php        # Member Dashboard
└── logout.php        # Logout
```

## 🗄️ Datenbank-Setup

1. **SQL_ADMIN_SETUP.sql** - Admin-System einrichten
2. **SQL_SETUP_02_MEMBERS_PINS.sql** - PINs für Mitglieder setzen
3. **SQL_SETUP_03_PROJECT_STRUCTURE.sql** - Indizes und Optimierungen

Führe die SQL-Dateien in dieser Reihenfolge aus.

## 🔧 Installation

1. Datenbank erstellen und `env.php` konfigurieren
2. SQL-Setup-Dateien ausführen
3. Web-Server konfigurieren (PHP 7.4+)
4. Fertig! 🎉

## 📝 API-Endpunkte

### Authentifizierung
- `POST /api/login.php` - Login

### Mitglieder
- `GET /api/get_members.php` - Alle Mitglieder (mit PIN für Admins)
- `POST /api/admin_change_pin.php` - PIN ändern
- `POST /api/admin_ban_user.php` - Mitglied sperren
- `POST /api/admin_toggle_admin.php` - Admin-Rechte verwalten

### Finanzen
- `GET /api/get_balance.php` - Salden aller Mitglieder
- `GET /api/get_transactions.php` - Transaktionen (filterbar)
- `POST /api/add_transaction.php` - Transaktion hinzufügen
- `POST /api/delete_transaction.php` - Transaktion löschen

### Schichten
- `GET /api/get_shifts.php` - Alle Schichten
- `POST /api/set_shift.php` - Schicht eintragen
- `POST /api/delete_shift.php` - Schicht löschen

## 🎨 Design

- **Glassmorphism** Design
- **GSAP** Animationen
- **Chart.js** für Visualisierungen
- **Tailwind CSS** für Styling
- **Responsive** für alle Geräte

## 🔐 Sicherheit

- Prepared Statements (SQL-Injection Schutz)
- Session-basierte Authentifizierung
- Admin-Berechtigungen
- Input-Validierung

## 📄 Lizenz

Proprietär - Pushing P Crew


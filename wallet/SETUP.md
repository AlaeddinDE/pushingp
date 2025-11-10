# Apple Wallet Integration - Setup Guide

## 🍎 Apple Developer Requirements

### 1. Pass Type ID erstellen

1. Gehe zu [Apple Developer Portal](https://developer.apple.com/account/)
2. Navigiere zu **Certificates, Identifiers & Profiles**
3. Wähle **Identifiers** → **Pass Type IDs**
4. Klicke **+** um eine neue Pass Type ID zu erstellen
5. Verwende: `pass.com.pushingp.crew`
6. Notiere deine **Team ID** (10 Zeichen)

### 2. Pass Type ID Certificate generieren

1. Unter **Pass Type IDs** → wähle deine ID
2. Klicke **Create Certificate**
3. Lade ein Certificate Signing Request (CSR) hoch:
   ```bash
   openssl req -new -newkey rsa:2048 -nodes \
     -keyout pass_key.pem \
     -out CertificateSigningRequest.certSigningRequest
   ```
4. Lade das generierte Certificate herunter: `pass.cer`

### 3. Certificate in .p12 konvertieren

```bash
# Convert .cer to .pem
openssl x509 -in pass.cer -inform DER -out pass_cert.pem

# Combine with private key to create .p12
openssl pkcs12 -export -out pass_cert.p12 \
  -inkey pass_key.pem \
  -in pass_cert.pem \
  -passout pass:YOUR_PASSWORD_HERE
```

### 4. Certificate auf Server kopieren

```bash
scp pass_cert.p12 user@pushingp.de:/var/www/html/wallet/certs/
```

### 5. Konfiguration anpassen

Bearbeite `/var/www/html/includes/apple_wallet.php`:

```php
private $team_identifier = 'YOUR_TEAM_ID'; // Ersetze mit deiner 10-stelligen Team ID
```

Setze Umgebungsvariable für Cert-Passwort:

```bash
# In .bashrc oder /etc/environment
export WALLET_CERT_PASSWORD='YOUR_PASSWORD_HERE'
```

---

## 📂 Ordnerstruktur

```
/var/www/html/wallet/
├── certs/
│   └── pass_cert.p12          # Apple Wallet Certificate (REQUIRED)
├── templates/
│   ├── icon.png               # 29x29 (required)
│   ├── icon@2x.png            # 58x58
│   ├── icon@3x.png            # 87x87
│   ├── logo.png               # 160x50 (required)
│   ├── logo@2x.png            # 320x100
│   ├── logo@3x.png            # 480x150
│   ├── strip.png              # 375x123
│   ├── strip@2x.png           # 750x246
│   └── strip@3x.png           # 1125x369
└── passes/
    └── (generated .pkpass files)
```

---

## 🖼️ Bilder erstellen

### Icon (App-Symbol in Wallet)
- **29x29px** (1x), **58x58px** (2x), **87x87px** (3x)
- PNG, transparent background
- Zeigt "P" Logo

### Logo (Oben links im Pass)
- **160x50px** (1x), **320x100px** (2x), **480x150px** (3x)
- PNG, transparent background
- "PUSHING P" Text-Logo

### Strip (Hintergrundbild oben)
- **375x123px** (1x), **750x246px** (2x), **1125x369px** (3x)
- PNG, kann Level-spezifisch sein
- Dunkler Hintergrund mit Verlauf

---

## 🔧 Schnelltest

```bash
# Test ob Certificate geladen werden kann
php -r "
\$cert = file_get_contents('/var/www/html/wallet/certs/pass_cert.p12');
\$certs = [];
if (openssl_pkcs12_read(\$cert, \$certs, 'YOUR_PASSWORD')) {
    echo '✅ Certificate OK\n';
} else {
    echo '❌ Certificate Error: ' . openssl_error_string() . '\n';
}
"
```

---

## 🚀 Pass erstellen

```bash
# Als eingeloggter User:
curl -k https://pushingp.de/api/wallet/create \
  -H "Cookie: PHPSESSID=..." \
  -o PushingP_Crew.pkpass

# Pass öffnen auf macOS
open PushingP_Crew.pkpass

# Pass auf iPhone übertragen via AirDrop
```

---

## 📱 Apple Wallet Web Service Endpoints

Apple ruft diese URLs automatisch auf:

| Endpoint | Method | Beschreibung |
|----------|--------|--------------|
| `/v1/devices/:device/registrations/:passType/:serial` | POST | Device registrieren |
| `/v1/devices/:device/registrations/:passType` | GET | Liste aktualisierter Pässe |
| `/v1/passes/:passType/:serial` | GET | Aktuellen Pass holen |
| `/v1/devices/:device/registrations/:passType/:serial` | DELETE | Device abmelden |
| `/v1/log` | POST | Error-Logs empfangen |

---

## 🔔 Push Notifications

Um User über Pass-Updates zu informieren:

```php
// Nach Änderung (z.B. Level-Up, Saldo-Update):
require_once 'includes/apple_wallet.php';

// Update pass timestamp
$conn->query("UPDATE users SET wallet_last_updated = NOW() WHERE id = $user_id");

// Apple wird automatisch beim nächsten Check updaten
// Für sofortige Updates: APNs Push senden (siehe APNs Docs)
```

---

## 🧪 Troubleshooting

### "Certificate not found"
→ Stelle sicher dass `/var/www/html/wallet/certs/pass_cert.p12` existiert

### "Failed to read certificate"
→ Überprüfe Passwort in `WALLET_CERT_PASSWORD`

### "Failed to sign manifest"
→ Überprüfe dass OpenSSL installiert ist: `php -m | grep openssl`

### "ZIP creation failed"
→ Überprüfe Schreibrechte: `chown -R www-data:www-data /var/www/html/wallet`

---

## 📚 Ressourcen

- [Apple Wallet Developer Guide](https://developer.apple.com/wallet/)
- [PassKit Package Format](https://developer.apple.com/documentation/walletpasses/building_a_pass)
- [Web Service Reference](https://developer.apple.com/documentation/walletpasses/adding_a_web_service_to_update_passes)

---

**Status:** ⚠️ Zertifikat erforderlich
**Nächste Schritte:**
1. Apple Developer Account einrichten
2. Pass Type ID erstellen
3. Certificate generieren & hochladen
4. Team ID in Code eintragen
5. Bilder erstellen
6. Testen!

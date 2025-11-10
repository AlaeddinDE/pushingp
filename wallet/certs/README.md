# Apple Wallet Certificate

Platziere hier dein Apple Wallet Pass Type Certificate:

## Benötigte Datei:
- `pass_cert.p12` - Dein Apple Pass Type ID Certificate

## Wie erstelle ich das Certificate?

1. **Apple Developer Portal öffnen:**
   https://developer.apple.com/account/

2. **Pass Type ID erstellen:**
   - Certificates, Identifiers & Profiles
   - Identifiers → Pass Type IDs
   - Erstelle: `pass.com.pushingp.crew`

3. **CSR erstellen:**
   ```bash
   openssl req -new -newkey rsa:2048 -nodes \
     -keyout pass_key.pem \
     -out CertificateSigningRequest.certSigningRequest
   ```

4. **Certificate herunterladen:**
   - Im Developer Portal: Create Certificate
   - CSR hochladen
   - `pass.cer` herunterladen

5. **In .p12 konvertieren:**
   ```bash
   openssl x509 -in pass.cer -inform DER -out pass_cert.pem
   openssl pkcs12 -export -out pass_cert.p12 \
     -inkey pass_key.pem \
     -in pass_cert.pem \
     -passout pass:IHR_PASSWORT
   ```

6. **Hierher kopieren:**
   ```bash
   mv pass_cert.p12 /var/www/html/wallet/certs/
   chmod 600 /var/www/html/wallet/certs/pass_cert.p12
   chown www-data:www-data /var/www/html/wallet/certs/pass_cert.p12
   ```

7. **Passwort setzen:**
   ```bash
   export WALLET_CERT_PASSWORD='IHR_PASSWORT'
   ```

## Sicherheit
⚠️ **Niemals** das Certificate in Git committen!
⚠️ Berechtigungen: 600 (nur Owner lesbar)
⚠️ Owner: www-data


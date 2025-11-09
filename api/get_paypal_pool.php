<?php
// PayPal Pool Scraper - Holt den aktuellen Kassenstand vom PayPal Pool
// URL: https://www.paypal.com/pool/9etnO1r4Cl?sr=wccr

header('Content-Type: application/json');

$pool_url = 'https://www.paypal.com/pool/9etnO1r4Cl?sr=wccr';

// PayPal Pool API endpoint (manchmal gibt es einen JSON endpoint)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $pool_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$html = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$html) {
    echo json_encode([
        'status' => 'error',
        'error' => 'PayPal Pool nicht erreichbar',
        'http_code' => $http_code
    ]);
    exit;
}

// PayPal Pool Format: "currentAmount":{"currencyCode":"EUR","value":"109.05"}
// currentAmount = verfügbarer Betrag (nicht collectedAmount = 323.88!)
$amount = null;

// Pattern: currentAmount (verfügbarer Betrag)
if (preg_match('/"currentAmount":\{"currencyCode":"EUR","value":"([0-9.]+)"\}/i', $html, $matches)) {
    $amount = floatval($matches[1]);
}

// Fallback: Nur nach currentAmount value suchen
if (!$amount && preg_match('/"currentAmount":[^}]*"value":"([0-9.]+)"/i', $html, $matches)) {
    $amount = floatval($matches[1]);
}

if ($amount === null) {
    // Fallback: Manuell eingetragener Wert oder Fehler
    echo json_encode([
        'status' => 'error',
        'error' => 'Betrag konnte nicht extrahiert werden',
        'html_preview' => substr($html, 0, 500)
    ]);
    exit;
}

// In settings_v2 speichern
require_once __DIR__ . '/../includes/db.php';

$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('paypal_pool_amount', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
$amount_str = number_format($amount, 2, '.', '');
$stmt->bind_param("ss", $amount_str, $amount_str);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'amount' => $amount,
    'formatted' => number_format($amount, 2, ',', '.') . ' €',
    'last_update' => date('Y-m-d H:i:s')
]);

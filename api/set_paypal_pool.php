<?php
// Manuelle Eingabe des PayPal Pool Betrags durch Admin
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$amount = floatval($data['amount'] ?? 0);

if ($amount < 0) {
    echo json_encode(['status' => 'error', 'error' => 'Betrag muss positiv sein']);
    exit;
}

$amount_str = number_format($amount, 2, '.', '');

$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('paypal_pool_amount', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
$stmt->bind_param("ss", $amount_str, $amount_str);

if ($stmt->execute()) {
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'amount' => $amount,
        'formatted' => number_format($amount, 2, ',', '.') . ' €'
    ]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Fehler beim Speichern']);
}

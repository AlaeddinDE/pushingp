<?php
/**
 * API: Gutschrift für Mitglieder die nicht dabei waren
 * Bucht faire Gutschrift aufs Konto
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

secure_session_start();

if (!isset($_SESSION['user_id']) || !is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Nur für Admins']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['mitglied_id']) || !isset($data['betrag'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'mitglied_id und betrag erforderlich']);
    exit;
}

$mitglied_id = intval($data['mitglied_id']);
$betrag = floatval($data['betrag']);
$beschreibung = $data['beschreibung'] ?? 'Gutschrift: Nicht dabei gewesen';
$admin_id = $_SESSION['user_id'];

if ($betrag <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Betrag muss positiv sein']);
    exit;
}

// Gutschrift als AUSGLEICH buchen (positive Transaktion)
$stmt = $conn->prepare("
    INSERT INTO transaktionen 
    (typ, betrag, mitglied_id, beschreibung, erstellt_von, status, datum) 
    VALUES ('AUSGLEICH', ?, ?, ?, ?, 'gebucht', NOW())
");

$stmt->bind_param('disi', $betrag, $mitglied_id, $beschreibung, $admin_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Gutschrift erfolgreich gebucht',
        'betrag' => $betrag,
        'mitglied_id' => $mitglied_id
    ]);
} else {
    $stmt->close();
    $conn->close();
    
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Datenbankfehler']);
}

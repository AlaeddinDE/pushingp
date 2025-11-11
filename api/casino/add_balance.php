<?php
/**
 * Add Balance to User Account
 * Used when winning in casino games
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Nicht eingeloggt']);
    exit;
}

$user_id = get_current_user_id();
$username = $_SESSION['username'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['amount'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Betrag fehlt']);
    exit;
}

$amount = floatval($input['amount']);

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Betrag']);
    exit;
}

try {
    // Add amount (create EINZAHLUNG transaction)
    $stmt = $conn->prepare("INSERT INTO transaktionen (mitglied_id, typ, betrag, beschreibung, status, datum) VALUES (?, 'EINZAHLUNG', ?, 'Casino Gewinn', 'gebucht', NOW())");
    $stmt->bind_param("id", $user_id, $amount);
    
    if (!$stmt->execute()) {
        throw new Exception("Fehler beim Gutschreiben");
    }
    
    $stmt->close();

    // Get new balance
    $stmt = $conn->prepare("SELECT 
        COALESCE(SUM(CASE 
            WHEN typ = 'EINZAHLUNG' THEN betrag
            WHEN typ IN ('AUSZAHLUNG', 'SCHADEN') THEN -betrag
            ELSE 0 
        END), 0) as balance 
        FROM transaktionen 
        WHERE mitglied_id = ? AND status = 'gebucht'");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($new_balance);
    $stmt->fetch();
    $stmt->close();

    $new_available = max(0, $new_balance - 10);
    
    echo json_encode([
        'status' => 'success',
        'balance' => round($new_available, 2),
        'added' => round($amount, 2)
    ]);

} catch (Exception $e) {
    error_log("add_balance.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Datenbankfehler']);
}

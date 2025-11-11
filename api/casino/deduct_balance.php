<?php
/**
 * Deduct Balance from User Account
 * Used when placing a bet in casino games
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
    // Get current balance
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
    $stmt->bind_result($current_balance);
    $stmt->fetch();
    $stmt->close();

    // Check if enough balance (with 10€ reserve)
    $available_balance = max(0, $current_balance - 10);
    
    if ($amount > $available_balance) {
        echo json_encode([
            'status' => 'error', 
            'error' => 'Nicht genug Guthaben',
            'available' => round($available_balance, 2)
        ]);
        exit;
    }

    // Deduct amount (create AUSZAHLUNG transaction)
    $stmt = $conn->prepare("INSERT INTO transaktionen (mitglied_id, typ, betrag, beschreibung, status, datum) VALUES (?, 'AUSZAHLUNG', ?, 'Casino Einsatz', 'gebucht', NOW())");
    $stmt->bind_param("id", $user_id, $amount);
    
    if (!$stmt->execute()) {
        throw new Exception("Fehler beim Abbuchen");
    }
    
    $stmt->close();

    // Return new balance
    $new_available = max(0, ($current_balance - $amount) - 10);
    
    echo json_encode([
        'status' => 'success',
        'balance' => round($new_available, 2),
        'deducted' => round($amount, 2)
    ]);

} catch (Exception $e) {
    error_log("deduct_balance.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Datenbankfehler']);
}

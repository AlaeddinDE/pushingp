<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

$to_user_id = intval($input['to_user_id'] ?? 0);
$amount = floatval($input['amount'] ?? 0);

if ($to_user_id <= 0 || $amount <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Parameter']);
    exit;
}

if ($to_user_id == $user_id) {
    echo json_encode(['status' => 'error', 'error' => 'Du kannst dir nicht selbst Geld senden']);
    exit;
}

// Get sender name
$stmt = $conn->prepare("SELECT name, username FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($sender_name, $sender_username);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'error' => 'Sender nicht gefunden']);
    exit;
}
$stmt->close();

// Get receiver name
$stmt = $conn->prepare("SELECT name, username FROM users WHERE id = ?");
$stmt->bind_param('i', $to_user_id);
$stmt->execute();
$stmt->bind_result($receiver_name, $receiver_username);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'error' => 'Empfänger nicht gefunden']);
    exit;
}
$stmt->close();

// Check sender balance from v_member_balance
$stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE username = ?");
$stmt->bind_param('s', $sender_username);
$stmt->execute();
$stmt->bind_result($sender_balance);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'error' => 'Kontostand nicht gefunden']);
    exit;
}
$stmt->close();

if ($sender_balance < $amount) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben']);
    exit;
}

// Create transactions
$conn->begin_transaction();

try {
    // Deduct from sender
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (mitglied_id, betrag, typ, beschreibung, status, created_at)
        VALUES (?, ?, 'AUSZAHLUNG', ?, 'gebucht', NOW())
    ");
    $negative_amount = -$amount;
    $desc_sender = "Geld gesendet an " . $receiver_name;
    $stmt->bind_param('ids', $user_id, $negative_amount, $desc_sender);
    $stmt->execute();
    $stmt->close();
    
    // Add to receiver
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (mitglied_id, betrag, typ, beschreibung, status, created_at)
        VALUES (?, ?, 'EINZAHLUNG', ?, 'gebucht', NOW())
    ");
    $desc_receiver = "Geld erhalten von " . $sender_name;
    $stmt->bind_param('ids', $to_user_id, $amount, $desc_receiver);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => number_format($amount, 2, ',', '.') . '€ an ' . $receiver_name . ' gesendet'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'error' => 'Transaktion fehlgeschlagen: ' . $e->getMessage()]);
}

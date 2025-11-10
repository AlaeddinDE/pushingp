<?php
// Chat Money Transfer API
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$data = json_decode(file_get_contents('php://input'), true);

$receiver_id = intval($data['receiver_id'] ?? 0);
$amount = floatval($data['amount'] ?? 0);

if ($receiver_id <= 0 || $amount <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Daten']);
    exit;
}

if ($receiver_id === $user_id) {
    echo json_encode(['status' => 'error', 'error' => 'Du kannst dir nicht selbst Geld senden']);
    exit;
}

// Get sender's current balance from view
$stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($sender_balance);
$stmt->fetch();
$stmt->close();

if ($sender_balance === null) {
    echo json_encode(['status' => 'error', 'error' => 'Sender nicht gefunden']);
    exit;
}

if ($sender_balance < $amount) {
    echo json_encode([
        'status' => 'error', 
        'error' => sprintf('Nicht genug Guthaben (Du hast: %.2f €)', $sender_balance)
    ]);
    exit;
}

// Get receiver info
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND status = 'active'");
$stmt->bind_param('i', $receiver_id);
$stmt->execute();
$stmt->bind_result($receiver_name);
$stmt->fetch();
$stmt->close();

if (!$receiver_name) {
    echo json_encode(['status' => 'error', 'error' => 'Empfänger nicht gefunden']);
    exit;
}

// Get sender info
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($sender_name);
$stmt->fetch();
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Deduct from sender - AUSZAHLUNG
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (
            typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum
        ) VALUES (
            'AUSZAHLUNG', 'POOL', ?, ?, ?, ?, NOW()
        )
    ");
    $desc_sender = "Geld gesendet an " . $receiver_name . " via Chat";
    $stmt->bind_param('disi', $amount, $user_id, $desc_sender, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Add to receiver - EINZAHLUNG
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (
            typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum
        ) VALUES (
            'EINZAHLUNG', 'POOL', ?, ?, ?, ?, NOW()
        )
    ");
    $desc_receiver = "Geld erhalten von " . $sender_name . " via Chat";
    $stmt->bind_param('disi', $amount, $receiver_id, $desc_receiver, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Update member_payment_status table for both sender and receiver
    $stmt = $conn->prepare("
        UPDATE member_payment_status mps
        JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
        SET mps.guthaben = vmb.balance
        WHERE mps.mitglied_id IN (?, ?)
    ");
    $stmt->bind_param('ii', $user_id, $receiver_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    // Get new balance
    $stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($new_balance);
    $stmt->fetch();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'new_balance' => $new_balance,
        'receiver_name' => $receiver_name
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Money transfer error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Transaktion fehlgeschlagen: ' . $e->getMessage()]);
}

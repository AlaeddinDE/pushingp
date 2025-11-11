<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$data = json_decode(file_get_contents('php://input'), true);
$bet = floatval($data['bet'] ?? 0);

if ($bet < 0.5 || $bet > 50) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Einsatz']);
    exit;
}

$stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

if ($balance === null) {
    echo json_encode(['status' => 'error', 'error' => 'Balance nicht gefunden']);
    exit;
}

if ($balance - $bet < 10) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben (10€ Reserve!)']);
    exit;
}

$multipliers = [
    ['value' => 0, 'weight' => 51, 'rotation' => 26],
    ['value' => 0.5, 'weight' => 21, 'rotation' => 67],
    ['value' => 1.0, 'weight' => 15, 'rotation' => 115],
    ['value' => 2.0, 'weight' => 9, 'rotation' => 165],
    ['value' => 5.0, 'weight' => 2.5, 'rotation' => 225],
    ['value' => 10.0, 'weight' => 1, 'rotation' => 290],
    ['value' => 50.0, 'weight' => 0.5, 'rotation' => 350]
];

$total = array_sum(array_column($multipliers, 'weight'));
$rand = (mt_rand() / mt_getrandmax()) * $total;  // 0.0 to $total
$sum = 0;
$result = $multipliers[0];

foreach ($multipliers as $mult) {
    $sum += $mult['weight'];
    if ($rand < $sum) {  // Changed from <= to <
        $result = $mult;
        break;
    }
}

$win_amount = $bet * $result['value'];

$conn->begin_transaction();

try {
    // Deduct bet
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
        VALUES ('AUSZAHLUNG', 'POOL', ?, ?, 'Casino Wheel Einsatz', ?, NOW())
    ");
    $stmt->bind_param('dii', $bet, $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Add winnings
    if ($win_amount > 0) {
        $stmt = $conn->prepare("
            INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
            VALUES ('EINZAHLUNG', 'POOL', ?, ?, 'Casino Wheel Gewinn', ?, NOW())
        ");
        $stmt->bind_param('dii', $win_amount, $user_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $stmt = $conn->prepare("
        UPDATE member_payment_status mps
        JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
        SET mps.guthaben = vmb.balance
        WHERE mps.mitglied_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    $result_json = json_encode($result);
    $stmt = $conn->prepare("
        INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result)
        VALUES (?, 'wheel', ?, ?, ?, ?)
    ");
    $stmt->bind_param('iddds', $user_id, $bet, $win_amount, $result['value'], $result_json);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    $stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($new_balance);
    $stmt->fetch();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'multiplier' => $result['value'],
        'rotation' => $result['rotation'],
        'win_amount' => $win_amount,
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Casino wheel error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Spiel fehlgeschlagen']);
}

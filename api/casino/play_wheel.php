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

// Wheel segments - MUST MATCH FRONTEND!
$multipliers = [
    ['value' => 0, 'weight' => 51],       // 51%
    ['value' => 0.5, 'weight' => 21],     // 21%
    ['value' => 1.0, 'weight' => 15],     // 15%
    ['value' => 2.0, 'weight' => 9],      // 9%
    ['value' => 5.0, 'weight' => 2.5],    // 2.5%
    ['value' => 10.0, 'weight' => 1],     // 1%
    ['value' => 50.0, 'weight' => 0.5]    // 0.5%
];

// Calculate total weight and rotation angles for each segment
$total = array_sum(array_column($multipliers, 'weight'));
$currentAngle = 0;

// Add rotation angle to each segment (center of segment)
foreach ($multipliers as $i => &$mult) {
    $segmentAngle = ($mult['weight'] / $total) * 360;
    $mult['rotation'] = $currentAngle + ($segmentAngle / 2); // Center of segment
    $currentAngle += $segmentAngle;
}
unset($mult);

// Random selection based on weights
$rand = (mt_rand() / mt_getrandmax()) * $total;
$sum = 0;
$result = $multipliers[0];

foreach ($multipliers as $mult) {
    $sum += $mult['weight'];
    if ($rand < $sum) {
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

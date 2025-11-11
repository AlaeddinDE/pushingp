<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'error' => 'Invalid request method']);
        exit;
    }

    if (!is_logged_in()) {
        echo json_encode(['status' => 'error', 'error' => 'Not authenticated']);
        exit;
    }

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['bet']) || !is_numeric($input['bet'])) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid bet amount']);
    exit;
}

$bet = floatval($input['bet']);

if ($bet < 0.5 || $bet > 50) {
    echo json_encode(['status' => 'error', 'error' => 'Bet must be between 0.50€ and 50€']);
    exit;
}

// Get user balance from transaktionen
$stmt = $conn->prepare("SELECT 
    COALESCE(SUM(CASE 
        WHEN typ = 'EINZAHLUNG' THEN betrag
        WHEN typ IN ('AUSZAHLUNG', 'SCHADEN') THEN -betrag
        ELSE 0 
    END), 0) as balance 
    FROM transaktionen 
    WHERE mitglied_id = ? AND status = 'gebucht'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

$balance = floatval($balance);
$casino_available_balance = max(0, $balance - 10.00);

if ($bet > $casino_available_balance) {
    echo json_encode(['status' => 'error', 'error' => 'Insufficient balance']);
    exit;
}

// Plinko game logic
// 9 slots with different multipliers
// Path simulation through pins
$slots = [
    ['multiplier' => 5.0, 'weight' => 1],      // 0 - leftmost
    ['multiplier' => 2.0, 'weight' => 3],      // 1
    ['multiplier' => 1.5, 'weight' => 5],      // 2
    ['multiplier' => 1.0, 'weight' => 10],     // 3
    ['multiplier' => 0.5, 'weight' => 15],     // 4 - center (most likely)
    ['multiplier' => 1.0, 'weight' => 10],     // 5
    ['multiplier' => 1.5, 'weight' => 5],      // 6
    ['multiplier' => 2.0, 'weight' => 3],      // 7
    ['multiplier' => 5.0, 'weight' => 1]       // 8 - rightmost
];

// Calculate total weight
$total_weight = array_sum(array_column($slots, 'weight'));

// Select random slot based on weights
$random = mt_rand(1, $total_weight);
$current_weight = 0;
$result_slot = 0;

foreach ($slots as $index => $slot) {
    $current_weight += $slot['weight'];
    if ($random <= $current_weight) {
        $result_slot = $index;
        break;
    }
}

$multiplier = $slots[$result_slot]['multiplier'];
$win_amount = $bet * $multiplier;
$profit = $win_amount - $bet;

// Generate ball path (8 rows of pins)
$ball_path = [];
$position = 4.0; // Start at center (0-8 range)

for ($row = 0; $row < 8; $row++) {
    // Each pin bounce can go left (-1) or right (+1)
    // Bias towards target slot
    $target_offset = ($result_slot - 4.0);
    $current_offset = ($position - 4.0);
    
    // Add randomness but bias towards target
    $direction = 0;
    if (mt_rand(0, 100) < 70) {
        // 70% chance to move towards target
        $direction = ($target_offset > $current_offset) ? 0.5 : -0.5;
    } else {
        // 30% random
        $direction = (mt_rand(0, 1) === 0) ? -0.5 : 0.5;
    }
    
    $position += $direction;
    $position = max(0, min(8, $position)); // Keep in bounds
    
    $ball_path[] = round($position, 2);
}

// Final position should land in result slot
$ball_path[] = $result_slot + 0.5; // Center of slot

// Update balance via transaction
if ($profit > 0) {
    $beschreibung = "Casino Plinko Gewinn (" . $multiplier . "x)";
    $trans_stmt = $conn->prepare("INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, erstellt_von, datum) VALUES ('EINZAHLUNG', ?, ?, ?, ?, NOW())");
    $trans_stmt->bind_param('disi', $profit, $user_id, $beschreibung, $user_id);
    $trans_stmt->execute();
    $trans_stmt->close();
} else if ($profit < 0) {
    $loss = abs($profit);
    $beschreibung = "Casino Plinko Verlust (" . $multiplier . "x)";
    $trans_stmt = $conn->prepare("INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, erstellt_von, datum) VALUES ('AUSZAHLUNG', ?, ?, ?, ?, NOW())");
    $trans_stmt->bind_param('disi', $loss, $user_id, $beschreibung, $user_id);
    $trans_stmt->execute();
    $trans_stmt->close();
}

// Get new balance
$stmt = $conn->prepare("SELECT 
    COALESCE(SUM(CASE 
        WHEN typ = 'EINZAHLUNG' THEN betrag
        WHEN typ IN ('AUSZAHLUNG', 'SCHADEN') THEN -betrag
        ELSE 0 
    END), 0) as balance 
    FROM transaktionen 
    WHERE mitglied_id = ? AND status = 'gebucht'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($new_balance);
$stmt->fetch();
$stmt->close();

// Log casino history
$stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, multiplier, win_amount) VALUES (?, 'plinko', ?, ?, ?)");
$stmt->bind_param('iddd', $user_id, $bet, $multiplier, $win_amount);
$stmt->execute();
$stmt->close();

    echo json_encode([
        'status' => 'success',
        'slot' => $result_slot,
        'slot_multiplier' => $multiplier,
        'multiplier' => $multiplier,
        'win' => $win_amount,
        'win_amount' => $win_amount,
        'profit' => $profit,
        'new_balance' => floatval($new_balance),
        'ball_path' => $ball_path
    ]);
} catch (Exception $e) {
    error_log("play_plinko.php Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Spielfehler: ' . $e->getMessage()]);
}

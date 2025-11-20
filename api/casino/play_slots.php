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

// Get current balance from v_member_balance
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

// Check reserve (10€ minimum)
if ($balance - $bet < 10) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben (10€ Reserve!)']);
    exit;
}

// Generate slots result - 4 REELS
$symbols = ['🍒', '🍋', '🍊', '🍉', '🍇', '🔔', '⭐', '7️⃣', '💎', 'BAR'];
$weights = [25, 25, 20, 18, 15, 12, 10, 8, 5, 2];

function weightedRandom($symbols, $weights) {
    $total = array_sum($weights);
    $rand = mt_rand(1, $total);
    $sum = 0;
    foreach ($weights as $i => $weight) {
        $sum += $weight;
        if ($rand <= $sum) return $symbols[$i];
    }
    return $symbols[0];
}

$result = [
    weightedRandom($symbols, $weights),
    weightedRandom($symbols, $weights),
    weightedRandom($symbols, $weights),
    weightedRandom($symbols, $weights)
];

// Check win - ALL 4 MUST MATCH
$win_amount = 0;
$multiplier = 0;

if ($result[0] === $result[1] && $result[1] === $result[2] && $result[2] === $result[3]) {
    switch ($result[0]) {
        case '💎':
            $multiplier = 500;
            break;
        case '7️⃣':
            $multiplier = 200;
            break;
        case '🔔':
            $multiplier = 100;
            break;
        case '⭐':
            $multiplier = 50;
            break;
        case 'BAR':
            $multiplier = 40;
            break;
        case '🍇':
            $multiplier = 30;
            break;
        case '🍉':
            $multiplier = 25;
            break;
        case '🍊':
            $multiplier = 20;
            break;
        case '🍋':
            $multiplier = 15;
            break;
        case '🍒':
            $multiplier = 10;
            break;
        default:
            $multiplier = 5;
    }
    $win_amount = $bet * $multiplier;
}

$profit = $win_amount - $bet;

// Start transaction
$conn->begin_transaction();

try {
    // ALWAYS deduct bet first
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
        VALUES ('AUSZAHLUNG', 'POOL', ?, ?, 'Casino Slots Einsatz', ?, NOW())
    ");
    $stmt->bind_param('dii', $bet, $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Add winnings if won
    if ($win_amount > 0) {
        $stmt = $conn->prepare("
            INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
            VALUES ('EINZAHLUNG', 'POOL', ?, ?, 'Casino Slots Gewinn', ?, NOW())
        ");
        $stmt->bind_param('dii', $win_amount, $user_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update member_payment_status
    $stmt = $conn->prepare("
        UPDATE member_payment_status mps
        JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
        SET mps.guthaben = vmb.balance
        WHERE mps.mitglied_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Log game
    $result_json = json_encode($result);
    $stmt = $conn->prepare("
        INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result)
        VALUES (?, 'slots', ?, ?, ?, ?)
    ");
    $stmt->bind_param('iddds', $user_id, $bet, $win_amount, $multiplier, $result_json);
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
        'result' => $result,
        'win_amount' => $win_amount,
        'multiplier' => $multiplier,
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Casino slots error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Spiel fehlgeschlagen']);
}

<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$data = json_decode(file_get_contents('php://input'), true);
$bet = floatval($data['bet'] ?? 0);
$is_freespin = ($data['freespin'] ?? false) === true;
$expanding_symbol = $data['expanding_symbol'] ?? null;

if ($bet < 0.50 || $bet > 50) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Einsatz']);
    exit;
}

// Get current balance and member_id (join users with v_member_balance via username)
$stmt = $conn->prepare("SELECT v.balance, v.id FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance, $member_id);
$stmt->fetch();
$stmt->close();

// Default to 0 if no balance found (non-member users)
$balance = floatval($balance ?? 0);
$member_id = intval($member_id ?? 0);
$available = max(0, $balance - 10.00);

// Check if user is a member
if ($member_id == 0) {
    echo json_encode(['status' => 'error', 'error' => 'Nur Mitglieder können Casino spielen']);
    exit;
}

// Check reserve (10€ minimum) - skip for freespins
if (!$is_freespin && $available < $bet) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben (10€ Reserve!)']);
    exit;
}

// Book of Ra symbols
$symbols = ['🅿️', '👑', '🦅', '⚱️', '🔱', '💎', '🎴', '🃏', '🎯'];
$weights = [2, 5, 8, 12, 15, 18, 20, 25, 30]; // P is rarest

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

// Generate 5 reels x 3 symbols = 15 symbols total
$result = [];
for ($i = 0; $i < 5; $i++) {
    $reel = [];
    for ($j = 0; $j < 3; $j++) {
        $reel[] = weightedRandom($symbols, $weights);
    }
    $result[] = $reel;
}

// Check for wins on each of 9 paylines (like Book of Ra)
$win_amount = 0;
$multiplier = 0;
$winning_lines = [];

// 9 Paylines like in Book of Ra
$paylines = [
    [1, 1, 1, 1, 1], // Line 1: Middle row
    [0, 0, 0, 0, 0], // Line 2: Top row
    [2, 2, 2, 2, 2], // Line 3: Bottom row
    [0, 1, 2, 1, 0], // Line 4: V-shape
    [2, 1, 0, 1, 2], // Line 5: Inverted V
    [1, 0, 0, 0, 1], // Line 6: W-shape top
    [1, 2, 2, 2, 1], // Line 7: W-shape bottom
    [0, 0, 1, 2, 2], // Line 8: Rising
    [2, 2, 1, 0, 0]  // Line 9: Falling
];

foreach ($paylines as $line_idx => $line) {
    // Get symbols on this payline
    $line_symbols = [];
    for ($i = 0; $i < 5; $i++) {
        $line_symbols[] = $result[$i][$line[$i]];
    }
    
    // Check for matching symbols from left to right
    $first_symbol = $line_symbols[0];
    $match_count = 1;
    
    for ($i = 1; $i < 5; $i++) {
        if ($line_symbols[$i] === $first_symbol) {
            $match_count++;
        } else {
            break;
        }
    }
    
    if ($match_count >= 3) {
        $temp_multiplier = 0;
        
        switch ($first_symbol) {
            case '🅿️':
                if ($match_count == 5) $temp_multiplier = 100;
                elseif ($match_count == 4) $temp_multiplier = 50;
                elseif ($match_count == 3) $temp_multiplier = 20;
                break;
            case '👑':
                if ($match_count == 5) $temp_multiplier = 50;
                elseif ($match_count == 4) $temp_multiplier = 25;
                elseif ($match_count == 3) $temp_multiplier = 15;
                break;
            case '🦅':
                if ($match_count == 5) $temp_multiplier = 25;
                elseif ($match_count == 4) $temp_multiplier = 15;
                elseif ($match_count == 3) $temp_multiplier = 10;
                break;
            case '⚱️':
                if ($match_count == 5) $temp_multiplier = 15;
                elseif ($match_count == 4) $temp_multiplier = 10;
                elseif ($match_count == 3) $temp_multiplier = 6;
                break;
            case '🔱':
                if ($match_count == 5) $temp_multiplier = 10;
                elseif ($match_count == 4) $temp_multiplier = 8;
                elseif ($match_count == 3) $temp_multiplier = 5;
                break;
            case '💎':
                if ($match_count == 5) $temp_multiplier = 8;
                elseif ($match_count == 4) $temp_multiplier = 6;
                elseif ($match_count == 3) $temp_multiplier = 4;
                break;
            case '🎴':
                if ($match_count == 5) $temp_multiplier = 6;
                elseif ($match_count == 4) $temp_multiplier = 4;
                elseif ($match_count == 3) $temp_multiplier = 3;
                break;
            case '🃏':
                if ($match_count == 5) $temp_multiplier = 4;
                elseif ($match_count == 4) $temp_multiplier = 3;
                elseif ($match_count == 3) $temp_multiplier = 2;
                break;
            case '🎯':
                if ($match_count == 5) $temp_multiplier = 3;
                elseif ($match_count == 4) $temp_multiplier = 2;
                elseif ($match_count == 3) $temp_multiplier = 1.5;
                break;
        }
        
        $win_amount += $bet * $temp_multiplier;
        $winning_lines[] = ['line' => $line_idx, 'symbol' => $first_symbol, 'count' => $match_count, 'multiplier' => $temp_multiplier];
        
        if ($temp_multiplier > $multiplier) {
            $multiplier = $temp_multiplier;
        }
    }
}

$profit = $win_amount - ($is_freespin ? 0 : $bet);

// Check for freespin trigger (3+ P symbols anywhere on screen)
$p_count = 0;
foreach ($result as $reel) {
    foreach ($reel as $symbol) {
        if ($symbol === '🅿️') $p_count++;
    }
}

$freespins_triggered = false;
$freespins_count = 0;
$freespin_expanding_symbol = null;

if (!$is_freespin && $p_count >= 3) {
    $freespins_triggered = true;
    $freespins_count = 10; // Standard: 10 Freispiele
    
    // Random expanding symbol (nicht P)
    $non_p_symbols = array_diff($symbols, ['🅿️']);
    $freespin_expanding_symbol = $non_p_symbols[array_rand($non_p_symbols)];
}

// Start transaction
$conn->begin_transaction();

try {
    // Deduct bet (only if not freespin)
    if (!$is_freespin) {
        $stmt = $conn->prepare("
            INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
            VALUES ('AUSZAHLUNG', 'POOL', ?, ?, 'Casino Book of P Einsatz', ?, NOW())
        ");
        $stmt->bind_param('dii', $bet, $member_id, $member_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Add winnings if won
    if ($win_amount > 0) {
        $stmt = $conn->prepare("
            INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
            VALUES ('EINZAHLUNG', 'POOL', ?, ?, 'Casino Book of P Gewinn', ?, NOW())
        ");
        $stmt->bind_param('dii', $win_amount, $member_id, $member_id);
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
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $stmt->close();
    
    // Log game
    $result_json = json_encode($result);
    $stmt = $conn->prepare("
        INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result)
        VALUES (?, 'book', ?, ?, ?, ?)
    ");
    $stmt->bind_param('iddds', $user_id, $bet, $win_amount, $multiplier, $result_json);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    // Get new balance
    $stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($new_balance);
    $stmt->fetch();
    $stmt->close();
    
    $new_balance = floatval($new_balance ?? 0);
    $new_available = max(0, $new_balance - 10.00);
    
    echo json_encode([
        'status' => 'success',
        'result' => $result,
        'win_amount' => $win_amount,
        'multiplier' => $multiplier,
        'new_balance' => $new_available,
        'freespins_triggered' => $freespins_triggered,
        'freespins_count' => $freespins_count,
        'expanding_symbol' => $freespin_expanding_symbol,
        'winning_lines' => $winning_lines
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Casino book error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Spiel fehlgeschlagen: ' . $e->getMessage()]);
}

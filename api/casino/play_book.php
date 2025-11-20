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

// Check for wins - Find connected clusters (orthogonal adjacency only)
$win_amount = 0;
$multiplier = 0;
$winning_lines = [];

// Payout table (per symbol based on cluster size)
$payouts = [
    '🅿️' => [3 => 5, 4 => 20, 5 => 50, 6 => 150, 7 => 300, 8 => 600],
    '👑' => [3 => 3, 4 => 10, 5 => 30, 6 => 80, 7 => 150, 8 => 300],
    '🦅' => [3 => 2, 4 => 8, 5 => 20, 6 => 50, 7 => 100, 8 => 200],
    '⚱️' => [3 => 2, 4 => 6, 5 => 15, 6 => 40, 7 => 80, 8 => 150],
    '🔱' => [3 => 1.5, 4 => 5, 5 => 12, 6 => 30, 7 => 60, 8 => 120],
    '💎' => [3 => 1.2, 4 => 4, 5 => 10, 6 => 25, 7 => 50, 8 => 100],
    '🎴' => [3 => 1, 4 => 3, 5 => 8, 6 => 20, 7 => 40, 8 => 80],
    '🃏' => [3 => 0.8, 4 => 2.5, 5 => 6, 6 => 15, 7 => 30, 8 => 60],
    '🎯' => [3 => 0.5, 4 => 2, 5 => 5, 6 => 12, 7 => 25, 8 => 50]
];

// Convert result[reel][row] to grid[row][reel] for easier processing
$grid = [];
for ($row = 0; $row < 3; $row++) {
    $grid[$row] = [];
    for ($reel = 0; $reel < 5; $reel++) {
        $grid[$row][$reel] = $result[$reel][$row];
    }
}

// Find all clusters using flood fill
$visited = array_fill(0, 3, array_fill(0, 5, false));

for ($row = 0; $row < 3; $row++) {
    for ($col = 0; $col < 5; $col++) {
        if (!$visited[$row][$col]) {
            $symbol = $grid[$row][$col];
            $cluster = [];
            floodFill($grid, $visited, $row, $col, $symbol, $cluster);
            
            // Win if cluster has 3+ connected symbols
            $count = count($cluster);
            if ($count >= 3 && isset($payouts[$symbol])) {
                // Find highest payout for this cluster size
                $temp_multiplier = 0;
                foreach ($payouts[$symbol] as $minCount => $mult) {
                    if ($count >= $minCount) {
                        $temp_multiplier = $mult;
                    }
                }
                
                if ($temp_multiplier > 0) {
                    $win = $bet * $temp_multiplier;
                    $win_amount += $win;
                    
                    if ($temp_multiplier > $multiplier) {
                        $multiplier = $temp_multiplier;
                    }
                    
                    // Convert cluster positions back to reel/row format
                    $positions = [];
                    foreach ($cluster as $pos) {
                        $positions[] = ['reel' => $pos[1], 'row' => $pos[0]];
                    }
                    
                    $winning_lines[] = [
                        'line' => 0, 
                        'symbol' => $symbol, 
                        'count' => $count, 
                        'multiplier' => $temp_multiplier,
                        'win_amount' => $win,
                        'positions' => $positions
                    ];
                }
            }
        }
    }
}

// Flood fill function to find connected symbols (orthogonal only)
function floodFill(&$grid, &$visited, $row, $col, $symbol, &$cluster) {
    if ($row < 0 || $row >= 3 || $col < 0 || $col >= 5 || $visited[$row][$col]) {
        return;
    }
    if ($grid[$row][$col] !== $symbol) {
        return;
    }
    
    $visited[$row][$col] = true;
    $cluster[] = [$row, $col];
    
    // Check 4 directions (up, down, left, right)
    floodFill($grid, $visited, $row - 1, $col, $symbol, $cluster);
    floodFill($grid, $visited, $row + 1, $col, $symbol, $cluster);
    floodFill($grid, $visited, $row, $col - 1, $symbol, $cluster);
    floodFill($grid, $visited, $row, $col + 1, $symbol, $cluster);
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

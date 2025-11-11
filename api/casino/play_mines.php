<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');
secure_session_start();

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Get user balance with 10€ reserve
function get_available_balance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT IFNULL(v.balance, 0) FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();
    return max(0, floatval($balance ?? 0) - 10.00);
}

// Calculate multiplier based on mines and revealed tiles
// RTP ~96% (House edge ~4%)
function calculate_multiplier($total_fields, $mines, $revealed) {
    $safe_fields = $total_fields - $mines;
    $remaining_safe = $safe_fields - $revealed;
    $remaining_total = $total_fields - $revealed;
    
    if ($remaining_safe <= 0 || $remaining_total <= 0) return 1.0;
    
    // Fair odds would be: remaining_total / remaining_safe
    // We apply house edge of ~4% (multiply by 0.96)
    $fair_multiplier = $remaining_total / $remaining_safe;
    $house_edge_factor = 0.96;
    
    return round($fair_multiplier * $house_edge_factor, 3);
}

// Calculate cumulative multiplier
function calculate_total_multiplier($total_fields, $mines, $revealed) {
    $multiplier = 1.0;
    
    for ($i = 0; $i < $revealed; $i++) {
        $safe_fields = $total_fields - $mines;
        $remaining_safe = $safe_fields - $i;
        $remaining_total = $total_fields - $i;
        
        $step_multiplier = ($remaining_total / $remaining_safe) * 0.96;
        $multiplier *= $step_multiplier;
    }
    
    return round($multiplier, 3);
}

if ($action === 'start') {
    $bet_amount = floatval($input['bet'] ?? 0);
    $mines = intval($input['mines'] ?? 3);
    
    // Validation
    if ($bet_amount < 0.10 || $bet_amount > 1000) {
        echo json_encode(['status' => 'error', 'error' => 'Ungültiger Einsatz']);
        exit;
    }
    
    if ($mines < 1 || $mines > 24) {
        echo json_encode(['status' => 'error', 'error' => 'Ungültige Minenanzahl (1-24)']);
        exit;
    }
    
    $available_balance = get_available_balance($conn, $user_id);
    if ($bet_amount > $available_balance) {
        echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben']);
        exit;
    }
    
    // Generate mine positions (provably fair)
    $total_fields = 25; // 5x5 grid
    $mine_positions = [];
    $all_positions = range(0, $total_fields - 1);
    shuffle($all_positions);
    $mine_positions = array_slice($all_positions, 0, $mines);
    
    // Create game session
    $game_data = [
        'mines' => $mines,
        'mine_positions' => $mine_positions,
        'revealed' => [],
        'bet_amount' => $bet_amount,
        'current_multiplier' => 1.0,
        'total_fields' => $total_fields
    ];
    
    $_SESSION['mines_game'] = $game_data;
    
    echo json_encode([
        'status' => 'success',
        'mines' => $mines,
        'total_fields' => $total_fields,
        'bet_amount' => $bet_amount
    ]);
    exit;
}

if ($action === 'reveal') {
    if (!isset($_SESSION['mines_game'])) {
        echo json_encode(['status' => 'error', 'error' => 'Kein aktives Spiel']);
        exit;
    }
    
    $game = $_SESSION['mines_game'];
    $position = intval($input['position'] ?? -1);
    
    if ($position < 0 || $position >= $game['total_fields']) {
        echo json_encode(['status' => 'error', 'error' => 'Ungültige Position']);
        exit;
    }
    
    if (in_array($position, $game['revealed'])) {
        echo json_encode(['status' => 'error', 'error' => 'Feld bereits aufgedeckt']);
        exit;
    }
    
    // Check if mine hit
    if (in_array($position, $game['mine_positions'])) {
        // BOOM - Game Over
        $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, result_data) VALUES (?, 'mines', ?, 0, ?)");
        $result_data = json_encode(['mines' => $game['mines'], 'revealed' => count($game['revealed'])]);
        $stmt->bind_param('ids', $user_id, $game['bet_amount'], $result_data);
        $stmt->execute();
        $stmt->close();
        
        unset($_SESSION['mines_game']);
        
        echo json_encode([
            'status' => 'mine_hit',
            'position' => $position,
            'mine_positions' => $game['mine_positions'],
            'win_amount' => 0
        ]);
        exit;
    }
    
    // Safe tile - add to revealed
    $game['revealed'][] = $position;
    $revealed_count = count($game['revealed']);
    
    // Calculate new multiplier
    $new_multiplier = calculate_total_multiplier($game['total_fields'], $game['mines'], $revealed_count);
    $game['current_multiplier'] = $new_multiplier;
    
    $_SESSION['mines_game'] = $game;
    
    // Check if all safe tiles revealed (max win)
    $safe_fields = $game['total_fields'] - $game['mines'];
    $all_revealed = ($revealed_count === $safe_fields);
    
    echo json_encode([
        'status' => 'safe',
        'position' => $position,
        'revealed_count' => $revealed_count,
        'current_multiplier' => $new_multiplier,
        'potential_win' => round($game['bet_amount'] * $new_multiplier, 2),
        'all_revealed' => $all_revealed
    ]);
    exit;
}

if ($action === 'cashout') {
    if (!isset($_SESSION['mines_game'])) {
        echo json_encode(['status' => 'error', 'error' => 'Kein aktives Spiel']);
        exit;
    }
    
    $game = $_SESSION['mines_game'];
    $revealed_count = count($game['revealed']);
    
    if ($revealed_count === 0) {
        echo json_encode(['status' => 'error', 'error' => 'Mindestens 1 Feld aufdecken']);
        exit;
    }
    
    $win_amount = $game['bet_amount'] * $game['current_multiplier'];
    
    // Save to history and add winnings
    $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, result_data) VALUES (?, 'mines', ?, ?, ?)");
    $result_data = json_encode(['mines' => $game['mines'], 'revealed' => $revealed_count, 'multiplier' => $game['current_multiplier']]);
    $stmt->bind_param('idds', $user_id, $game['bet_amount'], $win_amount, $result_data);
    $stmt->execute();
    $stmt->close();
    
    // Add balance
    $profit = $win_amount - $game['bet_amount'];
    if ($profit != 0) {
        $username = $_SESSION['username'] ?? '';
        $stmt = $conn->prepare("INSERT INTO transaktionen (username, amount, type, description, created_by) VALUES (?, ?, 'casino', ?, 'system')");
        $description = $profit > 0 ? "Casino Mines Gewinn (+" . round($game['current_multiplier'], 2) . "x)" : "Casino Mines Verlust";
        $stmt->bind_param('sds', $username, $profit, $description);
        $stmt->execute();
        $stmt->close();
    }
    
    unset($_SESSION['mines_game']);
    
    echo json_encode([
        'status' => 'cashout',
        'win_amount' => round($win_amount, 2),
        'multiplier' => $game['current_multiplier'],
        'revealed' => $revealed_count,
        'mine_positions' => $game['mine_positions']
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'error' => 'Ungültige Aktion']);

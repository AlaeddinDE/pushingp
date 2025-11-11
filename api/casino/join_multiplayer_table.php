<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();

$input = json_decode(file_get_contents('php://input'), true);
$table_id = isset($input['table_id']) ? intval($input['table_id']) : 0;
$bet_amount = isset($input['bet_amount']) ? floatval($input['bet_amount']) : 0;

if ($table_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Tisch-ID']);
    exit;
}

// Get table info
$stmt = $conn->prepare("
    SELECT max_players, current_players, min_bet, max_bet, status
    FROM casino_multiplayer_tables
    WHERE id = ?
");
$stmt->bind_param('i', $table_id);
$stmt->execute();
$stmt->bind_result($max_players, $current_players, $min_bet, $max_bet, $status);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'error' => 'Tisch nicht gefunden']);
    exit;
}
$stmt->close();

// Check if table is full or not waiting
if ($current_players >= $max_players) {
    echo json_encode(['status' => 'error', 'error' => 'Tisch ist voll!']);
    exit;
}

if ($status !== 'waiting') {
    echo json_encode(['status' => 'error', 'error' => 'Spiel läuft bereits!']);
    exit;
}

// Validate bet
if ($bet_amount < $min_bet || $bet_amount > $max_bet) {
    echo json_encode(['status' => 'error', 'error' => "Einsatz muss zwischen {$min_bet}€ und {$max_bet}€ liegen"]);
    exit;
}

// Check balance
$stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($db_balance);
$stmt->fetch();
$stmt->close();

$available_balance = max(0, floatval($db_balance ?? 0) - 10.00);

if ($bet_amount > $available_balance) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben!']);
    exit;
}

// Check if user already joined
$stmt = $conn->prepare("SELECT COUNT(*) FROM casino_multiplayer_players WHERE table_id = ? AND user_id = ?");
$stmt->bind_param('ii', $table_id, $user_id);
$stmt->execute();
$stmt->bind_result($already_joined);
$stmt->fetch();
$stmt->close();

if ($already_joined > 0) {
    echo json_encode(['status' => 'error', 'error' => 'Du bist bereits am Tisch!']);
    exit;
}

// Add player to table
$position = $current_players;
$stmt = $conn->prepare("
    INSERT INTO casino_multiplayer_players
    (table_id, user_id, bet_amount, position, status)
    VALUES (?, ?, ?, ?, 'waiting')
");
$stmt->bind_param('iidi', $table_id, $user_id, $bet_amount, $position);
$stmt->execute();
$stmt->close();

// Update player count
$stmt = $conn->prepare("UPDATE casino_multiplayer_tables SET current_players = current_players + 1 WHERE id = ?");
$stmt->bind_param('i', $table_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Erfolgreich beigetreten!',
    'table_id' => $table_id
]);

<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;

$input = json_decode(file_get_contents('php://input'), true);

$table_name = $input['table_name'] ?? 'Blackjack Tisch';
$min_bet = isset($input['min_bet']) ? floatval($input['min_bet']) : 1.00;
$max_bet = isset($input['max_bet']) ? floatval($input['max_bet']) : 50.00;
$max_players = isset($input['max_players']) ? intval($input['max_players']) : 4;
$game_type = $input['game_type'] ?? 'blackjack';

// Validate
if ($min_bet < 0.5 || $min_bet > 50) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Min-Einsatz']);
    exit;
}

if ($max_bet < $min_bet || $max_bet > 100) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Max-Einsatz']);
    exit;
}

if ($max_players < 2 || $max_players > 6) {
    echo json_encode(['status' => 'error', 'error' => 'Max. Spieler: 2-6']);
    exit;
}

// Check if user already has an active table
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM casino_multiplayer_tables t
    INNER JOIN casino_multiplayer_players p ON t.id = p.table_id
    WHERE p.user_id = ? AND t.status IN ('waiting', 'playing')
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($active_count);
$stmt->fetch();
$stmt->close();

if ($active_count > 0) {
    echo json_encode(['status' => 'error', 'error' => 'Du bist bereits an einem Tisch!']);
    exit;
}

// Create table
$stmt = $conn->prepare("
    INSERT INTO casino_multiplayer_tables 
    (host_user_id, game_type, table_name, max_players, min_bet, max_bet, status, current_players)
    VALUES (?, ?, ?, ?, ?, ?, 'waiting', 1)
");
$stmt->bind_param('issiii', $user_id, $game_type, $table_name, $max_players, $min_bet, $max_bet);
$stmt->execute();
$table_id = $stmt->insert_id;
$stmt->close();

// Add creator as first player
$stmt = $conn->prepare("
    INSERT INTO casino_multiplayer_players
    (table_id, user_id, bet_amount, position, status)
    VALUES (?, ?, ?, 0, 'waiting')
");
$stmt->bind_param('iid', $table_id, $user_id, $min_bet);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'table_id' => $table_id,
    'message' => 'Tisch erstellt! Warte auf Spieler...'
]);

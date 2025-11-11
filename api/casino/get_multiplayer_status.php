<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();

// Get count of active waiting tables
$stmt = $conn->prepare("
    SELECT COUNT(*) as table_count,
           SUM(max_players - current_players) as available_slots
    FROM casino_multiplayer_tables 
    WHERE status = 'waiting' 
    AND current_players < max_players
    AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 30
");
$stmt->execute();
$stmt->bind_result($table_count, $available_slots);
$stmt->fetch();
$stmt->close();

// Get user's active table if any
$stmt = $conn->prepare("
    SELECT t.id, t.status, t.game_type
    FROM casino_multiplayer_tables t
    INNER JOIN casino_multiplayer_players p ON t.id = p.table_id
    WHERE p.user_id = ? AND t.status IN ('waiting', 'playing')
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($active_table_id, $active_status, $active_game);
$has_active = $stmt->fetch();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'available_tables' => intval($table_count ?? 0),
    'available_slots' => intval($available_slots ?? 0),
    'user_in_game' => $has_active,
    'active_table_id' => $has_active ? $active_table_id : null,
    'active_status' => $has_active ? $active_status : null,
    'active_game' => $has_active ? $active_game : null
]);

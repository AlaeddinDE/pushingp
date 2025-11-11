<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();

// Get all available tables
$stmt = $conn->prepare("
    SELECT 
        t.id,
        t.table_name,
        t.game_type,
        t.min_bet,
        t.max_bet,
        t.max_players,
        t.current_players,
        t.status,
        t.created_at,
        u.name as host_name,
        u.username as host_username
    FROM casino_multiplayer_tables t
    INNER JOIN users u ON t.host_user_id = u.id
    WHERE t.status = 'waiting'
    AND t.current_players < t.max_players
    AND TIMESTAMPDIFF(MINUTE, t.created_at, NOW()) < 30
    ORDER BY t.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();

$tables = [];
while ($row = $result->fetch_assoc()) {
    $tables[] = $row;
}
$stmt->close();

echo json_encode([
    'status' => 'success',
    'tables' => $tables
]);

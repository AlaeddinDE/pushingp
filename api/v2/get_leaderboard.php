<?php
/**
 * API: Get XP Leaderboard
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xp_system.php';
secure_session_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}

$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 10;

$leaderboard = get_leaderboard($limit);

echo json_encode([
    'status' => 'success',
    'data' => $leaderboard
]);

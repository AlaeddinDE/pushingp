<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$include_inactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';

$query = "SELECT 
    u.id,
    u.username,
    u.name,
    u.email,
    u.role,
    u.status,
    u.pflicht_monatlich,
    u.shift_enabled,
    u.shift_mode,
    u.aktiv_ab,
    u.inaktiv_ab,
    u.created_at,
    u.last_login,
    COALESCE(mb.balance, 0.00) as balance
FROM users u
LEFT JOIN v_member_balance mb ON u.name = mb.mitglied_name
";

if (!$include_inactive) {
    $query .= " WHERE u.status = 'active'";
}

$query .= " ORDER BY u.name ASC";

$result = $conn->query($query);

if (!$result) {
    echo json_encode(['status' => 'error', 'error' => 'Database error']);
    exit;
}

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

echo json_encode([
    'status' => 'success',
    'data' => $members,
    'count' => count($members)
]);

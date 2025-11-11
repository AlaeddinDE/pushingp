<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht eingeloggt']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT 
    COALESCE((SELECT SUM(CASE 
        WHEN typ = 'Einzahlung' OR typ = 'Gutschrift' THEN betrag
        WHEN typ = 'Auszahlung' OR typ = 'Schaden' THEN -betrag
        ELSE 0 
    END) FROM transactions_v2 WHERE member_id = ?), 0) as balance");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

// 10€ Reserve abziehen
$available_balance = max(0, $balance - 10);

echo json_encode([
    'status' => 'success',
    'balance' => round($available_balance, 2)
]);

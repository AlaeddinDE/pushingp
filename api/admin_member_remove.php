<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id'] ?? 0);
$reason = trim($data['reason'] ?? 'Entfernt durch Admin');

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid user_id']);
    exit;
}

if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['status' => 'error', 'error' => 'Kann sich nicht selbst entfernen']);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET status = 'inactive', inaktiv_ab = NOW() WHERE id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $stmt->close();
    
    $log_stmt = $conn->prepare("INSERT INTO admin_member_actions (admin_id, action_type, target_user_id, reason) VALUES (?, 'remove', ?, ?)");
    $log_stmt->bind_param("iis", $_SESSION['user_id'], $user_id, $reason);
    $log_stmt->execute();
    $log_stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Mitglied entfernt']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Mitglied nicht gefunden']);
}

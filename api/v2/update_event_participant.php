<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
secure_session_start();
require_login();

if (!is_admin()) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$event_id = intval($_POST['event_id'] ?? 0);
$user_id = intval($_POST['user_id'] ?? 0);

if (!$event_id || !$user_id) {
    echo json_encode(['status' => 'error', 'error' => 'Missing ID']);
    exit;
}

if ($action === 'add' || $action === 'update') {
    $status = $_POST['status'] ?? 'coming';
    if (!in_array($status, ['coming', 'declined', 'no_show'])) {
        echo json_encode(['status' => 'error', 'error' => 'Invalid status']);
        exit;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO event_participants (event_id, mitglied_id, status) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE status = VALUES(status)
    ");
    $stmt->bind_param('iis', $event_id, $user_id, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'error' => $conn->error]);
    }
    $stmt->close();
} 
elseif ($action === 'remove') {
    $stmt = $conn->prepare("DELETE FROM event_participants WHERE event_id = ? AND mitglied_id = ?");
    $stmt->bind_param('ii', $event_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'error' => $conn->error]);
    }
    $stmt->close();
} 
else {
    echo json_encode(['status' => 'error', 'error' => 'Invalid action']);
}

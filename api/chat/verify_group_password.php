<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

$group_id = intval($input['group_id'] ?? 0);
$password = $input['password'] ?? '';

if (!$group_id) {
    echo json_encode(['status' => 'error', 'error' => 'Group ID required']);
    exit;
}

try {
    // Get group info
    $stmt = $conn->prepare("
        SELECT is_protected, password_hash 
        FROM chat_groups 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['is_protected'] == 1) {
            // Verify password
            if (!password_verify($password, $row['password_hash'])) {
                echo json_encode(['status' => 'error', 'error' => 'Falsches Passwort']);
                exit;
            }
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Group not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

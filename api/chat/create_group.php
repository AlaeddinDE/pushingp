<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

$group_name = trim($input['name'] ?? '');
$members = $input['members'] ?? [];
$password = $input['password'] ?? null;

if (!$group_name) {
    echo json_encode(['status' => 'error', 'error' => 'Group name required']);
    exit;
}

if (!is_array($members) || count($members) === 0) {
    echo json_encode(['status' => 'error', 'error' => 'At least one member required']);
    exit;
}

try {
    // Create group
    $is_protected = $password ? 1 : 0;
    $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO chat_groups (name, created_by, password_hash, is_protected, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('sssi', $group_name, $user_id, $password_hash, $is_protected);
    
    if ($stmt->execute()) {
        $group_id = $conn->insert_id;
        
        // Add creator as member
        $stmt2 = $conn->prepare("
            INSERT INTO chat_group_members (group_id, user_id, joined_at)
            VALUES (?, ?, NOW())
        ");
        $stmt2->bind_param('ii', $group_id, $user_id);
        $stmt2->execute();
        
        // Add selected members
        foreach ($members as $member_id) {
            $member_id = intval($member_id);
            if ($member_id > 0 && $member_id != $user_id) {
                $stmt3 = $conn->prepare("
                    INSERT IGNORE INTO chat_group_members (group_id, user_id, joined_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt3->bind_param('ii', $group_id, $member_id);
                $stmt3->execute();
            }
        }
        
        echo json_encode(['status' => 'success', 'group_id' => $group_id]);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to create group']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

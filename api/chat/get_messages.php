<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$is_admin = is_admin();
$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (!$type || !$id) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

try {
    $messages = [];
    
    if ($type === 'user') {
        // Private chat - load messages between current user and selected user
        // Admins can see all chats
        if ($is_admin) {
            $stmt = $conn->prepare("
                SELECT cm.*, u.name as sender_name
                FROM chat_messages cm
                LEFT JOIN users u ON u.id = cm.sender_id
                WHERE ((cm.sender_id = ? AND cm.receiver_id = ?) 
                    OR (cm.sender_id = ? AND cm.receiver_id = ?))
                AND cm.group_id IS NULL
                ORDER BY cm.created_at ASC
            ");
            $stmt->bind_param('iiii', $user_id, $id, $id, $user_id);
        } else {
            $stmt = $conn->prepare("
                SELECT cm.*, u.name as sender_name
                FROM chat_messages cm
                LEFT JOIN users u ON u.id = cm.sender_id
                WHERE ((cm.sender_id = ? AND cm.receiver_id = ?) 
                    OR (cm.sender_id = ? AND cm.receiver_id = ?))
                AND cm.group_id IS NULL
                ORDER BY cm.created_at ASC
            ");
            $stmt->bind_param('iiii', $user_id, $id, $id, $user_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        // Mark as read
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE receiver_id = $user_id AND sender_id = $id");
        
    } elseif ($type === 'group') {
        // Group chat - load messages for this group
        // Admins can see ALL groups, normal users only their groups
        if ($is_admin) {
            // Admin: Kann alle Gruppenchats lesen
            $stmt = $conn->prepare("
                SELECT cm.*, u.name as sender_name
                FROM chat_messages cm
                LEFT JOIN users u ON u.id = cm.sender_id
                WHERE cm.group_id = ?
                ORDER BY cm.created_at ASC
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
        } else {
            // Normal user: Nur Gruppen wo er Mitglied ist
            $check = $conn->query("
                SELECT 1 FROM chat_group_members 
                WHERE group_id = $id AND user_id = $user_id
            ");
            
            if ($check && $check->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT cm.*, u.name as sender_name
                    FROM chat_messages cm
                    LEFT JOIN users u ON u.id = cm.sender_id
                    WHERE cm.group_id = ?
                    ORDER BY cm.created_at ASC
                ");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $messages[] = $row;
                }
            }
        }
    }
    
    echo json_encode(['status' => 'success', 'messages' => $messages]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

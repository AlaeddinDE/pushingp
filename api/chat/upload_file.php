<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$is_admin = is_admin();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'error' => 'No file uploaded']);
    exit;
}

$type = $_POST['type'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$type || !$id) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

try {
    $file = $_FILES['file'];
    $file_name = basename($file['name']);
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    
    // Max 1GB
    if ($file_size > 1024 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'error' => 'File too large (max 1GB)']);
        exit;
    }
    
    // Generate unique filename
    $ext = pathinfo($file_name, PATHINFO_EXTENSION);
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
    $unique_name = $safe_name . '_' . time() . '_' . uniqid() . '.' . $ext;
    
    $upload_dir = __DIR__ . '/../../uploads/chat/';
    $upload_path = $upload_dir . $unique_name;
    $web_path = '/uploads/chat/' . $unique_name;
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if (move_uploaded_file($file_tmp, $upload_path)) {
        // Insert message with file
        if ($type === 'user') {
            $stmt = $conn->prepare("
                INSERT INTO chat_messages (sender_id, receiver_id, message, file_path, file_name, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $empty_msg = '📎 Datei';
            $stmt->bind_param('iisssi', $user_id, $id, $empty_msg, $web_path, $file_name, $file_size);
            
        } elseif ($type === 'group') {
            // Group file upload - Admins können in alle Gruppen uploaden
            if (!$is_admin) {
                // Normal user: Check if member
                $check = $conn->query("
                    SELECT 1 FROM chat_group_members 
                    WHERE group_id = $id AND user_id = $user_id
                ");
                
                if (!$check || $check->num_rows === 0) {
                    echo json_encode(['status' => 'error', 'error' => 'Not a member of this group']);
                    exit;
                }
            }
            
            $stmt = $conn->prepare("
                INSERT INTO chat_messages (sender_id, group_id, message, file_path, file_name, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $empty_msg = '📎 Datei';
            $stmt->bind_param('iisssi', $user_id, $id, $empty_msg, $web_path, $file_name, $file_size);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message_id' => $conn->insert_id,
                'file_path' => $web_path,
                'file_name' => $file_name
            ]);
        } else {
            echo json_encode(['status' => 'error', 'error' => 'Failed to save message']);
        }
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to upload file']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

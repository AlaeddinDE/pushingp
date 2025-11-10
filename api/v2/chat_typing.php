<?php
// Advanced Chat API - Typing Indicator
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$action = $_POST['action'] ?? '';

if ($action === 'start_typing') {
    $chat_type = $_POST['chat_type'] ?? 'user';
    $chat_id = intval($_POST['chat_id'] ?? 0);
    
    // Store typing status in memcache or temp file (expires in 3 seconds)
    $typing_file = "/tmp/chat_typing_{$chat_type}_{$chat_id}_{$user_id}.txt";
    file_put_contents($typing_file, time());
    
    echo json_encode(['status' => 'success']);
    
} elseif ($action === 'check_typing') {
    $chat_type = $_POST['chat_type'] ?? 'user';
    $chat_id = intval($_POST['chat_id'] ?? 0);
    
    $typing_users = [];
    $pattern = "/tmp/chat_typing_{$chat_type}_{$chat_id}_*.txt";
    
    foreach (glob($pattern) as $file) {
        $file_time = intval(file_get_contents($file));
        
        // If less than 3 seconds old
        if (time() - $file_time < 3) {
            preg_match('/_(\d+)\.txt$/', $file, $matches);
            $typing_user_id = intval($matches[1]);
            
            if ($typing_user_id != $user_id) {
                $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->bind_param('i', $typing_user_id);
                $stmt->execute();
                $stmt->bind_result($typing_name);
                if ($stmt->fetch()) {
                    $typing_users[] = $typing_name;
                }
                $stmt->close();
            }
        } else {
            unlink($file);
        }
    }
    
    echo json_encode(['status' => 'success', 'typing_users' => $typing_users]);
}

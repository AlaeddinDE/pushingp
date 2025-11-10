<?php
// Advanced Chat API - Voice Messages
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'error' => 'Invalid request']);
    exit;
}

// Check if audio data is sent
if (!isset($_FILES['audio'])) {
    echo json_encode(['status' => 'error', 'error' => 'No audio file']);
    exit;
}

$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : null;
$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;

// Create voice messages directory
$upload_dir = __DIR__ . '/../../uploads/voice_messages/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = 'webm'; // or mp3, ogg
$filename = 'voice_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($_FILES['audio']['tmp_name'], $filepath)) {
    $filesize = filesize($filepath);
    $relative_path = '/uploads/voice_messages/' . $filename;
    
    // Insert into chat_messages
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (sender_id, receiver_id, group_id, message, file_path, file_name, file_size, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $message = '🎤 Voice Message';
    $stmt->bind_param('iiisssi', $user_id, $receiver_id, $group_id, $message, $relative_path, $filename, $filesize);
    $stmt->execute();
    $message_id = $stmt->insert_id;
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message_id' => $message_id,
        'file_path' => $relative_path,
        'file_size' => $filesize
    ]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Upload failed']);
}

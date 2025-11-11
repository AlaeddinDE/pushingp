<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$receiver_id = isset($input['receiver_id']) ? intval($input['receiver_id']) : 0;
$group_id = isset($input['group_id']) ? intval($input['group_id']) : null;
$invitation_type = $input['type'] ?? ''; // 'casino', 'game', 'event'
$invitation_data = $input['data'] ?? null; // Additional data (game name, event ID, etc.)

if ($receiver_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Empfänger']);
    exit;
}

// Validate invitation type
$valid_types = ['casino', 'blackjack', 'slots', 'plinko', 'crash', 'event', 'call'];
if (!in_array($invitation_type, $valid_types)) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Einladungs-Typ']);
    exit;
}

// Create invitation message based on type
$emoji_map = [
    'casino' => '🎰',
    'blackjack' => '🃏',
    'slots' => '🎰',
    'plinko' => '🎯',
    'crash' => '🚀',
    'event' => '📅',
    'call' => '📞'
];

$message_map = [
    'casino' => 'Casino-Einladung',
    'blackjack' => 'Blackjack spielen',
    'slots' => 'Slots spielen',
    'plinko' => 'Plinko spielen',
    'crash' => 'Crash spielen',
    'event' => 'Event-Einladung',
    'call' => 'Anruf starten'
];

$emoji = $emoji_map[$invitation_type] ?? '🎮';
$action_text = $message_map[$invitation_type] ?? 'Einladung';

// Create rich invitation message
$invitation_message = "$emoji **{$name}** lädt dich ein: **{$action_text}**";
if ($invitation_data) {
    $invitation_message .= " • " . htmlspecialchars($invitation_data);
}

try {
    // Insert invitation as special message
    if ($group_id) {
        // Group invitation
        $stmt = $conn->prepare("
            INSERT INTO chat_messages (sender_id, group_id, message, message_type, invitation_type, invitation_data, created_at)
            VALUES (?, ?, ?, 'invitation', ?, ?, NOW())
        ");
        $stmt->bind_param('iisss', $user_id, $group_id, $invitation_message, $invitation_type, $invitation_data);
    } else {
        // Private invitation
        $stmt = $conn->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, message, message_type, invitation_type, invitation_data, created_at)
            VALUES (?, ?, ?, 'invitation', ?, ?, NOW())
        ");
        $stmt->bind_param('iisss', $user_id, $receiver_id, $invitation_message, $invitation_type, $invitation_data);
    }
    
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Einladung gesendet!'
    ]);
    
} catch (Exception $e) {
    error_log("Invitation error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Datenbankfehler']);
}

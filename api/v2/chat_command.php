<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$command = $_POST['command'] ?? '';
$args = $_POST['args'] ?? '';
$chat_type = $_POST['chat_type'] ?? 'user';
$chat_id = intval($_POST['chat_id'] ?? 0);

if (empty($command) || !$chat_id) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

$message = '';
$system_message = false;

switch ($command) {
    case '/roll':
        $max = intval($args) > 0 ? intval($args) : 100;
        $roll = rand(1, $max);
        $message = "🎲 **" . $_SESSION['name'] . "** würfelt eine **$roll** (1-$max)";
        break;
        
    case '/flip':
        $result = rand(0, 1) ? 'Kopf' : 'Zahl';
        $icon = $result === 'Kopf' ? '🪙' : '🦅';
        $message = "$icon **" . $_SESSION['name'] . "** wirft eine Münze: **$result**";
        break;
        
    case '/8ball':
        $answers = [
            'Ja, absolut!', 'Es ist sicher.', 'Ohne Zweifel.', 'Ja, sieht gut aus.',
            'Frag später nochmal.', 'Kann ich jetzt nicht sagen.', 'Konzentriere dich und frag nochmal.',
            'Nicht darauf wetten.', 'Meine Antwort ist Nein.', 'Sehr zweifelhaft.', 'Auf keinen Fall.'
        ];
        $answer = $answers[array_rand($answers)];
        $question = htmlspecialchars($args);
        $message = "🎱 **Frage:** $question\n**Antwort:** $answer";
        break;
        
    case '/me':
        $action = htmlspecialchars($args);
        $message = "_* " . $_SESSION['name'] . " $action _";
        break;

    case '/poll':
        $parts = explode('|', $args);
        if (count($parts) < 3) {
            echo json_encode(['status' => 'error', 'error' => 'Format: /poll Frage | Option1 | Option2']);
            exit;
        }
        
        $question = trim(array_shift($parts));
        $options = array_map('trim', $parts);
        
        // Create message first
        $message = "📊 **Umfrage:** $question";
        
        if ($chat_type === 'user') {
            $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('iis', $user_id, $chat_id, $message);
        } else {
            $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, group_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('iis', $user_id, $chat_id, $message);
        }
        
        if ($stmt->execute()) {
            $message_id = $stmt->insert_id;
            $stmt->close();
            
            // Create Poll
            $stmt = $conn->prepare("INSERT INTO chat_polls (message_id, question) VALUES (?, ?)");
            $stmt->bind_param('is', $message_id, $question);
            $stmt->execute();
            $poll_id = $stmt->insert_id;
            $stmt->close();
            
            // Create Options
            $stmt = $conn->prepare("INSERT INTO chat_poll_options (poll_id, option_text) VALUES (?, ?)");
            foreach ($options as $opt) {
                if (!empty($opt)) {
                    $stmt->bind_param('is', $poll_id, $opt);
                    $stmt->execute();
                }
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success']);
            exit; // Exit here as we handled the insert manually
        } else {
            echo json_encode(['status' => 'error', 'error' => 'Database error']);
            exit;
        }
        break;

    case '/spotify':
    case '/music':
        $song = htmlspecialchars($args);
        if (empty($song)) {
            echo json_encode(['status' => 'error', 'error' => 'Bitte Song angeben']);
            exit;
        }
        $message = "🎵 **" . $_SESSION['name'] . "** hört gerade:\n**$song**";
        // Optional: Add Spotify Search Link
        $search_url = "https://open.spotify.com/search/" . urlencode($args);
        $message .= "\n<a href='$search_url' target='_blank' style='color:#1db954;text-decoration:none;font-size:0.8em;'>▶️ Auf Spotify hören</a>";
        break;
        
    default:
        echo json_encode(['status' => 'error', 'error' => 'Unknown command']);
        exit;
}

// Send message to chat
if ($chat_type === 'user') {
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('iis', $user_id, $chat_id, $message);
} else {
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, group_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('iis', $user_id, $chat_id, $message);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Database error']);
}
$stmt->close();

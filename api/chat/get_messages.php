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
            // Load reactions for this message
            $msg_id = $row['id'];
            $reactions_result = $conn->query("
                SELECT emoji, COUNT(*) as count, GROUP_CONCAT(u.name SEPARATOR ', ') as users
                FROM chat_reactions cr
                JOIN users u ON u.id = cr.user_id
                WHERE cr.message_id = $msg_id
                GROUP BY emoji
            ");
            
            $row['reactions'] = [];
            if ($reactions_result) {
                while ($reaction = $reactions_result->fetch_assoc()) {
                    $row['reactions'][] = $reaction;
                }
            }
            
            $messages[] = $row;
        }
        
        // Load Poll Data for messages
        foreach ($messages as &$msg) {
            if (strpos($msg['message'], '📊 **Umfrage:**') !== false) {
                $msg_id = $msg['id'];
                $poll_stmt = $conn->prepare("
                    SELECT id, question, is_active 
                    FROM chat_polls 
                    WHERE message_id = ?
                ");
                $poll_stmt->bind_param('i', $msg_id);
                $poll_stmt->execute();
                $poll_res = $poll_stmt->get_result();
                
                if ($poll = $poll_res->fetch_assoc()) {
                    $poll_id = $poll['id'];
                    
                    // Get Options and Votes
                    $opt_stmt = $conn->prepare("
                        SELECT o.id, o.option_text, 
                               (SELECT COUNT(*) FROM chat_poll_votes v WHERE v.option_id = o.id) as votes,
                               (SELECT COUNT(*) FROM chat_poll_votes v WHERE v.option_id = o.id AND v.user_id = ?) as user_voted
                        FROM chat_poll_options o
                        WHERE o.poll_id = ?
                    ");
                    $opt_stmt->bind_param('ii', $user_id, $poll_id);
                    $opt_stmt->execute();
                    $opt_res = $opt_stmt->get_result();
                    
                    $options = [];
                    $total_votes = 0;
                    while ($opt = $opt_res->fetch_assoc()) {
                        $options[] = $opt;
                        $total_votes += $opt['votes'];
                    }
                    
                    $poll['options'] = $options;
                    $poll['total_votes'] = $total_votes;
                    $msg['poll'] = $poll;
                }
                $poll_stmt->close();
            }
        }
        unset($msg); // Break reference
        
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
                // Load reactions for this message
                $msg_id = $row['id'];
                $reactions_result = $conn->query("
                    SELECT emoji, COUNT(*) as count, GROUP_CONCAT(u.name SEPARATOR ', ') as users
                    FROM chat_reactions cr
                    JOIN users u ON u.id = cr.user_id
                    WHERE cr.message_id = $msg_id
                    GROUP BY emoji
                ");
                
                $row['reactions'] = [];
                if ($reactions_result) {
                    while ($reaction = $reactions_result->fetch_assoc()) {
                        $row['reactions'][] = $reaction;
                    }
                }
                
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
                    // Load reactions for this message
                    $msg_id = $row['id'];
                    $reactions_result = $conn->query("
                        SELECT emoji, COUNT(*) as count, GROUP_CONCAT(u.name SEPARATOR ', ') as users
                        FROM chat_reactions cr
                        JOIN users u ON u.id = cr.user_id
                        WHERE cr.message_id = $msg_id
                        GROUP BY emoji
                    ");
                    
                    $row['reactions'] = [];
                    if ($reactions_result) {
                        while ($reaction = $reactions_result->fetch_assoc()) {
                            $row['reactions'][] = $reaction;
                        }
                    }
                    
                    $messages[] = $row;
                }
            }
        }
    }
    
    echo json_encode(['status' => 'success', 'messages' => $messages]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

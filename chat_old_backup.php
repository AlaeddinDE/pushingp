<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin = is_admin();

// Alle aktiven User holen
$users_result = $conn->query("SELECT id, name, username FROM users WHERE status = 'active' AND id != $user_id ORDER BY name ASC");
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Letzte Chats holen (Privatnachrichten)
$recent_chats = [];
try {
    $recent_query = "
        SELECT 
            u2.id as contact_id,
            u2.name,
            u2.username,
            MAX(cm.created_at) as last_message_time,
            (SELECT message FROM chat_messages cm2 
             WHERE ((cm2.sender_id = $user_id AND cm2.receiver_id = u2.id) 
                OR (cm2.receiver_id = $user_id AND cm2.sender_id = u2.id))
             AND cm2.group_id IS NULL
             ORDER BY cm2.created_at DESC LIMIT 1) as last_message,
            'user' as type
        FROM chat_messages cm
        JOIN users u2 ON (
            (cm.sender_id = $user_id AND cm.receiver_id = u2.id) OR
            (cm.receiver_id = $user_id AND cm.sender_id = u2.id)
        )
        LEFT JOIN chat_hidden ch ON (
            ch.user_id = $user_id 
            AND ch.chat_type = 'user' 
            AND ch.chat_id = u2.id
        )
        WHERE cm.group_id IS NULL
        AND u2.status = 'active'
        AND u2.id != $user_id
        AND ch.id IS NULL
        GROUP BY u2.id, u2.name, u2.username
        ORDER BY last_message_time DESC
        LIMIT 10
    ";
    $recent_result = $conn->query($recent_query);
    if ($recent_result) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_chats[] = $row;
        }
    }

    // Letzte Gruppen-Chats
    $recent_groups_query = "
        SELECT 
            g.id as contact_id,
            g.name,
            g.is_protected,
            MAX(cm.created_at) as last_message_time,
            (SELECT message FROM chat_messages cm2 
             WHERE cm2.group_id = g.id
             ORDER BY cm2.created_at DESC LIMIT 1) as last_message,
            'group' as type,
            (SELECT COUNT(DISTINCT user_id) FROM chat_group_members WHERE group_id = g.id) as member_count
        FROM chat_messages cm
        JOIN chat_groups g ON g.id = cm.group_id
        JOIN chat_group_members cgm ON cgm.group_id = g.id AND cgm.user_id = $user_id
        LEFT JOIN chat_hidden ch ON (
            ch.user_id = $user_id 
            AND ch.chat_type = 'group' 
            AND ch.chat_id = g.id
        )
        WHERE cm.group_id IS NOT NULL
        AND ch.id IS NULL
        GROUP BY g.id, g.name, g.is_protected
        ORDER BY last_message_time DESC
        LIMIT 10
    ";
    $recent_groups_result = $conn->query($recent_groups_query);
    if ($recent_groups_result) {
        while ($row = $recent_groups_result->fetch_assoc()) {
            $recent_chats[] = $row;
        }
    }

    // Nach Zeit sortieren
    usort($recent_chats, function($a, $b) {
        return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
    });
    $recent_chats = array_slice($recent_chats, 0, 20);
} catch (Exception $e) {
    // Fehler abfangen, damit Seite weiter lädt
    error_log("Chat recent error: " . $e->getMessage());
    $recent_chats = [];
}

// Gruppen holen (Admins sehen ALLE Gruppen)
if ($is_admin) {
    // Admin: Alle Gruppen anzeigen
    $groups_result = $conn->query("
        SELECT g.id, g.name, g.created_by, g.created_at, g.is_protected,
               COUNT(DISTINCT cgm.user_id) as member_count
        FROM chat_groups g
        LEFT JOIN chat_group_members cgm ON cgm.group_id = g.id
        GROUP BY g.id, g.name, g.created_by, g.created_at, g.is_protected
        ORDER BY g.name ASC
    ");
} else {
    // Normal user: Nur eigene Gruppen
    $groups_result = $conn->query("
        SELECT g.id, g.name, g.created_by, g.created_at, g.is_protected,
               COUNT(DISTINCT cgm.user_id) as member_count
        FROM chat_groups g
        LEFT JOIN chat_group_members cgm ON cgm.group_id = g.id
        WHERE cgm.user_id = $user_id OR g.created_by = $user_id
        GROUP BY g.id, g.name, g.created_by, g.created_at, g.is_protected
        ORDER BY g.name ASC
    ");
}
$groups = [];
while ($row = $groups_result->fetch_assoc()) {
    $groups[] = $row;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .chat-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 0;
            height: calc(100vh - 140px);
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .chat-sidebar {
            background: var(--bg-tertiary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .chat-sidebar-search {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        
        .chat-tabs {
            display: flex;
            padding: 12px 20px;
            gap: 8px;
            border-bottom: 1px solid var(--border);
        }
        
        .chat-tab {
            padding: 8px 16px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .chat-tab.active {
            background: var(--accent);
            color: white;
        }
        
        .chat-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }
        
        .chat-item {
            padding: 12px 16px;
            background: var(--bg-secondary);
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chat-item:hover {
            background: var(--accent);
            transform: translateX(4px);
        }
        
        .chat-item.active {
            background: var(--accent);
        }
        
        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
        }
        
        .chat-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .chat-item-name {
            font-weight: 600;
            font-size: 0.875rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .chat-item-preview {
            font-size: 0.75rem;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .chat-main {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .chat-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-tertiary);
        }
        
        .chat-header-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .chat-header-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .chat-message {
            display: flex;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        .chat-message.own {
            flex-direction: row-reverse;
        }
        
        .chat-message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        
        .chat-message.own .chat-message-avatar {
            background: linear-gradient(135deg, var(--accent), #a855f7);
        }
        
        .chat-message-content {
            max-width: 60%;
        }
        
        .chat-message-bubble {
            background: var(--bg-tertiary);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 4px;
        }
        
        .chat-message.own .chat-message-bubble {
            background: var(--accent);
            color: white;
        }
        
        .chat-message-text {
            word-wrap: break-word;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        .chat-message-file {
            background: rgba(255,255,255,0.1);
            padding: 10px 14px;
            border-radius: 8px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .chat-message-file:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .chat-message-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            padding: 0 4px;
        }
        
        .chat-message.own .chat-message-meta {
            text-align: right;
            color: rgba(255,255,255,0.7);
        }
        
        .chat-input-area {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-tertiary);
        }
        
        .chat-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.875rem;
            resize: none;
            max-height: 120px;
            font-family: 'Inter', sans-serif;
        }
        
        .chat-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .chat-send-btn {
            padding: 12px 24px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
        }
        
        .chat-send-btn:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }
        
        .chat-file-btn {
            padding: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-file-btn:hover {
            background: var(--accent);
            color: white;
        }
        
        .chat-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .chat-empty-content {
            max-width: 300px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Mobile Optimierungen */
        @media (max-width: 768px) {
            .chat-container {
                grid-template-columns: 1fr;
                height: calc(100vh - 100px);
            }
            
            .chat-sidebar {
                display: flex;
                position: fixed;
                top: 0;
                left: -100%;
                right: 100%;
                bottom: 0;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .chat-sidebar.mobile-open {
                left: 0;
                right: 0;
            }
            
            .mobile-chat-toggle {
                display: flex !important;
            }
            
            .chat-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .chat-message-content {
                max-width: 85%;
            }
            
            .chat-input-wrapper {
                flex-wrap: wrap;
            }
            
            .container {
                padding: 12px !important;
            }
        }
        
        @media (max-width: 430px) {
            .chat-messages {
                padding: 16px;
            }
            
            .chat-input-area {
                padding: 12px;
            }
            
            .chat-message-bubble {
                padding: 10px 12px;
            }
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .checkbox-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
        }
        
        .checkbox-item {
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .checkbox-item:hover {
            background: var(--bg-secondary);
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .btn-primary {
            flex: 1;
            padding: 12px 24px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
        }
        
        .btn-secondary {
            flex: 1;
            padding: 12px 24px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            background: var(--bg-secondary);
        }
        
        /* Mobile Toggle Button */
        .mobile-chat-toggle {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-chat-toggle:active {
            transform: scale(0.95);
        }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .mobile-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">PUSHING P</a>
            <nav class="nav">
                <a href="dashboard.php" class="nav-item">Dashboard</a>
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <a href="chat.php" class="nav-item active">Chat</a>
                <?php if ($is_admin): ?>
                    <a href="admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar" id="chatSidebar">
                <div class="chat-sidebar-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="margin: 0;">Chats</h3>
                        <button onclick="closeMobileSidebar()" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer; display: none;" id="closeSidebarBtn">✕</button>
                    </div>
                    <input type="text" class="chat-sidebar-search" placeholder="🔍 Suchen..." id="chatSearch">
                </div>
                
                <div class="chat-tabs">
                    <button class="chat-tab active" data-tab="recent">Kürzlich</button>
                    <button class="chat-tab" data-tab="users">Direkt</button>
                    <button class="chat-tab" data-tab="groups">Gruppen</button>
                </div>
                
                <div class="chat-list" id="recentList">
                    <?php if (empty($recent_chats)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                            <div style="font-size: 2rem; margin-bottom: 12px;">💬</div>
                            <div style="font-size: 0.875rem;">Noch keine Chats</div>
                            <div style="font-size: 0.75rem; margin-top: 8px;">Starte eine Unterhaltung!</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_chats as $chat): 
                            if ($chat['type'] === 'user') {
                                $initials = '';
                                $name_parts = explode(' ', $chat['name']);
                                if (count($name_parts) >= 2) {
                                    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($chat['name'], 0, 2));
                                }
                                $chat_icon = $initials;
                                $chat_subtitle = substr($chat['last_message'] ?? '', 0, 30) . (strlen($chat['last_message'] ?? '') > 30 ? '...' : '');
                            } else {
                                $chat_icon = $chat['is_protected'] ? '🔒' : '👥';
                                $chat_subtitle = substr($chat['last_message'] ?? '', 0, 30) . (strlen($chat['last_message'] ?? '') > 30 ? '...' : '');
                            }
                            
                            $time_ago = '';
                            try {
                                $msg_time = new DateTime($chat['last_message_time']);
                                $now = new DateTime();
                                $diff = $now->getTimestamp() - $msg_time->getTimestamp();
                                if ($diff < 60) $time_ago = 'Gerade eben';
                                elseif ($diff < 3600) $time_ago = floor($diff / 60) . ' Min';
                                elseif ($diff < 86400) $time_ago = floor($diff / 3600) . ' Std';
                                else $time_ago = floor($diff / 86400) . ' Tage';
                            } catch (Exception $e) {
                                $time_ago = '';
                            }
                        ?>
                        <div class="chat-item" data-type="<?= $chat['type'] ?>" data-id="<?= $chat['contact_id'] ?? $chat['id'] ?>" data-name="<?= htmlspecialchars($chat['name']) ?>" data-protected="<?= $chat['is_protected'] ?? 0 ?>">
                            <div class="chat-avatar"><?= $chat_icon ?></div>
                            <div class="chat-item-info">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                    <div class="chat-item-name"><?= htmlspecialchars($chat['name']) ?></div>
                                    <?php if ($time_ago): ?>
                                        <div style="font-size: 0.7rem; color: var(--text-secondary);"><?= $time_ago ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-item-preview"><?= htmlspecialchars($chat_subtitle) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="chat-list" id="usersList" style="display: none;">
                    <?php foreach ($users as $user): 
                        $initials = '';
                        $name_parts = explode(' ', $user['name']);
                        if (count($name_parts) >= 2) {
                            $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                        } else {
                            $initials = strtoupper(substr($user['name'], 0, 2));
                        }
                    ?>
                    <div class="chat-item" data-type="user" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>">
                        <div class="chat-avatar"><?= $initials ?></div>
                        <div class="chat-item-info">
                            <div class="chat-item-name"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="chat-item-preview">@<?= htmlspecialchars($user['username']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-list" id="groupsList" style="display: none;">
                    <div style="padding: 12px; text-align: center;">
                        <button class="button" onclick="createGroup()" style="width: 100%;">
                            ➕ Neue Gruppe erstellen
                        </button>
                    </div>
                    <?php foreach ($groups as $group): ?>
                    <div class="chat-item" data-type="group" data-id="<?= $group['id'] ?>" data-name="<?= htmlspecialchars($group['name']) ?>" data-protected="<?= $group['is_protected'] ?>">
                        <div class="chat-avatar"><?= $group['is_protected'] ? '🔒' : '👥' ?></div>
                        <div class="chat-item-info">
                            <div class="chat-item-name"><?= htmlspecialchars($group['name']) ?></div>
                            <div class="chat-item-preview"><?= $group['member_count'] ?> Mitglieder<?= $group['is_protected'] ? ' · Geschützt' : '' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Main Chat Area -->
            <div class="chat-main">
                <div class="chat-empty">
                    <div class="chat-empty-content">
                        <div style="font-size: 3rem; margin-bottom: 16px;">💬</div>
                        <div style="font-weight: 600; margin-bottom: 8px;">Wähle einen Chat</div>
                        <div style="font-size: 0.875rem;">Starte eine Unterhaltung mit deinen Crew-Mitgliedern</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Toggle Button -->
    <button class="mobile-chat-toggle" id="mobileToggle" onclick="openMobileSidebar()">
        💬
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileSidebar()"></div>
    
    <!-- Create Group Modal -->
    <div class="modal-overlay" id="createGroupModal">
        <div class="modal-content">
            <div class="modal-title">Neue Gruppe erstellen</div>
            
            <div class="form-group">
                <label class="form-label">Gruppenname</label>
                <input type="text" class="form-input" id="groupNameInput" placeholder="z.B. Crew Chat">
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" id="groupPasswordProtected" style="width: auto; margin-right: 8px;">
                    Gruppe mit Passwort schützen
                </label>
                <input type="password" class="form-input" id="groupPasswordInput" placeholder="Passwort" style="display: none; margin-top: 8px;">
            </div>
            
            <div class="form-group">
                <label class="form-label">Mitglieder auswählen</label>
                <div class="checkbox-list" id="memberCheckboxList">
                    <?php foreach ($users as $user): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="group_members" value="<?= $user['id'] ?>">
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($user['name']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">@<?= htmlspecialchars($user['username']) ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button class="btn-secondary" onclick="closeCreateGroupModal()">Abbrechen</button>
                <button class="btn-primary" onclick="submitCreateGroup()">Gruppe erstellen</button>
            </div>
        </div>
    </div>

    <script>
    let currentChatType = null;
    let currentChatId = null;
    let currentChatName = null;
    let pollInterval = null;
    const userId = <?= $user_id ?>;
    const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
    
    // Mobile Sidebar Functions
    function openMobileSidebar() {
        document.getElementById('chatSidebar').classList.add('mobile-open');
        document.getElementById('mobileOverlay').classList.add('active');
        document.getElementById('closeSidebarBtn').style.display = 'block';
        
        // Show toggle button again
        if (window.innerWidth <= 768) {
            document.getElementById('mobileToggle').style.display = 'flex';
        }
    }
    
    function closeMobileSidebar() {
        document.getElementById('chatSidebar').classList.remove('mobile-open');
        document.getElementById('mobileOverlay').classList.remove('active');
        document.getElementById('closeSidebarBtn').style.display = 'none';
    }
    
    // Tab Switching
    document.querySelectorAll('.chat-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const tabName = this.getAttribute('data-tab');
            document.getElementById('recentList').style.display = 'none';
            document.getElementById('usersList').style.display = 'none';
            document.getElementById('groupsList').style.display = 'none';
            
            if (tabName === 'recent') {
                document.getElementById('recentList').style.display = 'block';
            } else if (tabName === 'users') {
                document.getElementById('usersList').style.display = 'block';
            } else {
                document.getElementById('groupsList').style.display = 'block';
            }
        });
    });
    
    // Chat Item Click
    function attachChatItemListeners() {
        document.querySelectorAll('.chat-item').forEach(item => {
            // Remove old listener to prevent duplicates
            item.replaceWith(item.cloneNode(true));
        });
        
        // Re-attach to all items
        document.querySelectorAll('.chat-item').forEach(item => {
            item.addEventListener('click', handleChatItemClick);
        });
    }
    
    async function handleChatItemClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        document.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        
        currentChatType = this.getAttribute('data-type');
        currentChatId = this.getAttribute('data-id');
        currentChatName = this.getAttribute('data-name');
        const isProtected = this.getAttribute('data-protected') === '1';
        
        console.log('=== CHAT DEBUG ===');
        console.log('Type:', currentChatType);
        console.log('ID:', currentChatId);
        console.log('Name:', currentChatName);
        console.log('Protected:', isProtected);
        console.log('Element:', this);
        console.log('================');
        
        if (!currentChatId || !currentChatType || !currentChatName) {
            console.error('Missing chat data!', {
                type: currentChatType,
                id: currentChatId,
                name: currentChatName
            });
            alert('Fehler: Chat-Daten fehlen. Bitte Seite neu laden.');
            return;
        }
        
        // Check if group is password protected
        if (currentChatType === 'group' && isProtected) {
            const password = prompt('🔒 Diese Gruppe ist passwortgeschützt. Bitte Passwort eingeben:');
            if (!password) return;
            
            try {
                const response = await fetch('/api/chat/verify_group_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        group_id: parseInt(currentChatId),
                        password: password
                    })
                });
                
                const data = await response.json();
                if (data.status !== 'success') {
                    alert('❌ ' + data.error);
                    return;
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
                return;
            }
        }
        
        // Open chat and ensure it stays open
        openChat(currentChatType, currentChatId, currentChatName);
        
        // Close mobile sidebar after selection
        setTimeout(() => {
            closeMobileSidebar();
        }, 100);
    }
    
    // Initial attachment
    attachChatItemListeners();
    
    // Check for URL hash to auto-open chat (e.g., #user-4)
    if (window.location.hash) {
        const hash = window.location.hash.substring(1); // Remove #
        const parts = hash.split('-');
        if (parts.length === 2) {
            const type = parts[0]; // 'user' or 'group'
            const id = parseInt(parts[1]);
            
            // Find the chat name from the users list
            const chatItem = document.querySelector(`[data-chat-type="${type}"][data-chat-id="${id}"]`);
            if (chatItem) {
                const name = chatItem.getAttribute('data-chat-name');
                openChat(type, id, name);
            }
        }
        // Clear hash
        history.replaceState(null, null, window.location.pathname);
    }
    
    function openChat(type, id, name) {
        const chatMain = document.querySelector('.chat-main');
        
        chatMain.innerHTML = `
            <div class="chat-header">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <button onclick="openMobileSidebar()" style="display: none; background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer; padding: 8px;" id="backToChatsBtn">←</button>
                        <div>
                            <div class="chat-header-title">${type === 'group' ? '👥 ' : ''}${name}</div>
                            <div class="chat-header-subtitle">${type === 'group' ? 'Gruppenchat' : 'Privatchat'}</div>
                        </div>
                    </div>
                    <button onclick="hideChat('${type}', ${id})" style="background: none; border: none; color: var(--text-secondary); font-size: 1.25rem; cursor: pointer; padding: 8px; transition: all 0.2s;" title="Chat ausblenden" onmouseover="this.style.color='var(--error)'" onmouseout="this.style.color='var(--text-secondary)'">
                        🗑️
                    </button>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <!-- Messages loaded here -->
            </div>
            
            <div class="chat-input-area" style="display: block !important; visibility: visible !important; position: relative !important; z-index: 100 !important;">
                <div class="chat-input-wrapper" style="display: flex !important;">
                    <input type="file" id="fileInput" style="display: none;">
                    <button class="chat-file-btn" id="fileBtn" style="display: flex !important; visibility: visible !important;">
                        📎
                    </button>
                    <textarea class="chat-input" id="messageInput" placeholder="Nachricht schreiben..." rows="1" style="display: block !important; visibility: visible !important; flex: 1 !important;"></textarea>
                    <button class="chat-send-btn" id="sendBtn" style="display: block !important; visibility: visible !important;">Senden</button>
                </div>
            </div>
        `;
        
        // Show back button on mobile
        if (window.innerWidth <= 768) {
            const backBtn = document.getElementById('backToChatsBtn');
            const toggleBtn = document.getElementById('mobileToggle');
            if (backBtn) backBtn.style.display = 'block';
            if (toggleBtn) toggleBtn.style.display = 'none';
        }
        
        // File button click handler
        const fileBtn = document.getElementById('fileBtn');
        const fileInput = document.getElementById('fileInput');
        if (fileBtn && fileInput) {
            fileBtn.addEventListener('click', function() {
                fileInput.click();
            });
            fileInput.addEventListener('change', function() {
                handleFileSelect(this);
            });
        }
        
        // Send button click handler
        const sendBtn = document.getElementById('sendBtn');
        if (sendBtn) {
            sendBtn.addEventListener('click', sendMessage);
        }
        
        // Auto-resize textarea
        const textarea = document.getElementById('messageInput');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Enter to send
            textarea.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
        
        loadMessages();
        
        // Poll for new messages
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(loadMessages, 2000);
    }
    
    async function loadMessages() {
        if (!currentChatId || !currentChatType) return;
        
        try {
            const response = await fetch(`/api/chat/get_messages.php?type=${currentChatType}&id=${currentChatId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                const messagesContainer = document.getElementById('chatMessages');
                if (!messagesContainer) return;
                
                const wasAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 100;
                
                // Check if messages changed
                const newHTML = data.messages.map(msg => {
                    const isOwn = msg.sender_id == userId;
                    const initials = msg.sender_name ? msg.sender_name.split(' ').map(n => n[0]).join('').toUpperCase().substr(0, 2) : '?';
                    
                    return `
                        <div class="chat-message ${isOwn ? 'own' : ''}" data-msg-id="${msg.id}">
                            <div class="chat-message-avatar">${initials}</div>
                            <div class="chat-message-content">
                                <div class="chat-message-bubble">
                                    ${!isOwn ? `<div style="font-weight: 600; font-size: 0.75rem; margin-bottom: 4px; opacity: 0.8;">${msg.sender_name}</div>` : ''}
                                    <div class="chat-message-text">${escapeHtml(msg.message)}</div>
                                    ${msg.file_path ? `
                                        <a href="${msg.file_path}" target="_blank" class="chat-message-file" download="${msg.file_name}">
                                            <span>📎</span>
                                            <span>${msg.file_name}</span>
                                            <span style="opacity: 0.7; font-size: 0.7rem;">(${formatFileSize(msg.file_size)})</span>
                                        </a>
                                    ` : ''}
                                </div>
                                <div class="chat-message-meta">${formatTime(msg.created_at)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                // Only update if content changed
                if (messagesContainer.innerHTML !== newHTML) {
                    messagesContainer.innerHTML = newHTML;
                    
                    if (wasAtBottom) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }
    
    async function sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) return;
        
        try {
            const response = await fetch('/api/chat/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: currentChatType,
                    id: currentChatId,
                    message: message
                })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                input.value = '';
                input.style.height = 'auto';
                loadMessages();
            } else {
                alert('Fehler: ' + data.error);
            }
        } catch (error) {
            alert('Fehler beim Senden: ' + error.message);
        }
    }
    
    async function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', currentChatType);
        formData.append('id', currentChatId);
        
        try {
            const response = await fetch('/api/chat/upload_file.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                loadMessages();
            } else {
                alert('Fehler beim Upload: ' + data.error);
            }
        } catch (error) {
            alert('Fehler beim Upload: ' + error.message);
        }
        
        input.value = '';
    }
    
    async function createGroup() {
        document.getElementById('createGroupModal').classList.add('active');
    }
    
    function closeCreateGroupModal() {
        document.getElementById('createGroupModal').classList.remove('active');
        document.getElementById('groupNameInput').value = '';
        document.getElementById('groupPasswordInput').value = '';
        document.getElementById('groupPasswordProtected').checked = false;
        document.getElementById('groupPasswordInput').style.display = 'none';
        document.querySelectorAll('input[name="group_members"]').forEach(cb => cb.checked = false);
    }
    
    // Toggle password field
    document.addEventListener('DOMContentLoaded', function() {
        const passwordCheckbox = document.getElementById('groupPasswordProtected');
        const passwordInput = document.getElementById('groupPasswordInput');
        
        if (passwordCheckbox) {
            passwordCheckbox.addEventListener('change', function() {
                passwordInput.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) passwordInput.value = '';
            });
        }
    });
    
    async function submitCreateGroup() {
        const groupName = document.getElementById('groupNameInput').value.trim();
        if (!groupName) {
            alert('Bitte gib einen Gruppennamen ein!');
            return;
        }
        
        const selectedMembers = Array.from(document.querySelectorAll('input[name="group_members"]:checked'))
            .map(cb => parseInt(cb.value));
        
        if (selectedMembers.length === 0) {
            alert('Bitte wähle mindestens ein Mitglied aus!');
            return;
        }
        
        const isProtected = document.getElementById('groupPasswordProtected').checked;
        const password = document.getElementById('groupPasswordInput').value;
        
        if (isProtected && !password) {
            alert('Bitte gib ein Passwort ein!');
            return;
        }
        
        try {
            const response = await fetch('/api/chat/create_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    name: groupName,
                    members: selectedMembers,
                    password: isProtected ? password : null
                })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                alert('✅ Gruppe erstellt!');
                location.reload();
            } else {
                alert('Fehler: ' + data.error);
            }
        } catch (error) {
            alert('Fehler: ' + error.message);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }
    
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Gerade eben';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' Min';
        if (diff < 86400000) return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) + ' ' + date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
    
    // Search functionality
    document.getElementById('chatSearch').addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            item.style.display = name.includes(search) ? 'flex' : 'none';
        });
    });
    
    // Hide chat function
    async function hideChat(type, id) {
        if (!confirm('Möchtest du diesen Chat wirklich ausblenden?\n\nDer Chat wird aus "Kürzlich" entfernt. Du kannst ihn jederzeit über "Direkt" oder "Gruppen" wieder öffnen.')) {
            return;
        }
        
        try {
            const response = await fetch('/api/chat/hide_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    type: type,
                    id: parseInt(id)
                })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                // Close current chat
                const chatMain = document.querySelector('.chat-main');
                chatMain.innerHTML = `
                    <div class="chat-empty">
                        <div class="chat-empty-content">
                            <div style="font-size: 3rem; margin-bottom: 16px;">✅</div>
                            <div style="font-weight: 600; margin-bottom: 8px;">Chat ausgeblendet</div>
                            <div style="font-size: 0.875rem;">Der Chat wurde aus "Kürzlich" entfernt</div>
                        </div>
                    </div>
                `;
                
                // Reload page after 1 second
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                alert('❌ Fehler: ' + data.error);
            }
        } catch (error) {
            alert('Fehler: ' + error.message);
        }
    }
    
    // Make hideChat global
    window.hideChat = hideChat;
    </script>
</body>
</html>

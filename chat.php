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
        body {
            overflow: hidden;
            height: 100vh;
        }
        
        .container {
            max-height: 100vh;
            overflow: hidden;
            padding: 20px 0;
        }
        
        .chat-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 0;
            height: calc(100vh - 140px);
            max-width: 1800px;
            margin: 0 auto;
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
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
            position: relative;
            overflow: hidden;
        }
        
        .chat-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-tertiary);
            flex-shrink: 0;
        }
        
        .chat-header-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .chat-header-subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: scroll;
            overflow-x: hidden;
            padding: 32px;
            padding-bottom: 180px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-height: 0;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Dark Scrollbar */
        .chat-messages::-webkit-scrollbar {
            width: 12px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--bg-tertiary);
            border-radius: 6px;
            border: 2px solid var(--bg-secondary);
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
        
        /* Firefox Scrollbar */
        .chat-messages {
            scrollbar-width: thin;
            scrollbar-color: var(--bg-tertiary) var(--bg-secondary);
        }
        
        .chat-message {
            display: flex;
            gap: 14px;
            animation: slideIn 0.3s ease;
        }
        
        .chat-message.own {
            flex-direction: row-reverse;
        }
        
        .chat-message-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        
        .chat-message.own .chat-message-avatar {
            background: linear-gradient(135deg, var(--accent), #a855f7);
        }
        
        .chat-message-content {
            max-width: 65%;
        }
        
        .chat-message-bubble {
            background: var(--bg-tertiary);
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 4px;
        }
        
        .chat-message.own .chat-message-bubble {
            background: var(--accent);
            color: white;
        }
        
        .chat-message-text {
            word-wrap: break-word;
            font-size: 0.9375rem;
            line-height: 1.6;
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
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-tertiary);
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
        }
        
        /* NEUER FIXED INPUT BEREICH */
        .chat-input-area-fixed {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-tertiary);
            border-top: 2px solid var(--border);
            padding: 16px 20px;
            z-index: 9999;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
        }
        
        .chat-input-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
        }
        
        .chat-tool-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-tool-btn:hover {
            background: var(--bg-secondary);
            transform: scale(1.1);
        }
        
        .chat-tool-btn.voice-btn {
            background: var(--accent);
            color: white;
            padding: 8px 12px;
        }
        
        .chat-tool-btn.voice-btn:hover {
            background: #7c3aed;
        }
        
        /* Voice Message Styles */
        .voice-message-container {
            position: relative;
        }
        
        .voice-message-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .wave-bar {
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes waveAnimation {
            0%, 100% {
                transform: scaleY(0.5);
            }
            50% {
                transform: scaleY(1.5);
            }
        }
        
        .voice-message-container.playing .wave-bar {
            animation: waveAnimation 1s ease-in-out infinite;
        }
        
        .voice-message-container.playing .wave-bar:nth-child(2n) {
            animation-delay: 0.1s;
        }
        
        .voice-message-container.playing .wave-bar:nth-child(3n) {
            animation-delay: 0.2s;
        }
        
        .chat-tool-btn.money-btn {
            background: #10b981;
            color: white;
            padding: 8px 12px;
        }
        
        .chat-tool-btn.money-btn:hover {
            background: #059669;
        }
        
        .chat-textarea {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.9rem;
            resize: none;
            max-height: 120px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }
        
        .chat-textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .chat-send-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .chat-send-btn:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        
        .chat-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            max-width: 100%;
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
            
            .chat-input-area {
                left: 0;
            }
            
            .chat-input-area-fixed {
                left: 0;
                padding: 12px;
            }
            
            .chat-tool-btn {
                font-size: 1.3rem;
                padding: 6px;
            }
            
            .chat-textarea {
                min-width: 120px;
                font-size: 0.85rem;
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
                left: 0;
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
        `;
        
        // Chat Input Area wird SEPARAT außerhalb von chat-main hinzugefügt (damit es fixed sein kann)
        const inputAreaHTML = `
            <div class="chat-input-area-fixed" id="chatInputArea">
                <input type="file" id="fileInput" style="display: none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt">
                
                <div class="chat-input-toolbar">
                    <button class="chat-tool-btn" id="fileBtn" title="Datei anhängen">
                        <span>📎</span>
                    </button>
                    <button class="chat-tool-btn voice-btn" id="voiceBtn" title="Sprachnachricht (Halten zum Aufnehmen)">
                        <span>🎤</span>
                    </button>
                    <button class="chat-tool-btn money-btn" id="moneyBtn" title="💰 Geld senden">
                        <span>💰</span>
                    </button>
                    
                    <textarea class="chat-textarea" id="messageInput" placeholder="Nachricht schreiben..." rows="1"></textarea>
                    
                    <button class="chat-tool-btn" id="emojiBtn" title="Emoji einfügen">
                        <span>😊</span>
                    </button>
                    <button class="chat-send-btn" id="sendBtn">
                        <span>Senden</span>
                    </button>
                </div>
            </div>
        `;
        
        // Remove old input if exists
        const oldInput = document.querySelector('.chat-input-area-fixed');
        if (oldInput) oldInput.remove();
        
        // Add new input area
        chatMain.insertAdjacentHTML('beforeend', inputAreaHTML);
        
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
        
        // VOICE MESSAGE BUTTON - Hold to record, release to send
        const voiceBtn = document.getElementById('voiceBtn');
        if (voiceBtn) {
            let isRecording = false;
            
            voiceBtn.addEventListener('mousedown', function() {
                isRecording = true;
                if (typeof startVoiceRecording === 'function') {
                    startVoiceRecording();
                    this.style.background = '#ef4444';
                }
            });
            
            voiceBtn.addEventListener('mouseup', function() {
                if (isRecording) {
                    if (typeof stopVoiceRecording === 'function') {
                        stopVoiceRecording();
                    }
                    this.style.background = 'var(--accent)';
                    isRecording = false;
                }
            });
            
            voiceBtn.addEventListener('mouseleave', function() {
                if (isRecording) {
                    if (typeof stopVoiceRecording === 'function') {
                        stopVoiceRecording();
                    }
                    this.style.background = 'var(--accent)';
                    isRecording = false;
                }
            });
        }
        
        // EMOJI PICKER BUTTON
        const emojiBtn = document.getElementById('emojiBtn');
        if (emojiBtn) {
            const emojis = ['😀','😂','😍','🥰','😎','🤔','👍','❤️','🔥','🎉','💯','✨','👏','🙌','💪','🎊'];
            emojiBtn.addEventListener('click', function() {
                const picker = document.createElement('div');
                picker.style.cssText = 'position: absolute; bottom: 60px; right: 20px; background: var(--bg-tertiary); padding: 12px; border-radius: 12px; display: grid; grid-template-columns: repeat(8, 1fr); gap: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.3); z-index: 1000;';
                picker.innerHTML = emojis.map(e => 
                    `<button onclick="document.getElementById('messageInput').value += '${e}'; this.parentElement.remove();" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 4px;">${e}</button>`
                ).join('');
                
                // Remove existing picker
                const existing = document.querySelector('.emoji-picker-popup');
                if (existing) existing.remove();
                
                picker.className = 'emoji-picker-popup';
                document.body.appendChild(picker);
                
                // Close on outside click
                setTimeout(() => {
                    document.addEventListener('click', function closePickerHandler(e) {
                        if (!picker.contains(e.target) && e.target !== emojiBtn) {
                            picker.remove();
                            document.removeEventListener('click', closePickerHandler);
                        }
                    });
                }, 100);
            });
        }
        
        // MONEY TRANSFER BUTTON with custom modal
        const moneyBtn = document.getElementById('moneyBtn');
        if (moneyBtn) {
            moneyBtn.addEventListener('click', function() {
                showMoneyInputModal();
            });
        }
        
        // Custom Money Input Modal
        function showMoneyInputModal() {
            // Create modal overlay
            const modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 99999; display: flex; align-items: center; justify-content: center; animation: fadeIn 0.2s;';
            
            const modalContent = document.createElement('div');
            modalContent.style.cssText = 'background: var(--bg-primary); padding: 40px; border-radius: 20px; max-width: 450px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.5); animation: scaleIn 0.3s ease-out;';
            
            modalContent.innerHTML = `
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes scaleIn {
                        from { transform: scale(0.9); opacity: 0; }
                        to { transform: scale(1); opacity: 1; }
                    }
                    .money-input {
                        width: 100%;
                        font-size: 2.5rem;
                        font-weight: 900;
                        text-align: center;
                        padding: 20px;
                        background: var(--bg-secondary);
                        border: 3px solid var(--accent);
                        border-radius: 16px;
                        color: var(--text-primary);
                        outline: none;
                        transition: all 0.3s;
                        letter-spacing: -0.02em;
                        -moz-appearance: textfield;
                    }
                    .money-input::-webkit-outer-spin-button,
                    .money-input::-webkit-inner-spin-button {
                        -webkit-appearance: none;
                        margin: 0;
                    }
                    .money-input:focus {
                        border-color: #10b981;
                        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
                        transform: scale(1.02);
                    }
                    .quick-amount {
                        padding: 14px 20px;
                        background: var(--bg-secondary);
                        border: 2px solid var(--border);
                        border-radius: 12px;
                        color: var(--text-primary);
                        font-weight: 700;
                        cursor: pointer;
                        transition: all 0.2s;
                        font-size: 1.125rem;
                    }
                    .quick-amount:hover {
                        background: var(--accent);
                        border-color: var(--accent);
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
                    }
                    .send-money-btn {
                        width: 100%;
                        padding: 18px;
                        background: linear-gradient(135deg, #10b981, #059669);
                        border: none;
                        border-radius: 12px;
                        color: white;
                        font-weight: 800;
                        font-size: 1.25rem;
                        cursor: pointer;
                        transition: all 0.3s;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                    }
                    .send-money-btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
                    }
                    .send-money-btn:active {
                        transform: translateY(0);
                    }
                    .cancel-btn {
                        width: 100%;
                        padding: 14px;
                        background: var(--bg-secondary);
                        border: none;
                        border-radius: 12px;
                        color: var(--text-secondary);
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s;
                    }
                    .cancel-btn:hover {
                        background: var(--bg-tertiary);
                        color: var(--text-primary);
                    }
                </style>
                
                <h2 style="font-size: 2rem; font-weight: 900; margin-bottom: 8px; text-align: center;">💰 Geld senden</h2>
                <p style="color: var(--text-secondary); text-align: center; margin-bottom: 32px;">Wähle einen Betrag oder gib einen eigenen ein</p>
                
                <div style="margin-bottom: 24px;">
                    <input type="number" id="moneyAmountInput" class="money-input" placeholder="0.00" step="0.01" min="0.01" autofocus />
                    <div style="text-align: center; color: var(--text-secondary); font-size: 0.875rem; margin-top: 8px;">EUR €</div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
                    <button class="quick-amount" data-amount="5">5 €</button>
                    <button class="quick-amount" data-amount="10">10 €</button>
                    <button class="quick-amount" data-amount="20">20 €</button>
                    <button class="quick-amount" data-amount="50">50 €</button>
                    <button class="quick-amount" data-amount="100">100 €</button>
                    <button class="quick-amount" data-amount="200">200 €</button>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <button id="confirmMoneyBtn" class="send-money-btn">
                        💸 Jetzt senden
                    </button>
                    <button id="cancelMoneyBtn" class="cancel-btn">
                        Abbrechen
                    </button>
                </div>
            `;
            
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            
            const input = document.getElementById('moneyAmountInput');
            const confirmBtn = document.getElementById('confirmMoneyBtn');
            const cancelBtn = document.getElementById('cancelMoneyBtn');
            
            // Disable scroll on number input
            input.addEventListener('wheel', function(e) {
                e.preventDefault();
            });
            
            // Quick amount buttons
            document.querySelectorAll('.quick-amount').forEach(btn => {
                btn.addEventListener('click', function() {
                    const amount = this.getAttribute('data-amount');
                    input.value = amount;
                    input.focus();
                });
            });
            
            // Confirm button
            confirmBtn.addEventListener('click', function() {
                const amount = parseFloat(input.value);
                if (amount && !isNaN(amount) && amount > 0) {
                    modal.remove();
                    sendMoneyTransfer(amount);
                } else {
                    input.style.borderColor = '#ef4444';
                    input.focus();
                    setTimeout(() => {
                        input.style.borderColor = 'var(--accent)';
                    }, 500);
                }
            });
            
            // Cancel button
            cancelBtn.addEventListener('click', function() {
                modal.remove();
            });
            
            // Click outside to close
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            // Enter to confirm
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    confirmBtn.click();
                }
            });
            
            // Focus input
            setTimeout(() => input.focus(), 100);
        }
        
        // Send button click handler
        const sendBtn = document.getElementById('sendBtn');
        if (sendBtn) {
            sendBtn.addEventListener('click', sendMessage);
        }
        
        // Auto-resize textarea
        const textarea = document.getElementById('messageInput');
        if (textarea) {
            // Typing indicator
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                
                // Send typing notification
                if (typeof onUserTyping === 'function') {
                    onUserTyping();
                }
            });
            
            // Enter to send
            textarea.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
        
        // Reset lastMessageId when switching chats
        lastMessageId = 0;
        
        loadMessages();
        
        // Poll for new messages
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(loadMessages, 2000);
    }
    
    let lastMessageId = 0;
    
    async function loadMessages() {
        if (!currentChatId || !currentChatType) return;
        
        try {
            const response = await fetch(`/api/chat/get_messages.php?type=${currentChatType}&id=${currentChatId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                const messagesContainer = document.getElementById('chatMessages');
                if (!messagesContainer) return;
                
                const wasAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 100;
                
                // Check for new messages only
                const newMessages = data.messages.filter(msg => msg.id > lastMessageId);
                
                if (newMessages.length > 0 || lastMessageId === 0) {
                    // If first load, render all
                    if (lastMessageId === 0) {
                        messagesContainer.innerHTML = data.messages.map(msg => renderMessage(msg)).join('');
                    } else {
                        // Append new messages only
                        newMessages.forEach(msg => {
                            messagesContainer.insertAdjacentHTML('beforeend', renderMessage(msg));
                        });
                    }
                    
                    // Update last message ID
                    if (data.messages.length > 0) {
                        lastMessageId = Math.max(...data.messages.map(m => m.id));
                    }
                    
                    if (wasAtBottom || lastMessageId === 0) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }
    
    function renderMessage(msg) {
        const isOwn = msg.sender_id == userId;
        const initials = msg.sender_name ? msg.sender_name.split(' ').map(n => n[0]).join('').toUpperCase().substr(0, 2) : '?';
        
        // Render file/media content
        let fileContent = '';
        if (msg.file_path) {
            const ext = msg.file_name.split('.').pop().toLowerCase();
            
            // Images
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                fileContent = `
                    <div class="chat-message-image" onclick="openMediaViewer('${msg.file_path}', 'image')">
                        <img src="${msg.file_path}" alt="${msg.file_name}" style="max-width: 300px; max-height: 400px; border-radius: 8px; cursor: pointer; display: block;" />
                    </div>
                `;
            }
            // Videos
            else if (['mp4', 'webm', 'ogg', 'mov'].includes(ext)) {
                fileContent = `
                    <div class="chat-message-video">
                        <video controls style="max-width: 300px; max-height: 400px; border-radius: 8px; display: block;">
                            <source src="${msg.file_path}" type="video/${ext}">
                            Video wird nicht unterstützt
                        </video>
                    </div>
                `;
            }
            // Audio / Voice Messages
            else if (['mp3', 'wav', 'ogg', 'webm'].includes(ext) || msg.file_path.includes('voice_messages')) {
                const isVoiceMsg = msg.file_path.includes('voice_messages');
                const audioId = 'audio_' + msg.id;
                const waveId = 'wave_' + msg.id;
                
                fileContent = `
                    <div class="voice-message-container" style="
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        padding: 12px 16px;
                        background: ${msg.sender_id === currentUserId ? 'rgba(139, 92, 246, 0.1)' : 'var(--bg-secondary)'};
                        border-radius: 24px;
                        max-width: 280px;
                        position: relative;
                        border: 1px solid ${msg.sender_id === currentUserId ? 'rgba(139, 92, 246, 0.2)' : 'var(--border)'};
                    ">
                        <audio id="${audioId}" style="display: none;">
                            <source src="${msg.file_path}" type="audio/${ext}">
                        </audio>
                        
                        <!-- Play/Pause Button -->
                        <button onclick="toggleVoicePlayback('${audioId}', '${waveId}')" style="
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            background: var(--accent);
                            border: none;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-shrink: 0;
                            transition: all 0.2s;
                            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
                        " onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                            <span id="${audioId}_icon" style="color: white; font-size: 1rem;">▶️</span>
                        </button>
                        
                        <!-- Waveform Visualization -->
                        <div class="voice-waveform" id="${waveId}" style="
                            flex: 1;
                            display: flex;
                            align-items: center;
                            gap: 2px;
                            height: 32px;
                        ">
                            ${Array.from({length: 25}, (_, i) => {
                                const height = Math.random() * 100;
                                return `<div class="wave-bar" style="
                                    width: 3px;
                                    height: ${20 + height * 0.4}%;
                                    background: ${msg.sender_id === currentUserId ? 'var(--accent)' : 'var(--text-tertiary)'};
                                    border-radius: 2px;
                                    transition: all 0.3s;
                                    opacity: 0.6;
                                "></div>`;
                            }).join('')}
                        </div>
                        
                        <!-- Duration -->
                        <span id="${audioId}_duration" style="
                            font-size: 0.75rem;
                            color: var(--text-secondary);
                            font-weight: 600;
                            min-width: 35px;
                            text-align: right;
                        ">0:00</span>
                    </div>
                `;
            }
            // Other files
            else {
                fileContent = `
                    <a href="${msg.file_path}" target="_blank" class="chat-message-file" download="${msg.file_name}" style="display: flex; align-items: center; gap: 8px; padding: 12px; background: var(--bg-secondary); border-radius: 8px; text-decoration: none; color: inherit; margin-top: 8px;">
                        <span style="font-size: 1.5rem;">📎</span>
                        <div>
                            <div style="font-weight: 600;">${msg.file_name}</div>
                            <div style="opacity: 0.7; font-size: 0.75rem;">${formatFileSize(msg.file_size)}</div>
                        </div>
                    </a>
                `;
            }
        }
        
        // Check if message is money transfer
        let messageContent = escapeHtml(msg.message);
        const moneyMatch = msg.message.match(/💰 Geld gesendet: ([\d,.]+) €/);
        if (moneyMatch) {
            messageContent = `
                <div class="money-transfer-card" style="
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    color: white;
                    padding: 24px;
                    border-radius: 16px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                    box-shadow: 0 8px 32px rgba(16, 185, 129, 0.4);
                    animation: moneyPulse 0.6s ease-out;
                ">
                    <!-- Animated background circles -->
                    <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); animation: rotate 20s linear infinite;"></div>
                    <div style="position: absolute; top: 10%; right: 10%; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 3s ease-in-out infinite;"></div>
                    <div style="position: absolute; bottom: 20%; left: 15%; width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 4s ease-in-out infinite 0.5s;"></div>
                    
                    <!-- Content -->
                    <div style="position: relative; z-index: 2;">
                        <div style="font-size: 3.5rem; margin-bottom: 12px; animation: coinFlip 0.8s ease-out; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));">💰</div>
                        <div style="font-size: 2rem; font-weight: 900; margin-bottom: 8px; letter-spacing: -0.02em; animation: scaleIn 0.5s ease-out 0.2s both;">
                            ${moneyMatch[1]} €
                        </div>
                        <div style="font-size: 0.95rem; opacity: 0.95; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; animation: fadeInUp 0.5s ease-out 0.3s both;">
                            ${isOwn ? '✓ Geld gesendet' : '✓ Geld erhalten'}
                        </div>
                    </div>
                    
                    <!-- Sparkles -->
                    <div style="position: absolute; top: 15%; left: 20%; animation: sparkle 1.5s ease-in-out infinite;">✨</div>
                    <div style="position: absolute; top: 25%; right: 25%; animation: sparkle 1.5s ease-in-out infinite 0.3s;">✨</div>
                    <div style="position: absolute; bottom: 20%; left: 30%; animation: sparkle 1.5s ease-in-out infinite 0.6s;">💫</div>
                    <div style="position: absolute; bottom: 30%; right: 20%; animation: sparkle 1.5s ease-in-out infinite 0.9s;">✨</div>
                </div>
                
                <style>
                    @keyframes moneyPulse {
                        0% {
                            transform: scale(0.8);
                            opacity: 0;
                        }
                        50% {
                            transform: scale(1.05);
                        }
                        100% {
                            transform: scale(1);
                            opacity: 1;
                        }
                    }
                    
                    @keyframes coinFlip {
                        0% {
                            transform: rotateY(0deg) scale(0.5);
                            opacity: 0;
                        }
                        50% {
                            transform: rotateY(180deg) scale(1.2);
                        }
                        100% {
                            transform: rotateY(360deg) scale(1);
                            opacity: 1;
                        }
                    }
                    
                    @keyframes scaleIn {
                        0% {
                            transform: scale(0);
                            opacity: 0;
                        }
                        100% {
                            transform: scale(1);
                            opacity: 1;
                        }
                    }
                    
                    @keyframes fadeInUp {
                        0% {
                            transform: translateY(20px);
                            opacity: 0;
                        }
                        100% {
                            transform: translateY(0);
                            opacity: 1;
                        }
                    }
                    
                    @keyframes float {
                        0%, 100% {
                            transform: translateY(0) scale(1);
                        }
                        50% {
                            transform: translateY(-20px) scale(1.1);
                        }
                    }
                    
                    @keyframes sparkle {
                        0%, 100% {
                            opacity: 0;
                            transform: scale(0) rotate(0deg);
                        }
                        50% {
                            opacity: 1;
                            transform: scale(1.5) rotate(180deg);
                        }
                    }
                    
                    @keyframes rotate {
                        0% {
                            transform: rotate(0deg);
                        }
                        100% {
                            transform: rotate(360deg);
                        }
                    }
                </style>
            `;
        }
        
        return `
            <div class="chat-message ${isOwn ? 'own' : ''}" data-msg-id="${msg.id}">
                <div class="chat-message-avatar">${initials}</div>
                <div class="chat-message-content">
                    <div class="chat-message-bubble">
                        ${!isOwn ? `<div style="font-weight: 600; font-size: 0.75rem; margin-bottom: 4px; opacity: 0.8;">${msg.sender_name}</div>` : ''}
                        <div class="chat-message-text">${messageContent}</div>
                        ${fileContent}
                    </div>
                    <div class="chat-message-meta">${formatTime(msg.created_at)}</div>
                </div>
            </div>
        `;
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
    
    // VOICE RECORDING FUNCTIONS
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingStream = null;
    let selectedMicrophoneId = null;
    
    // Get available microphones
    async function getAvailableMicrophones() {
        try {
            // Request permission first to get device labels
            await navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    // Stop the stream immediately, we just needed permission
                    stream.getTracks().forEach(track => track.stop());
                });
            
            const devices = await navigator.mediaDevices.enumerateDevices();
            const microphones = devices.filter(device => device.kind === 'audioinput');
            
            console.log('Available microphones:', microphones);
            return microphones;
        } catch (error) {
            console.error('Error getting microphones:', error);
            return [];
        }
    }
    
    // Show microphone selection modal
    async function showMicrophoneSelector() {
        const microphones = await getAvailableMicrophones();
        
        if (microphones.length === 0) {
            alert('❌ Keine Mikrofone gefunden!');
            return null;
        }
        
        // Create modal
        const modal = document.createElement('div');
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; display: flex; align-items: center; justify-content: center;';
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = 'background: var(--bg-primary); padding: 32px; border-radius: 16px; max-width: 500px; width: 90%;';
        
        modalContent.innerHTML = `
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 20px;">🎤 Mikrofon auswählen</h2>
            <div id="microphoneList" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
                ${microphones.map((mic, index) => `
                    <div class="mic-option" data-device-id="${mic.deviceId}" style="padding: 16px; background: var(--bg-secondary); border-radius: 12px; cursor: pointer; transition: all 0.2s; border: 2px solid transparent;">
                        <div style="font-weight: 600; margin-bottom: 4px;">${mic.label || 'Mikrofon ' + (index + 1)}</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Device ID: ${mic.deviceId.substring(0, 20)}...</div>
                    </div>
                `).join('')}
            </div>
            <div style="display: flex; gap: 12px;">
                <button id="cancelMicBtn" style="flex: 1; padding: 14px; background: var(--bg-secondary); border: none; border-radius: 8px; color: var(--text-primary); font-weight: 600; cursor: pointer;">Abbrechen</button>
                <button id="confirmMicBtn" style="flex: 1; padding: 14px; background: var(--accent); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer;" disabled>Bestätigen</button>
            </div>
        `;
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        
        // Handle selection
        let selectedDeviceId = null;
        const micOptions = modalContent.querySelectorAll('.mic-option');
        const confirmBtn = modalContent.querySelector('#confirmMicBtn');
        const cancelBtn = modalContent.querySelector('#cancelMicBtn');
        
        micOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove previous selection
                micOptions.forEach(opt => {
                    opt.style.border = '2px solid transparent';
                    opt.style.background = 'var(--bg-secondary)';
                });
                
                // Highlight selected
                this.style.border = '2px solid var(--accent)';
                this.style.background = 'var(--accent)15';
                selectedDeviceId = this.getAttribute('data-device-id');
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
            });
            
            // Hover effect
            option.addEventListener('mouseenter', function() {
                if (this.style.border !== '2px solid var(--accent)') {
                    this.style.background = 'var(--bg-tertiary)';
                }
            });
            option.addEventListener('mouseleave', function() {
                if (this.style.border !== '2px solid var(--accent)') {
                    this.style.background = 'var(--bg-secondary)';
                }
            });
        });
        
        return new Promise((resolve) => {
            confirmBtn.addEventListener('click', () => {
                modal.remove();
                resolve(selectedDeviceId);
            });
            
            cancelBtn.addEventListener('click', () => {
                modal.remove();
                resolve(null);
            });
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(null);
                }
            });
        });
    }
    
    async function startVoiceRecording() {
        try {
            // Show microphone selector if not selected
            if (!selectedMicrophoneId) {
                selectedMicrophoneId = await showMicrophoneSelector();
                if (!selectedMicrophoneId) {
                    console.log('No microphone selected');
                    return;
                }
            }
            
            // Request microphone access with selected device
            const constraints = {
                audio: {
                    deviceId: selectedMicrophoneId ? { exact: selectedMicrophoneId } : undefined,
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            };
            
            recordingStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Create MediaRecorder with better quality settings
            const options = {
                mimeType: 'audio/webm;codecs=opus',
                audioBitsPerSecond: 128000
            };
            
            mediaRecorder = new MediaRecorder(recordingStream, options);
            audioChunks = [];
            
            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            };
            
            mediaRecorder.onstop = async () => {
                // Create audio blob
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                
                console.log('Audio blob size:', audioBlob.size, 'bytes');
                
                // Upload voice message
                await uploadVoiceMessage(audioBlob);
                
                // Stop all tracks
                if (recordingStream) {
                    recordingStream.getTracks().forEach(track => track.stop());
                    recordingStream = null;
                }
            };
            
            // Start recording
            mediaRecorder.start();
            console.log('🎤 Recording started with microphone:', selectedMicrophoneId);
            
        } catch (error) {
            console.error('Microphone access error:', error);
            alert('❌ Mikrofon-Zugriff fehlgeschlagen!\n\nBitte erlaube den Zugriff auf dein Mikrofon.');
            selectedMicrophoneId = null; // Reset selection on error
        }
    }
    
    function stopVoiceRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            console.log('🎤 Recording stopped');
        }
    }
    
    async function uploadVoiceMessage(audioBlob) {
        if (!currentChatId || !currentChatType) {
            alert('Bitte wähle zuerst einen Chat aus!');
            return;
        }
        
        if (audioBlob.size === 0) {
            alert('❌ Aufnahme ist leer. Bitte versuche es erneut.');
            return;
        }
        
        try {
            const formData = new FormData();
            const timestamp = Date.now();
            formData.append('file', audioBlob, `voice_message_${timestamp}.webm`);
            formData.append('type', currentChatType);
            formData.append('id', currentChatId);
            
            console.log('Uploading voice message:', {
                size: audioBlob.size,
                type: currentChatType,
                id: currentChatId,
                filename: `voice_message_${timestamp}.webm`
            });
            
            const response = await fetch('/api/chat/upload_file.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is ok
            if (!response.ok) {
                console.error('HTTP Error:', response.status, response.statusText);
                const text = await response.text();
                console.error('Response text:', text);
                alert('❌ Server-Fehler: ' + response.status);
                return;
            }
            
            const text = await response.text();
            console.log('Server response text:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Response was:', text);
                alert('❌ Server hat ungültige Antwort gesendet. Siehe Console für Details.');
                return;
            }
            
            if (data.status === 'success') {
                console.log('✅ Voice message sent!');
                loadMessages();
            } else {
                console.error('Upload error:', data);
                alert('❌ Fehler beim Senden: ' + data.error);
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('❌ Upload-Fehler: ' + error.message);
        }
    }
    
    // MONEY TRANSFER FUNCTION
    async function sendMoneyTransfer(amount) {
        if (!currentChatId || currentChatType !== 'user') {
            alert('Geldtransfer nur in Privatchats möglich!');
            return;
        }
        
        try {
            const response = await fetch('/api/chat/send_money.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    receiver_id: currentChatId,
                    amount: amount
                })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                // Send message notification
                await fetch('/api/chat/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: currentChatType,
                        id: currentChatId,
                        message: `💰 Geld gesendet: ${amount.toFixed(2)} €`
                    })
                });
                loadMessages();
                // Success - no alert popup
                console.log(`✅ ${amount.toFixed(2)} € erfolgreich gesendet!`);
            } else {
                alert('❌ Fehler: ' + data.error);
            }
        } catch (error) {
            alert('❌ Verbindungsfehler: ' + error.message);
        }
    }
    
    // MEDIA VIEWER FOR IMAGES
    function openMediaViewer(src, type) {
        const viewer = document.createElement('div');
        viewer.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 99999; display: flex; align-items: center; justify-content: center; cursor: zoom-out;';
        
        if (type === 'image') {
            viewer.innerHTML = `
                <img src="${src}" style="max-width: 90%; max-height: 90%; object-fit: contain; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.5);" />
                <button onclick="this.parentElement.remove()" style="position: absolute; top: 20px; right: 20px; background: white; border: none; color: black; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 2rem; line-height: 1;">×</button>
            `;
        }
        
        viewer.onclick = function(e) {
            if (e.target === viewer) {
                viewer.remove();
            }
        };
        
        document.body.appendChild(viewer);
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
    
    // Voice Message Playback with Waveform Animation
    const activeAudios = new Map();
    
    function toggleVoicePlayback(audioId, waveId) {
        const audio = document.getElementById(audioId);
        const icon = document.getElementById(audioId + '_icon');
        const durationEl = document.getElementById(audioId + '_duration');
        const waveContainer = document.getElementById(waveId);
        const waveBars = waveContainer ? waveContainer.querySelectorAll('.wave-bar') : [];
        
        if (!audio) return;
        
        // Pause all other audios
        activeAudios.forEach((otherAudio, otherId) => {
            if (otherId !== audioId && !otherAudio.paused) {
                otherAudio.pause();
                const otherIcon = document.getElementById(otherId + '_icon');
                if (otherIcon) otherIcon.textContent = '▶️';
            }
        });
        
        if (audio.paused) {
            audio.play();
            icon.textContent = '⏸️';
            activeAudios.set(audioId, audio);
            
            // Add playing class for animation
            if (waveContainer) {
                waveContainer.parentElement.classList.add('playing');
            }
            
            // Animate waveform
            waveBars.forEach((bar, index) => {
                setTimeout(() => {
                    bar.style.opacity = '1';
                }, index * 20);
            });
            
        } else {
            audio.pause();
            icon.textContent = '▶️';
            
            // Remove playing class
            if (waveContainer) {
                waveContainer.parentElement.classList.remove('playing');
            }
            
            // Reset waveform
            waveBars.forEach(bar => {
                bar.style.opacity = '0.6';
            });
        }
        
        // Update duration
        audio.addEventListener('loadedmetadata', () => {
            if (!isNaN(audio.duration)) {
                durationEl.textContent = formatDuration(audio.duration);
            }
        });
        
        audio.addEventListener('timeupdate', () => {
            const remaining = audio.duration - audio.currentTime;
            durationEl.textContent = formatDuration(remaining);
            
            // Update waveform progress
            const progress = audio.currentTime / audio.duration;
            waveBars.forEach((bar, index) => {
                if (index / waveBars.length <= progress) {
                    bar.style.background = 'var(--accent)';
                    bar.style.opacity = '1';
                } else {
                    bar.style.opacity = '0.4';
                }
            });
        });
        
        audio.addEventListener('ended', () => {
            icon.textContent = '▶️';
            durationEl.textContent = formatDuration(audio.duration);
            
            // Remove playing class
            if (waveContainer) {
                waveContainer.parentElement.classList.remove('playing');
            }
            
            // Reset waveform
            waveBars.forEach(bar => {
                bar.style.opacity = '0.6';
                bar.style.background = '';
            });
            
            activeAudios.delete(audioId);
        });
    }
    
    function formatDuration(seconds) {
        if (isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    // Make functions global
    window.toggleVoicePlayback = toggleVoicePlayback;
    
    // Make hideChat global
    window.hideChat = hideChat;
    </script>

    <!-- PREMIUM CHAT FEATURES INTEGRATION -->
    <script src="/chat_premium_features.js"></script>
    
    <script>
    // Integrate premium features into existing chat
    const originalSendMessage = window.sendMessage || function() {};
    
    // Add typing indicator on input
    const messageInput = document.querySelector('.chat-input textarea, .chat-input input[type="text"]');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            if (typeof onUserTyping === 'function') {
                onUserTyping();
            }
        });
    }
    
    // Add voice recording button to chat input
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const chatInput = document.querySelector('.chat-input');
            if (chatInput && !document.querySelector('.voice-record-btn')) {
                const voiceBtn = document.createElement('button');
                voiceBtn.className = 'voice-record-btn';
                voiceBtn.innerHTML = '🎤';
                voiceBtn.title = 'Sprachnachricht';
                voiceBtn.style.cssText = 'background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 8px;';
                voiceBtn.onmousedown = function() {
                    if (typeof startVoiceRecording === 'function') {
                        startVoiceRecording();
                    }
                };
                voiceBtn.onmouseup = function() {
                    if (typeof stopVoiceRecording === 'function') {
                        stopVoiceRecording();
                    }
                };
                
                const sendBtn = chatInput.querySelector('button');
                if (sendBtn) {
                    sendBtn.parentNode.insertBefore(voiceBtn, sendBtn);
                }
            }
        }, 1000);
    });
    
    console.log('✅ Premium Chat Features aktiviert!');
    </script>

    <!-- Voice Recording Indicator -->
    <div id="recordingIndicator" style="display: none; position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%); background: #ef4444; color: white; padding: 20px 40px; border-radius: 50px; align-items: center; gap: 16px; box-shadow: 0 8px 32px rgba(239, 68, 68, 0.5); z-index: 10000;">
        <div style="width: 12px; height: 12px; background: white; border-radius: 50%; animation: pulse 1.5s infinite;"></div>
        <span style="font-weight: 700;">Aufnahme läuft...</span>
        <button onclick="if(typeof stopVoiceRecording==='function') stopVoiceRecording()" style="background: white; color: #ef4444; border: none; padding: 8px 20px; border-radius: 20px; font-weight: 700; cursor: pointer;">Senden</button>
    </div>
    
    <!-- Typing Indicator -->
    <div id="typingIndicator" style="display: none; position: fixed; bottom: 60px; left: 20px; color: var(--text-secondary); font-size: 0.875rem; font-style: italic;"></div>
    
    <style>
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.2); }
    }
    </style>

</body>
</html>

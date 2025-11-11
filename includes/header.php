<?php
// Notification Badges
// 1. Pending Events (keine Antwort gegeben)
$pending_events_count = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM events e
    WHERE e.event_status = 'active' 
    AND e.datum >= CURDATE()
    AND e.id NOT IN (
        SELECT event_id 
        FROM event_participants 
        WHERE mitglied_id = ?
    )
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($pending_events_count);
$stmt->fetch();
$stmt->close();

// 2. Active Shift NOW
$active_shift_count = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM shifts 
    WHERE user_id = ? 
    AND date = CURDATE()
    AND CONCAT(date, ' ', start_time) <= NOW()
    AND CONCAT(date, ' ', end_time) >= NOW()
    AND type != 'free'
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($active_shift_count);
$stmt->fetch();
$stmt->close();

// 3. Unread Chat Messages
$unread_messages_count = 0;
$result = $conn->query("SHOW TABLES LIKE 'chat_messages'");
if ($result && $result->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM chat_messages 
        WHERE receiver_id = ? 
        AND is_read = 0
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($unread_messages_count);
    $stmt->fetch();
    $stmt->close();
}

// Casino Badge - nur wenn Guthaben > 10€
$user_balance = 0;
$stmt_bal = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt_bal->bind_param('i', $user_id);
$stmt_bal->execute();
$stmt_bal->bind_result($user_balance);
$stmt_bal->fetch();
$stmt_bal->close();
$show_casino = $user_balance >= 10.00;
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            line-height: 1.4;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { 
                transform: scale(1);
                opacity: 1;
            }
            50% { 
                transform: scale(1.1);
                opacity: 0.9;
            }
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">
                PUSHING P
                <?php if ($is_admin_user ?? false): ?>
                    <span style="color: #7f1010; margin-left: 12px; font-weight: 700; font-size: 0.9rem; background: rgba(127, 16, 16, 0.1); padding: 4px 12px; border-radius: 6px; border: 1px solid rgba(127, 16, 16, 0.3);">Admin</span>
                <?php endif; ?>
            </a>
            <nav class="nav">
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item" style="position: relative;">
                    Events
                    <?php if ($pending_events_count > 0): ?>
                        <span class="notification-badge"><?= $pending_events_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="schichten.php" class="nav-item" style="position: relative;">
                    Schichten
                    <?php if ($active_shift_count > 0): ?>
                        <span class="notification-badge" style="background: #10b981;">🔴</span>
                    <?php endif; ?>
                </a>
                <?php if ($show_casino): ?>
                    <a href="casino.php" class="nav-item" style="position: relative;">
                        🎰 Casino
                        <span id="casinoMultiplayerBadge" class="notification-badge" style="display: none; background: #f59e0b;"></span>
                    </a>
                <?php endif; ?>
                <a href="leaderboard.php" class="nav-item">Leaderboard</a>
                <a href="chat.php" class="nav-item" style="position: relative;">
                    Chat
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="notification-badge"><?= $unread_messages_count ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($is_admin_user ?? false): ?>
                    <a href="admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

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
        /* Mobile Header Styles */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .nav-item {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
            position: relative;
            text-decoration: none;
            color: var(--text-secondary);
        }
        
        .nav-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
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
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .mobile-menu-toggle:hover {
            background: var(--bg-secondary);
            border-color: var(--accent);
        }
        
        .mobile-menu-toggle span {
            display: block;
            width: 24px;
            height: 2px;
            background: var(--text-primary);
            margin: 5px 0;
            transition: all 0.3s;
            border-radius: 2px;
        }
        
        .mobile-menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }
        
        .mobile-menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }
        
        .mobile-menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }
        
        /* Mobile Responsive */
        @media (max-width: 968px) {
            .header-content {
                padding: 12px 16px;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .nav {
                position: fixed;
                top: 60px;
                right: -100%;
                width: 280px;
                height: calc(100vh - 60px);
                background: var(--bg-secondary);
                border-left: 1px solid var(--border);
                flex-direction: column;
                align-items: stretch;
                gap: 4px;
                padding: 16px;
                transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow-y: auto;
                box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
            }
            
            .nav.active {
                right: 0;
            }
            
            .nav-item {
                padding: 12px 16px;
                font-size: 0.9rem;
                border-radius: 8px;
            }
            
            .logo {
                font-size: 1.125rem;
            }
            
            .logo span {
                font-size: 0.75rem !important;
                padding: 2px 8px !important;
            }
        }
        
        @media (max-width: 430px) {
            .header-content {
                padding: 10px 12px;
            }
            
            .logo {
                font-size: 1rem;
            }
            
            .nav {
                width: 100%;
                right: -100%;
            }
        }
    </style>
    <?php if (isset($page_styles)): ?>
        <?= $page_styles ?>
    <?php endif; ?>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="dashboard.php" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">
                <span style="background: linear-gradient(135deg, var(--accent), #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">PUSHING P</span>
                <?php if ($is_admin_user ?? false): ?>
                    <span style="color: #ef4444; font-size: 0.75rem; font-weight: 700; background: rgba(239, 68, 68, 0.1); padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.3);">ADMIN</span>
                <?php endif; ?>
            </a>
            
            <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <nav class="nav" id="mobileNav">
                <a href="dashboard.php" class="nav-item">
                    <span>🏠</span> Dashboard
                </a>
                <a href="kasse.php" class="nav-item">
                    <span>💰</span> Kasse
                </a>
                <a href="events.php" class="nav-item" style="position: relative;">
                    <span>🎉</span> Events
                    <?php if ($pending_events_count > 0): ?>
                        <span class="notification-badge"><?= $pending_events_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="schichten.php" class="nav-item" style="position: relative;">
                    <span>📅</span> Schichten
                    <?php if ($active_shift_count > 0): ?>
                        <span class="notification-badge" style="background: #10b981;">🔴</span>
                    <?php endif; ?>
                </a>
                <?php if ($show_casino): ?>
                    <a href="casino.php" class="nav-item" style="position: relative;">
                        <span>🎰</span> Casino
                        <span id="casinoMultiplayerBadge" class="notification-badge" style="display: none; background: #f59e0b;"></span>
                    </a>
                <?php endif; ?>
                <a href="leaderboard.php" class="nav-item">
                    <span>🏆</span> Leaderboard
                </a>
                <a href="chat.php" class="nav-item" style="position: relative;">
                    <span>💬</span> Chat
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="notification-badge"><?= $unread_messages_count ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($is_admin_user ?? false): ?>
                    <a href="admin.php" class="nav-item">
                        <span>⚙️</span> Admin
                    </a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">
                    <span>⚙️</span> Settings
                </a>
                <a href="logout.php" class="nav-item" style="color: #ef4444;">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </div>
    </div>
    
    <script>
        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            const toggle = document.getElementById('mobileMenuToggle');
            nav.classList.toggle('active');
            toggle.classList.toggle('active');
        }
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            const nav = document.getElementById('mobileNav');
            const toggle = document.getElementById('mobileMenuToggle');
            if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('active');
                toggle.classList.remove('active');
            }
        });
        
        // Close menu when clicking a link
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                document.getElementById('mobileNav').classList.remove('active');
                document.getElementById('mobileMenuToggle').classList.remove('active');
            });
        });
    </script>

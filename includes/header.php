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
        /* Modern Two-Row Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
        }
        
        .header-top {
            max-width: 1400px;
            margin: 0 auto;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }
        
        .header-bottom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 8px 20px;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .top-nav {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .bottom-nav {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .nav-btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
            position: relative;
            text-decoration: none;
            color: var(--text-secondary);
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-btn:hover {
            background: var(--bg-tertiary);
            border-color: var(--accent);
            color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(88, 101, 242, 0.2);
        }
        
        .nav-btn.top-btn {
            padding: 8px 16px;
            font-size: 0.813rem;
        }
        
        .nav-btn.logout {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .nav-btn.logout:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
        }
        
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            line-height: 1.2;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.5);
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
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .mobile-menu-toggle span {
            display: block;
            width: 24px;
            height: 2px;
            background: var(--text-primary);
            margin: 4px 0;
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
        @media (max-width: 768px) {
            .header-top {
                padding: 10px 16px;
            }
            
            .header-bottom {
                display: none;
            }
            
            .logo {
                font-size: 1.125rem;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .top-nav {
                position: fixed;
                top: 60px;
                right: -100%;
                width: 280px;
                max-width: 85vw;
                height: calc(100vh - 60px);
                background: var(--bg-secondary);
                border-left: 1px solid var(--border);
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                padding: 16px;
                transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow-y: auto;
                box-shadow: -4px 0 20px rgba(0, 0, 0, 0.5);
            }
            
            .top-nav.active {
                right: 0;
            }
            
            .nav-btn {
                width: 100%;
                justify-content: flex-start;
                padding: 14px 16px;
                font-size: 0.938rem;
            }
            
            .nav-btn.top-btn {
                padding: 14px 16px;
                font-size: 0.938rem;
            }
        }
        
        @media (max-width: 430px) {
            .logo {
                font-size: 1rem;
            }
            
            .top-nav {
                width: 100%;
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
        <!-- Top Row: Logo + Top Actions -->
        <div class="header-top">
            <a href="dashboard.php" class="logo">
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
            
            <div class="top-nav" id="mobileNav">
                <a href="chat.php" class="nav-btn top-btn" style="position: relative;">
                    💬 Chat
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="notification-badge"><?= $unread_messages_count ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($is_admin_user ?? false): ?>
                    <a href="admin.php" class="nav-btn top-btn">
                        ⚙️ Admin
                    </a>
                <?php endif; ?>
                <a href="settings.php" class="nav-btn top-btn">
                    ⚙️ Settings
                </a>
                <a href="logout.php" class="nav-btn top-btn logout">
                    🚪 Logout
                </a>
            </div>
        </div>
        
        <!-- Bottom Row: Main Navigation Buttons -->
        <div class="header-bottom">
            <a href="kasse.php" class="nav-btn">
                💰 Kasse
            </a>
            <a href="events.php" class="nav-btn" style="position: relative;">
                🎉 Events
                <?php if ($pending_events_count > 0): ?>
                    <span class="notification-badge"><?= $pending_events_count ?></span>
                <?php endif; ?>
            </a>
            <a href="schichten.php" class="nav-btn" style="position: relative;">
                📅 Schichten
                <?php if ($active_shift_count > 0): ?>
                    <span class="notification-badge" style="background: #10b981;">🔴</span>
                <?php endif; ?>
            </a>
            <?php if ($show_casino): ?>
                <a href="casino.php" class="nav-btn" style="position: relative;">
                    🎰 Casino
                    <span id="casinoMultiplayerBadge" class="notification-badge" style="display: none; background: #f59e0b;"></span>
                </a>
            <?php endif; ?>
            <a href="leaderboard.php" class="nav-btn">
                🏆 Leaderboard
            </a>
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
            if (nav && toggle && !nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('active');
                toggle.classList.remove('active');
            }
        });
        
        // Close menu when clicking a link
        document.querySelectorAll('.nav-btn').forEach(item => {
            item.addEventListener('click', () => {
                const nav = document.getElementById('mobileNav');
                const toggle = document.getElementById('mobileMenuToggle');
                if (nav && toggle) {
                    nav.classList.remove('active');
                    toggle.classList.remove('active');
                }
            });
        });
    </script>

<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/xp_system.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin_user = is_admin();

// Get XP info
$xp_info = get_user_level_info($user_id);
$user_badges = get_user_badges($user_id);

// Schichten in den nächsten 24h (inkl. laufende Schichten)
$next_24h_shifts = [];
$result = $conn->query("
    SELECT 
        s.date, s.type, s.start_time, s.end_time, u.name
    FROM shifts s
    JOIN users u ON u.id = s.user_id
    WHERE CONCAT(s.date, ' ', s.end_time) >= NOW()
    AND CONCAT(s.date, ' ', s.start_time) <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
    AND s.type != 'free'
    AND u.status = 'active'
    ORDER BY s.date ASC, s.start_time ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $next_24h_shifts[] = $row;
    }
}

$stats = [];
$result = $conn->query("SELECT pool_balance FROM paypal_pool_status ORDER BY last_updated DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $stats['balance'] = floatval($row['pool_balance']);
} else {
    $stats['balance'] = 0.00;
}
$result = $conn->query("SELECT COUNT(*) as cnt FROM events WHERE event_status = 'active' AND datum >= CURDATE()");
if ($result && $row = $result->fetch_assoc()) $stats['events'] = intval($row['cnt']);
$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) $stats['members'] = intval($row['cnt']);

$my_shifts = [];
$stmt = $conn->prepare("SELECT date, type, start_time, end_time FROM shifts WHERE user_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 5");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($shift_date, $shift_type, $shift_start, $shift_end);
while ($stmt->fetch()) $my_shifts[] = ['date' => $shift_date, 'type' => $shift_type, 'start' => $shift_start, 'end' => $shift_end];
$stmt->close();

$next_events = [];
$result = $conn->query("SELECT id, title, datum, start_time, location, cost FROM events WHERE event_status = 'active' AND datum >= CURDATE() ORDER BY datum ASC, start_time ASC LIMIT 5");
if ($result) while ($row = $result->fetch_assoc()) $next_events[] = $row;

// Aktive Crew Members
$crew_members = [];
$result = $conn->query("
    SELECT id, name, username, created_at 
    FROM users 
    WHERE status = 'active' 
    ORDER BY 
        CASE 
            WHEN name LIKE '%Alaeddin%' OR name LIKE '%alaeddin%' THEN 1
            WHEN name LIKE '%Alessio%' OR name LIKE '%alessio%' THEN 2
            WHEN name LIKE '%Ayyub%' OR name LIKE '%ayyub%' THEN 3
            WHEN name LIKE '%Yassin%' OR name LIKE '%yassin%' THEN 4
            WHEN name LIKE '%Salva%' OR name LIKE '%salva%' THEN 5
            WHEN name LIKE '%Sahin%' OR name LIKE '%sahin%' THEN 6
            WHEN name LIKE '%Elbasan%' OR name LIKE '%elbasan%' THEN 7
            WHEN name LIKE '%Adis%' OR name LIKE '%adis%' THEN 998
            WHEN name LIKE '%Bora%' OR name LIKE '%bora%' THEN 999
            ELSE 10
        END,
        name ASC
");
if ($result) while ($row = $result->fetch_assoc()) $crew_members[] = $row;

// Notification Badges
// 1. Pending Events (keine Antwort gegeben)
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – PUSHING P</title>
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
        
        .stats .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stats .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stats .stat-card:nth-child(3) { animation-delay: 0.3s; }
        
        .welcome {
            margin-bottom: 40px;
            animation: fadeIn 0.6s ease;
        }
        
        .welcome h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
        }
        
        .list-item:nth-child(1) { animation-delay: 0.1s; }
        .list-item:nth-child(2) { animation-delay: 0.2s; }
        .list-item:nth-child(3) { animation-delay: 0.3s; }
        .list-item:nth-child(4) { animation-delay: 0.4s; }
        .list-item:nth-child(5) { animation-delay: 0.5s; }
        
        .list-item-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .list-item-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        /* Mobile Optimierungen */
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            
            .welcome h1 {
                font-size: 1.5rem;
            }
            
            .stats {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 16px !important;
            }
            
            .stat-value {
                font-size: 1.75rem !important;
            }
            
            .container {
                padding: 16px !important;
            }
            
            .section {
                margin-bottom: 24px !important;
            }
            
            .section-title {
                font-size: 1.25rem !important;
            }
            
            /* Event Cards Mobile */
            .event-card-mobile {
                padding: 16px !important;
            }
            
            .event-card-mobile .event-title {
                font-size: 1.125rem !important;
            }
            
            .event-card-mobile .event-meta {
                flex-direction: column;
                gap: 8px !important;
            }
            
            .event-card-mobile .time-badge {
                padding: 6px 12px !important;
                font-size: 0.75rem !important;
            }
            
            /* Shifts List - Mobile Version für Desktop & Mobile */
            .shifts-list-mobile {
                display: block !important;
            }
            
            /* Timeline Mobile - komplett verbergen auf Mobile */
            .timeline-container {
                display: none !important;
            }
            
            /* Chart Mobile */
            #kasseChart {
                height: 200px !important;
            }
            
            .timeframe-btn {
                padding: 6px 12px !important;
                font-size: 0.75rem !important;
            }
            
            /* Crew Members Mobile */
            .crew-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            
            .crew-member-card {
                padding: 16px !important;
            }
            
            .crew-avatar {
                width: 48px !important;
                height: 48px !important;
                font-size: 1rem !important;
            }
            
            .crew-name {
                font-size: 1rem !important;
            }
            
            .crew-meta {
                font-size: 0.75rem !important;
            }
        }
        
        /* iPhone spezifisch */
        @media (max-width: 430px) {
            body {
                font-size: 14px;
            }
            
            .welcome h1 {
                font-size: 1.375rem;
            }
            
            .stat-icon {
                font-size: 1.5rem !important;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px;
            }
        }
        
        /* Timeline & Shifts List Standardmäßig sichtbar auf Desktop */
        .timeline-container {
            display: block;
        }
        
        .shifts-list-mobile {
            display: block;
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">
                PUSHING P
                <?php if ($is_admin_user): ?>
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
                <?php
                // Casino nur anzeigen wenn Guthaben > 10€
                $user_balance = 0;
                $stmt_bal = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
                $stmt_bal->bind_param('i', $user_id);
                $stmt_bal->execute();
                $stmt_bal->bind_result($user_balance);
                $stmt_bal->fetch();
                $stmt_bal->close();
                if ($user_balance >= 10.00):
                ?>
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
                <?php if ($is_admin_user): ?>
                    <a href="admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>Willkommen, <?= escape($name) ?></h1>
            <p class="text-secondary">Hier ist deine Übersicht</p>
        </div>

        <div class="stats">
            <a href="kasse.php" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer;">
                <span class="stat-icon">💰</span>
                <div class="stat-value"><?= number_format($stats['balance'] ?? 0, 2, ',', '.') ?> €</div>
                <div class="stat-label">Kassenstand</div>
            </a>
            <a href="events.php" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer;">
                <span class="stat-icon">🎉</span>
                <div class="stat-value"><?= $stats['events'] ?? 0 ?></div>
                <div class="stat-label">Kommende Events</div>
            </a>
            <a href="#crew-members" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer;" onclick="scrollToCrewMembers(event)">
                <span class="stat-icon">👥</span>
                <div class="stat-value"><?= $stats['members'] ?? 0 ?></div>
                <div class="stat-label">Crew Members</div>
            </a>
        </div>

        <!-- XP & Level Section -->
        <?php if ($xp_info): ?>
        <div class="section" style="margin-bottom: 32px;">
            <div class="section-header">
                <img src="<?= $xp_info['level_image'] ?>" alt="<?= escape($xp_info['current_level']) ?>" style="width: 40px; height: 40px; object-fit: contain;">
                <h2 class="section-title">Level <?= $xp_info['level_id'] ?> – <?= escape($xp_info['current_level']) ?></h2>
                <a href="leaderboard.php" style="color: var(--accent); text-decoration: none; font-size: 0.875rem;">Leaderboard →</a>
            </div>
            
            <div style="background: var(--bg-tertiary); padding: 24px; border-radius: 12px;">
                <!-- XP Info -->
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div style="font-size: 1.25rem; font-weight: 700;">
                            <?= number_format($xp_info['xp_total']) ?> XP
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">
                            <?= number_format($xp_info['xp_in_current_level']) ?> / <?= number_format($xp_info['xp_needed_for_next']) ?> XP zum nächsten Level
                        </div>
                    </div>
                    
                    <!-- XP Progress Bar -->
                    <div style="height: 12px; background: var(--bg-secondary); border-radius: 6px; overflow: hidden; position: relative;">
                        <div style="height: 100%; background: linear-gradient(90deg, var(--accent), #a855f7); width: <?= min(100, $xp_info['progress_percent']) ?>%; transition: width 0.5s;"></div>
                    </div>
                    
                    <div style="margin-top: 8px; font-size: 0.875rem; color: var(--text-secondary); text-align: right;">
                        <?= round($xp_info['progress_percent']) ?>% zum nächsten Level
                    </div>
                    
                    <?php if (count($user_badges) > 0): ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 12px;">
                            🏅 Deine Badges (<?= count($user_badges) ?>)
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php foreach (array_slice($user_badges, 0, 5) as $badge): ?>
                            <div style="background: var(--bg-secondary); padding: 8px 12px; border-radius: 8px; font-size: 0.875rem;" title="<?= escape($badge['description']) ?>">
                                <?= $badge['emoji'] ?> <?= escape($badge['title']) ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($user_badges) > 5): ?>
                            <div style="background: var(--bg-secondary); padding: 8px 12px; border-radius: 8px; font-size: 0.875rem; color: var(--text-secondary);">
                                +<?= count($user_badges) - 5 ?> mehr
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Kommende Events Section -->
        <?php if (!empty($next_events)): ?>
        <div class="section">
            <div style="background: var(--bg-tertiary); padding: 24px; border-radius: 12px;">
                <div class="section-header" style="margin-bottom: 24px;">
                    <span>🎉</span>
                    <h2 class="section-title">Kommende Events</h2>
                </div>
                
                <div style="display: grid; gap: 16px;">
                <?php foreach ($next_events as $event): 
                    try {
                        $start_time = $event['start_time'] ?? '00:00:00';
                        $tz = new DateTimeZone('Europe/Berlin');
                        $event_date = new DateTime($event['datum'] . ' ' . $start_time, $tz);
                        $now = new DateTime('now', $tz);
                        
                        // Kalendertage-Differenz berechnen (nicht Stunden)
                        $today = new DateTime($now->format('Y-m-d'), $tz);
                        $event_day = new DateTime($event_date->format('Y-m-d'), $tz);
                        $days_diff = (int)$today->diff($event_day)->format('%r%a');
                        
                        if ($days_diff == 0) {
                            $time_info = '🔥 Heute';
                        } elseif ($days_diff == 1) {
                            $time_info = '⚡ Morgen';
                        } elseif ($days_diff >= 2 && $days_diff <= 7) {
                            $time_info = 'In ' . $days_diff . ' Tagen';
                        } else {
                            $time_info = $event_date->format('d.m.Y');
                        }
                    } catch (Exception $e) {
                        $event_date = null;
                        $time_info = '📅 Bald';
                    }
                    
                    if (!$event_date) continue;
                ?>
                <div class="event-card-mobile" style="background: var(--bg-secondary); padding: 24px; border-radius: 12px; transition: all 0.3s; position: relative; border: 1px solid var(--border);"
                     onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.2)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    
                    <!-- Share Button -->
                    <button onclick="event.stopPropagation(); window.open('/event.php?id=<?= $event['id'] ?>', '_blank')" 
                            style="position: absolute; top: 16px; right: 16px; background: var(--accent); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; transition: all 0.3s; z-index: 10;"
                            onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 12px rgba(139,92,246,0.4)';"
                            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';"
                            title="Event teilen">
                        📤
                    </button>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div class="event-title" style="font-size: 1.5rem; font-weight: 800; padding-right: 48px;">
                            <?= htmlspecialchars($event['title']) ?>
                        </div>
                        <div class="event-meta" style="display: flex; flex-wrap: wrap; gap: 12px; color: var(--text-secondary); font-size: 0.875rem;">
                            <span>📅 <?= $event_date->format('d.m.Y') ?></span>
                            <span>🕐 <?= $event_date->format('H:i') ?> Uhr</span>
                            <?php if ($event['location']): ?>
                                <span>📍 <?= htmlspecialchars($event['location']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                            <div class="time-badge" style="padding: 8px 16px; background: var(--accent); color: white; border-radius: 8px; font-weight: 700; font-size: 0.875rem;">
                                <?= $time_info ?>
                            </div>
                            <?php if ($event['cost'] > 0): ?>
                                <div style="font-size: 1.25rem; font-weight: 700; color: #10b981;">
                                    <?= number_format($event['cost'], 2, ',', '.') ?> €
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Live Schichten Timeline (24h) -->
        <div class="section">
            <div style="background: var(--bg-tertiary); padding: 24px; border-radius: 12px;">
                <div class="section-header" style="margin-bottom: 24px;">
                    <span>🔴</span>
                    <h2 class="section-title">Aktive Schichten (LIVE)</h2>
                    <div id="currentTime" style="color: var(--text-secondary); font-size: 0.875rem;"></div>
                </div>
                
                <?php if (empty($next_24h_shifts)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        Aktuell keine aktiven Schichten
                    </div>
                <?php else: ?>
                    <!-- Timeline mit Stunden-Markierungen (Desktop) -->
                    <div class="timeline-container" style="position: relative; padding: 40px 0 20px 0;">
                        <!-- Zeitachse (horizontale Linie) -->
                        <div style="position: relative; height: 2px; background: linear-gradient(90deg, #ef4444 0%, rgba(139,92,246,0.3) 5%, rgba(139,92,246,0.3) 95%, rgba(139,92,246,0.1) 100%); margin: 40px 0;">
                            
                            <!-- Stunden-Marker (alle 2h) -->
                            <?php
                            $now = new DateTime();
                            $timeline_start = clone $now;
                            for ($h = 0; $h <= 24; $h += 2):
                                $marker_time = (clone $timeline_start)->modify("+{$h} hours");
                                $left_pos = ($h / 24) * 100;
                            ?>
                            <div style="position: absolute; left: <?= $left_pos ?>%; top: -8px; width: 2px; height: 18px; background: <?= $h == 0 ? '#ef4444' : 'rgba(139,92,246,0.4)' ?>;">
                                <div style="position: absolute; top: 24px; left: 50%; transform: translateX(-50%); font-size: 0.7rem; color: var(--text-secondary); white-space: nowrap; font-weight: <?= $h == 0 ? '700' : '400' ?>; color: <?= $h == 0 ? '#ef4444' : 'var(--text-secondary)' ?>;">
                                    <?= $marker_time->format('H:i') ?>
                                </div>
                            </div>
                            <?php endfor; ?>
                            
                            <!-- JETZT-Marker (pulsierend) -->
                            <div style="position: absolute; left: 0; top: -12px; width: 4px; height: 26px; background: #ef4444; z-index: 20; animation: pulse-marker 2s infinite; border-radius: 2px;">
                                <div style="position: absolute; top: -28px; left: 50%; transform: translateX(-50%); background: #ef4444; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; white-space: nowrap; box-shadow: 0 2px 8px rgba(239,68,68,0.4);">
                                    ● JETZT
                                </div>
                            </div>
                            
                            <!-- Schichten als Balken über der Timeline -->
                            <?php
                            $row_heights = []; // Track which rows are occupied
                            foreach($next_24h_shifts as $shift):
                                $shift_start = new DateTime($shift['date'] . ' ' . $shift['start_time']);
                                $shift_end = new DateTime($shift['date'] . ' ' . $shift['end_time']);
                                
                                // Berechne Position und Breite in %
                                $offset_minutes = ($shift_start->getTimestamp() - $timeline_start->getTimestamp()) / 60;
                                $duration_minutes = ($shift_end->getTimestamp() - $shift_start->getTimestamp()) / 60;
                                
                                // Wenn Schicht schon läuft, schneide den vergangenen Teil ab
                                if ($offset_minutes < 0) {
                                    $duration_minutes += $offset_minutes; // Reduziere Dauer um vergangene Zeit
                                    $offset_minutes = 0;
                                }
                                
                                $left_percent = ($offset_minutes / 1440) * 100;
                                $width_percent = ($duration_minutes / 1440) * 100;
                                
                                // Finde freie Reihe (vermeide Überlappungen)
                                $row = 0;
                                $shift_end_pos = $left_percent + $width_percent;
                                while (isset($row_heights[$row]) && $row_heights[$row] > $left_percent) {
                                    $row++;
                                }
                                $row_heights[$row] = $shift_end_pos;
                                
                                $top_offset = -80 - ($row * 55);
                                
                                // Farbe je nach Schichttyp
                                $bg_color = $shift['type'] === 'early' ? 'linear-gradient(135deg, #fbbf24, #f59e0b)' : 'linear-gradient(135deg, #8b5cf6, #7c3aed)';
                                $type_label = $shift['type'] === 'early' ? '🌅 Früh' : '🌙 Spät';
                            ?>
                            <div style="position: absolute; 
                                        left: <?= $left_percent ?>%; 
                                        width: <?= max(8, $width_percent) ?>%; 
                                        top: <?= $top_offset ?>px;
                                        height: 48px;
                                        background: <?= $bg_color ?>; 
                                        border-radius: 8px; 
                                        padding: 8px 12px; 
                                        color: white; 
                                        font-weight: 700; 
                                        font-size: 0.8rem; 
                                        display: flex; 
                                        flex-direction: column; 
                                        justify-content: center; 
                                        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                                        transition: all 0.3s;
                                        cursor: pointer;
                                        z-index: 5;"
                                 onmouseover="this.style.transform='translateY(-4px) scale(1.02)'; this.style.zIndex='15';"
                                 onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.zIndex='5';"
                                 title="<?= htmlspecialchars($shift['name']) ?> · <?= $type_label ?> · <?= $shift_start->format('H:i') ?>-<?= $shift_end->format('H:i') ?>">
                                <div style="font-size: 0.85rem; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars($shift['name']) ?>
                                </div>
                                <div style="font-size: 0.7rem; opacity: 0.95; margin-top: 2px;">
                                    <?= $type_label ?> · <?= $shift_start->format('H:i') ?>-<?= $shift_end->format('H:i') ?>
                                </div>
                                
                                <!-- Verbindungslinie zur Timeline -->
                                <div style="position: absolute; bottom: -<?= abs($top_offset) - 48 ?>px; left: 12px; width: 2px; height: <?= abs($top_offset) - 48 ?>px; background: linear-gradient(180deg, rgba(255,255,255,0.5), transparent); opacity: 0.4;"></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <style>
                    @keyframes pulse-marker {
                        0%, 100% { 
                            opacity: 1; 
                            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
                        }
                        50% { 
                            opacity: 1; 
                            box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
                        }
                    }
                    </style>
                    
                    <!-- Schichten-Liste (Mobile) -->
                    <div class="shifts-list-mobile" style="display: grid; gap: 12px;">
                        <?php foreach($next_24h_shifts as $shift): 
                            $shift_start = new DateTime($shift['date'] . ' ' . $shift['start_time']);
                            $shift_end = new DateTime($shift['date'] . ' ' . $shift['end_time']);
                            $type_label = $shift['type'] === 'early' ? '🌅 Frühschicht' : '🌙 Spätschicht';
                            $bg_color = $shift['type'] === 'early' ? '#f59e0b' : '#8b5cf6';
                        ?>
                        <div style="padding: 16px; background: var(--bg-secondary); border-radius: 8px;">
                            <div style="font-weight: 700; font-size: 1.125rem; margin-bottom: 8px;">
                                <?= htmlspecialchars($shift['name']) ?>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 4px; color: var(--text-secondary); font-size: 0.875rem;">
                                <div><?= $type_label ?></div>
                                <div>🕐 <?= $shift_start->format('H:i') ?> - <?= $shift_end->format('H:i') ?> Uhr</div>
                                <div>📅 <?= $shift_start->format('d.m.Y') ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kassen-Kurs Chart (30 Tage) -->
        <div class="section">
            <div style="background: var(--bg-tertiary); padding: 24px; border-radius: 12px;">
                <div class="section-header" style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span>📈</span>
                        <div>
                            <h2 class="section-title">Kassenkurs (30 Tage)</h2>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">$PUSHP · PUSHING P CREW</div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.75rem; font-weight: 900; color: var(--success);" id="paypalPool">€<?= number_format($stats['balance'], 2, ',', '.') ?></div>
                        <div style="font-size: 0.875rem; font-weight: 600; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                            <span id="changeArrow" style="font-size: 1.2rem; font-weight: 900; color: var(--success);">▲</span>
                            <span id="changePercent" style="color: var(--success);">+0.00%</span>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: center; gap: 8px; margin-bottom: 24px;">
                    <button class="timeframe-btn" data-days="1" style="padding: 8px 16px; background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">1T</button>
                    <button class="timeframe-btn" data-days="5" style="padding: 8px 16px; background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">5T</button>
                    <button class="timeframe-btn active" data-days="30" style="padding: 8px 16px; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">1M</button>
                    <button class="timeframe-btn" data-days="180" style="padding: 8px 16px; background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">6M</button>
                </div>
                
                <canvas id="kasseChart" style="width: 100%; height: 250px;"></canvas>
            </div>
        </div>

        <script>
        // Live-Update der aktuellen Zeit
        function updateCurrentTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateStr = now.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: 'long' });
            document.getElementById('currentTime').textContent = `${dateStr}, ${timeStr}`;
        }
        
        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);
        
        // Kassen-Chart laden
        let currentDays = 30; // Standard: 1 Monat
        
        async function loadKasseChart(days = currentDays) {
            try {
                const response = await fetch('/api/v2/get_kasse_chart.php?days=' + days);
                const result = await response.json();
                
                if (result.status === 'success') {
                    const data = result.data;
                    const stats = result.stats;
                    
                    // Update Stats - PayPal Pool bleibt statisch (PHP), nur Prozent + Pfeil updaten
                    const isPositive = stats.change >= 0;
                    const arrow = isPositive ? '▲' : '▼';
                    const arrowColor = isPositive ? 'var(--success)' : 'var(--error)';
                    const percentText = (isPositive ? '+' : '') + stats.change_percent.toFixed(2) + '%';
                    
                    document.getElementById('changeArrow').textContent = arrow;
                    document.getElementById('changeArrow').style.color = arrowColor;
                    document.getElementById('changePercent').textContent = percentText;
                    document.getElementById('changePercent').style.color = arrowColor;
                    
                    // Entferne alte Stats (wurden entfernt)
                    // document.getElementById('high30d').textContent = '€' + stats.high_30d.toFixed(2);
                    // document.getElementById('low30d').textContent = '€' + stats.low_30d.toFixed(2);
                    // document.getElementById('startBalance').textContent = '€' + stats.start_balance.toFixed(2);
                    
                    // Draw Chart
                    const canvas = document.getElementById('kasseChart');
                    if (!canvas) {
                        console.error('Canvas element not found!');
                        return;
                    }
                    
                    const ctx = canvas.getContext('2d');
                    
                    canvas.width = canvas.offsetWidth * 2;
                    canvas.height = 500;
                    
                    console.log('Canvas size:', canvas.width, 'x', canvas.height);
                    console.log('Data points:', data.length);
                    
                    const padding = 60;
                    const chartWidth = canvas.width - padding * 2;
                    const chartHeight = canvas.height - padding * 2;
                    
                    const balances = data.map(d => d.balance);
                    const minBalance = Math.min(...balances);
                    const maxBalance = Math.max(...balances);
                    const range = maxBalance - minBalance || 1;
                    
                    console.log('Balance range:', minBalance, '-', maxBalance);
                    
                    // Clear
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    // Grid
                    ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
                    ctx.lineWidth = 1;
                    for (let i = 0; i <= 5; i++) {
                        const y = padding + (chartHeight / 5) * i;
                        ctx.beginPath();
                        ctx.moveTo(padding, y);
                        ctx.lineTo(canvas.width - padding, y);
                        ctx.stroke();
                        
                        // Y-Achse Labels
                        const value = maxBalance - (range / 5) * i;
                        ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
                        ctx.font = '24px Inter';
                        ctx.textAlign = 'right';
                        ctx.fillText('€' + value.toFixed(0), padding - 10, y + 8);
                    }
                    
                    // Gradient Fill (isPositive bereits oben definiert)
                    const gradient = ctx.createLinearGradient(0, padding, 0, canvas.height - padding);
                    gradient.addColorStop(0, isPositive ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)');
                    gradient.addColorStop(1, isPositive ? 'rgba(16, 185, 129, 0)' : 'rgba(239, 68, 68, 0)');
                    
                    ctx.fillStyle = gradient;
                    ctx.beginPath();
                    ctx.moveTo(padding, canvas.height - padding);
                    
                    data.forEach((point, i) => {
                        const x = padding + (chartWidth / (data.length - 1)) * i;
                        const y = padding + chartHeight - ((point.balance - minBalance) / range) * chartHeight;
                        if (i === 0) ctx.lineTo(x, y);
                        else ctx.lineTo(x, y);
                    });
                    
                    ctx.lineTo(canvas.width - padding, canvas.height - padding);
                    ctx.closePath();
                    ctx.fill();
                    
                    // Line
                    ctx.strokeStyle = isPositive ? '#10b981' : '#ef4444';
                    ctx.lineWidth = 4;
                    ctx.beginPath();
                    
                    data.forEach((point, i) => {
                        const x = padding + (chartWidth / (data.length - 1)) * i;
                        const y = padding + chartHeight - ((point.balance - minBalance) / range) * chartHeight;
                        if (i === 0) ctx.moveTo(x, y);
                        else ctx.lineTo(x, y);
                    });
                    
                    ctx.stroke();
                    
                    // Current Price Dot
                    const lastX = canvas.width - padding;
                    const lastY = padding + chartHeight - ((stats.current - minBalance) / range) * chartHeight;
                    
                    ctx.fillStyle = isPositive ? '#10b981' : '#ef4444';
                    ctx.beginPath();
                    ctx.arc(lastX, lastY, 10, 0, Math.PI * 2);
                    ctx.fill();
                    
                    ctx.strokeStyle = isPositive ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)';
                    ctx.lineWidth = 3;
                    ctx.beginPath();
                    ctx.arc(lastX, lastY, 18, 0, Math.PI * 2);
                    ctx.stroke();
                    
                    // X-Axis Labels (every 5 days)
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
                    ctx.font = '20px Inter';
                    ctx.textAlign = 'center';
                    data.forEach((point, i) => {
                        if (i % 5 === 0 || i === data.length - 1) {
                            const x = padding + (chartWidth / (data.length - 1)) * i;
                            ctx.fillText(point.date, x, canvas.height - padding + 30);
                        }
                    });
                }
            } catch (error) {
                console.error('Fehler beim Laden des Kassen-Charts:', error);
            }
        }
        
        // Timeframe Buttons
        document.querySelectorAll('.timeframe-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all
                document.querySelectorAll('.timeframe-btn').forEach(b => {
                    b.classList.remove('active');
                    b.style.background = 'var(--bg-tertiary)';
                    b.style.color = 'var(--text-secondary)';
                    b.style.border = '1px solid var(--border)';
                });
                
                // Add active to clicked
                this.classList.add('active');
                this.style.background = 'var(--accent)';
                this.style.color = 'white';
                this.style.border = 'none';
                
                // Load chart with new timeframe
                const days = parseInt(this.getAttribute('data-days'));
                currentDays = days;
                loadKasseChart(days);
            });
        });
        
        loadKasseChart(30); // Start mit 1 Monat
        </script>

        <!-- Crew Members Section -->
        <div id="crew-members" class="section">
            <div style="background: var(--bg-tertiary); padding: 24px; border-radius: 12px;">
                <div class="section-header" style="margin-bottom: 24px;">
                    <span>👥</span>
                    <h2 class="section-title">Aktive Crew Members</h2>
                    <span style="color: var(--text-secondary); font-size: 0.875rem;"><?= count($crew_members) ?> Mitglieder</span>
                </div>
                
                <div class="crew-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 16px;">
                <?php 
                $colors = ['#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#14b8a6', '#f97316'];
                $color_index = 0;
                foreach ($crew_members as $member): 
                    $member_color = $colors[$color_index % count($colors)];
                    $color_index++;
                    
                    // Get XP info for member
                    $member_xp = get_user_level_info($member['id']);
                    
                    // Check if member has active shift NOW
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) 
                        FROM shifts 
                        WHERE user_id = ? 
                        AND date = CURDATE()
                        AND CONCAT(date, ' ', start_time) <= NOW()
                        AND CONCAT(date, ' ', end_time) >= NOW()
                        AND type != 'free'
                    ");
                    $stmt->bind_param('i', $member['id']);
                    $stmt->execute();
                    $stmt->bind_result($has_active_shift);
                    $stmt->fetch();
                    $stmt->close();
                    
                    // Avatar-Initialen
                    $name_parts = explode(' ', $member['name']);
                    $initials = '';
                    if (count($name_parts) >= 2) {
                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($member['name'], 0, 2));
                    }
                    
                    // Member seit
                    $joined = new DateTime($member['created_at']);
                    $now = new DateTime();
                    $member_diff = $joined->diff($now);
                    if ($member_diff->y > 0) {
                        $member_since = $member_diff->y . ' Jahr' . ($member_diff->y > 1 ? 'e' : '');
                    } elseif ($member_diff->m > 0) {
                        $member_since = $member_diff->m . ' Monat' . ($member_diff->m > 1 ? 'e' : '');
                    } else {
                        $member_since = 'Neu';
                    }
                    
                    // Prepare member data as JSON for modal
                    $member_data = [
                        'id' => $member['id'],
                        'name' => $member['name'],
                        'username' => $member['username'],
                        'initials' => $initials,
                        'member_since' => $member_since,
                        'color' => $member_color,
                        'level_id' => $member_xp['level_id'] ?? 1,
                        'level_title' => $member_xp['current_level'] ?? 'Rookie',
                        'level_image' => $member_xp['level_image'] ?? '',
                        'xp_total' => $member_xp['xp_total'] ?? 0,
                        'has_shift' => $has_active_shift > 0
                    ];
                ?>
                <div class="crew-member-card" 
                     data-member='<?= json_encode($member_data) ?>'
                     onclick="openMemberModal(this)"
                     style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; display: flex; align-items: center; gap: 16px; transition: all 0.3s; position: relative; overflow: hidden; cursor: pointer;"
                     onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.2)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    
                    <!-- Gradient Overlay -->
                    <div style="position: absolute; top: 0; right: 0; width: 100px; height: 100%; background: linear-gradient(90deg, transparent, <?= $member_color ?>15); pointer-events: none;"></div>
                    
                    <!-- Avatar -->
                    <div class="crew-avatar" style="width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, <?= $member_color ?>, <?= $member_color ?>CC); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; color: white; flex-shrink: 0; box-shadow: 0 4px 12px <?= $member_color ?>40;">
                        <?= $initials ?>
                    </div>
                    
                    <!-- Info -->
                    <div style="flex: 1; min-width: 0;">
                        <div class="crew-name" style="font-weight: 700; font-size: 1.125rem; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($member['name']) ?>
                        </div>
                        <div class="crew-meta" style="color: var(--text-secondary); font-size: 0.875rem; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                            <span>@<?= htmlspecialchars($member['username']) ?></span>
                            <span style="color: <?= $member_color ?>;">•</span>
                            <span><?= $member_since ?></span>
                        </div>
                    </div>
                    
                    <!-- Status Badge - Green nur wenn KEINE Schicht -->
                    <?php if ($has_active_shift > 0): ?>
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 0 3px #ef444433; flex-shrink: 0;" title="Im Dienst"></div>
                    <?php else: ?>
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 3px #10b98133; flex-shrink: 0;" title="Verfügbar"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            </div>
        </div>
        
    </div> <!-- End Container -->

    <!-- Simple Member Modal -->
    <div id="memberModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;" onclick="if(event.target.id === 'memberModal') closeMemberModal()">
        <div style="background: var(--bg-primary); border-radius: 20px; max-width: 500px; width: 90%; position: relative; padding: 40px;">
            <button onclick="closeMemberModal()" style="position: absolute; top: 20px; right: 20px; background: var(--bg-tertiary); border: none; color: var(--text-primary); width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                    onmouseover="this.style.background='var(--accent)'; this.style.transform='rotate(90deg)';"
                    onmouseout="this.style.background='var(--bg-tertiary)'; this.style.transform='rotate(0)';">
                ×
            </button>

            <div style="text-align: center; margin-bottom: 32px;">
                <div id="modalAvatar" style="width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: white;"></div>
                <h2 id="modalName" style="font-size: 2rem; font-weight: 800; margin-bottom: 8px;"></h2>
                <div id="modalUsername" style="color: var(--text-secondary); font-size: 1rem;">@username</div>
            </div>

            <div style="background: var(--bg-secondary); padding: 24px; border-radius: 16px; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <img id="modalLevelImage" src="" alt="Level" style="width: 60px; height: 60px; object-fit: contain;">
                    <div style="flex: 1;">
                        <div id="modalLevelTitle" style="font-size: 1.25rem; font-weight: 800; margin-bottom: 4px;"></div>
                        <div id="modalXP" style="color: var(--text-secondary); font-size: 0.875rem;"></div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 24px;">
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; text-align: center;">
                    <div id="modalTotalXP" style="font-size: 1.75rem; font-weight: 800; color: var(--accent);">0</div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px;">Total XP</div>
                </div>
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; text-align: center;">
                    <div id="modalMemberSince" style="font-size: 1.75rem; font-weight: 800; color: #10b981;"></div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px;">Mitglied seit</div>
                </div>
            </div>

            <button id="modalChatBtn" onclick="startChat()" style="width: 100%; padding: 16px; background: var(--accent); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(139,92,246,0.4)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                💬 Chat starten
            </button>
        </div>
    </div>

    <script>
    let currentMemberId = null;

    function openMemberModal(cardElement) {
        const memberData = JSON.parse(cardElement.getAttribute('data-member'));
        currentMemberId = memberData.id;
        
        // Set modal data
        document.getElementById('modalAvatar').style.background = `linear-gradient(135deg, ${memberData.color}, ${memberData.color}CC)`;
        document.getElementById('modalAvatar').textContent = memberData.initials;
        document.getElementById('modalName').textContent = memberData.name;
        document.getElementById('modalUsername').textContent = '@' + memberData.username;
        
        if (memberData.level_image) {
            document.getElementById('modalLevelImage').src = memberData.level_image;
        }
        document.getElementById('modalLevelTitle').textContent = `Level ${memberData.level_id} – ${memberData.level_title}`;
        document.getElementById('modalXP').textContent = `${memberData.xp_total.toLocaleString('de-DE')} XP`;
        document.getElementById('modalTotalXP').textContent = memberData.xp_total.toLocaleString('de-DE');
        document.getElementById('modalMemberSince').textContent = memberData.member_since;
        
        // Show modal
        document.getElementById('memberModal').style.display = 'flex';
    }

    function closeMemberModal() {
        document.getElementById('memberModal').style.display = 'none';
        currentMemberId = null;
    }

    function startChat() {
        if (currentMemberId) {
            window.location.href = `/chat.php#user-${currentMemberId}`;
        }
    }

    // Scroll to Crew Members section
    function scrollToCrewMembers(event) {
        event.preventDefault();
        const crewSection = document.getElementById('crew-members');
        if (crewSection) {
            crewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Save and restore scroll position on page reload
    window.addEventListener('beforeunload', function() {
        sessionStorage.setItem('dashboardScrollY', window.scrollY);
    });

    window.addEventListener('load', function() {
        const savedScrollY = sessionStorage.getItem('dashboardScrollY');
        if (savedScrollY) {
            window.scrollTo(0, parseInt(savedScrollY));
            // Clear after restore so it doesn't persist across different page visits
            sessionStorage.removeItem('dashboardScrollY');
        }
    });
    
    // Casino Multiplayer Badge Update
    async function updateCasinoMultiplayerBadge() {
        try {
            const response = await fetch('/api/casino/get_multiplayer_status.php');
            const data = await response.json();
            
            if (data.status === 'success') {
                const badge = document.getElementById('casinoMultiplayerBadge');
                if (badge) {
                    if (data.available_tables > 0) {
                        badge.textContent = data.available_tables;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Failed to update casino badge:', error);
        }
    }
    
    // Update badge every 5 seconds
    updateCasinoMultiplayerBadge();
    setInterval(updateCasinoMultiplayerBadge, 5000);
    </script>
</body>
</html>

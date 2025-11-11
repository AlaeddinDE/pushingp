<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin = is_admin();
$is_admin_user = $is_admin;
$page_title = 'Schichtplan';

// Aktuelles Datum und Woche
$current_date = new DateTime();
$week_offset = isset($_GET['week']) ? intval($_GET['week']) : 0;
$current_date->modify("$week_offset week");

// Start der Woche (Montag)
$week_start = clone $current_date;
$week_start->modify('monday this week');

// Hole alle aktiven Mitglieder MIT aktivierten Schichten
$members_query = "SELECT id, name FROM users WHERE status = 'active' AND shift_enabled = 1 ORDER BY shift_sort_order ASC, name ASC";
$members_result = $conn->query($members_query);
$members = [];
while ($row = $members_result->fetch_assoc()) {
    $members[] = $row;
}

// Hole Schichten für diese Woche
$week_end = clone $week_start;
$week_end->modify('+6 days');

$shifts_query = "
    SELECT s.*, u.name as user_name
    FROM shifts s
    JOIN users u ON u.id = s.user_id
    WHERE s.date BETWEEN ? AND ?
    AND u.status = 'active'
    ORDER BY s.date ASC, s.start_time ASC
";
$stmt = $conn->prepare($shifts_query);
$start_str = $week_start->format('Y-m-d');
$end_str = $week_end->format('Y-m-d');
$stmt->bind_param('ss', $start_str, $end_str);
$stmt->execute();
$shifts_result = $stmt->get_result();

// Organisiere Schichten nach User und Datum
$shifts_by_user = [];
while ($shift = $shifts_result->fetch_assoc()) {
    $user_id_key = $shift['user_id'];
    $date_key = $shift['date'];
    if (!isset($shifts_by_user[$user_id_key])) {
        $shifts_by_user[$user_id_key] = [];
    }
    if (!isset($shifts_by_user[$user_id_key][$date_key])) {
        $shifts_by_user[$user_id_key][$date_key] = [];
    }
    $shifts_by_user[$user_id_key][$date_key][] = $shift;
}
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>
    <style>
        body {
            background: var(--bg-primary);
            overflow-x: hidden;
        }
        
        .week-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            margin: 32px 0 24px;
        }
        
        .week-nav-btn {
            background: var(--bg-tertiary);
            border: 2px solid var(--border);
            color: var(--text-primary);
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .week-nav-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--accent);
            transform: scale(1.05);
        }
        
        .current-week {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 700;
            min-width: 250px;
            text-align: center;
        }
        
        .calendar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 0 32px;
        }
        
        .calendar-grid {
            background: var(--bg-secondary);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
        }
        
        .calendar-header {
            display: grid;
            grid-template-columns: 150px repeat(7, 1fr);
            background: var(--bg-tertiary);
            border-bottom: 2px solid var(--border);
            gap: 1px;
        }
        
        .day-header {
            padding: 16px 8px;
            text-align: center;
            font-weight: 700;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .day-header.today {
            background: var(--accent);
            color: white;
            border-radius: 8px;
            margin: 4px;
        }
        
        .day-header .day-name {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .day-header .day-date {
            display: block;
            font-size: 1.25rem;
            margin-top: 4px;
        }
        
        .member-header {
            padding: 16px;
            font-weight: 700;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .calendar-row {
            display: grid;
            grid-template-columns: 150px repeat(7, 1fr);
            border-bottom: 1px solid var(--border);
            min-height: 80px;
            gap: 1px;
            background: var(--border);
        }
        
        .calendar-row:hover {
            background: rgba(139, 92, 246, 0.1);
        }
        
        .member-cell {
            padding: 16px;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            color: var(--text-primary);
            position: sticky;
            left: 0;
            z-index: 10;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #ec4899);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 0.875rem;
        }
        
        .shift-cell {
            padding: 8px;
            background: var(--bg-secondary);
            display: flex;
            flex-direction: column;
            gap: 4px;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .shift-cell:hover {
            background: var(--bg-tertiary);
        }
        
        .shift-item {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            transition: all 0.2s;
        }
        
        .shift-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .shift-item.free {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
        .shift-item.morning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .shift-item.late {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .shift-item.night {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }
        
        .shift-time {
            font-size: 0.7rem;
            opacity: 0.9;
        }
        
        .empty-cell {
            color: var(--text-tertiary);
            font-size: 0.75rem;
            text-align: center;
            padding: 16px 8px;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin: 24px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
        }
        
        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 6px;
        }
        
        .fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
            transition: all 0.3s;
            z-index: 100;
        }
        
        .fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 32px rgba(139, 92, 246, 0.6);
        }
        
        /* Mobile Optimierung */
        @media (max-width: 768px) {
            .schicht-header h1 {
                font-size: 1.5rem;
            }
            
            .week-nav {
                flex-direction: column;
                gap: 12px;
            }
            
            .current-week {
                font-size: 1rem;
                min-width: auto;
            }
            
            .calendar-header,
            .calendar-row {
                grid-template-columns: 100px repeat(7, 80px);
            }
            
            .member-cell {
                padding: 12px 8px;
            }
            
            .member-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.75rem;
            }
            
            .day-header {
                padding: 12px 4px;
                font-size: 0.7rem;
            }
            
            .day-header .day-date {
                font-size: 1rem;
            }
            
            .shift-item {
                padding: 6px;
                font-size: 0.65rem;
            }
            
            .shift-time {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .calendar-header,
            .calendar-row {
                grid-template-columns: 80px repeat(7, 60px);
            }
            
            .member-cell span {
                font-size: 0.75rem;
            }
            
            .day-header .day-name {
                font-size: 0.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <div class="logo">PUSHING P</div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-item">Dashboard</a>
                <a href="schichten.php" class="nav-item active">Schichten</a>
                <?php if ($is_admin): ?>
                <a href="admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>📅 Schichtplan</h1>
            <p class="text-secondary">Wochenübersicht aller Schichten</p>
        </div>

        <div class="week-nav">
            <a href="?week=<?= $week_offset - 1 ?>" class="week-nav-btn">‹ Vorherige</a>
            <div class="current-week">
                <?= $week_start->format('d.m.Y') ?> - <?= $week_end->format('d.m.Y') ?>
            </div>
            <a href="?week=<?= $week_offset + 1 ?>" class="week-nav-btn">Nächste ›</a>
        </div>
        <div style="text-align: center; margin-bottom: 24px;">
            <a href="?week=0" class="week-nav-btn" style="font-size: 0.875rem;">🎯 Aktuelle Woche</a>
        </div>
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                <span>🌅 Früh (06-14)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #3b82f6, #2563eb);"></div>
                <span>🌆 Spät (14-22)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #6366f1, #4f46e5);"></div>
                <span>🌙 Nacht (22-06)</span>
            </div>
        </div>

        <div class="calendar-grid">
            <div class="calendar-header">
                <div class="member-header">👥 Mitglied</div>
                <?php
                $today = new DateTime();
                for ($i = 0; $i < 7; $i++):
                    $day = clone $week_start;
                    $day->modify("+$i day");
                    $is_today = $day->format('Y-m-d') === $today->format('Y-m-d');
                    $day_names = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
                ?>
                <div class="day-header <?= $is_today ? 'today' : '' ?>">
                    <span class="day-name"><?= $day_names[$i] ?></span>
                    <span class="day-date"><?= $day->format('d') ?></span>
                </div>
                <?php endfor; ?>
            </div>

            <?php foreach ($members as $member):
                $initials = '';
                $name_parts = explode(' ', $member['name']);
                if (count($name_parts) >= 2) {
                    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                } else {
                    $initials = strtoupper(substr($member['name'], 0, 2));
                }
            ?>
            <div class="calendar-row">
                <div class="member-cell">
                    <div class="member-avatar"><?= $initials ?></div>
                    <span><?= htmlspecialchars($member['name']) ?></span>
                </div>
                
                <?php for ($i = 0; $i < 7; $i++):
                    $day = clone $week_start;
                    $day->modify("+$i day");
                    $date_key = $day->format('Y-m-d');
                    $user_shifts = $shifts_by_user[$member['id']][$date_key] ?? [];
                ?>
                <div class="shift-cell" onclick="editShift(<?= $member['id'] ?>, '<?= $date_key ?>')">
                    <?php if (empty($user_shifts)): ?>
                        <div class="empty-cell">-</div>
                    <?php else: ?>
                        <?php foreach ($user_shifts as $shift):
                            // Skip "free" shifts - sie werden nicht angezeigt
                            if ($shift['type'] === 'free') {
                                continue;
                            }
                            
                            $shift_class = '';
                            $shift_icon = '🕐';
                            
                            // Schichttyp basierend auf Startzeit erkennen
                            $start_hour = (int)substr($shift['start_time'], 0, 2);
                            
                            if ($start_hour >= 6 && $start_hour < 14) {
                                // Frühschicht: 06:00 - 13:59
                                $shift_class = 'morning';
                                $shift_icon = '🌅';
                            } elseif ($start_hour >= 14 && $start_hour < 22) {
                                // Spätschicht: 14:00 - 21:59
                                $shift_class = 'late';
                                $shift_icon = '🌆';
                            } else {
                                // Nachtschicht: 22:00 - 05:59
                                $shift_class = 'night';
                                $shift_icon = '🌙';
                            }
                            
                            $time_str = substr($shift['start_time'], 0, 5) . '-' . substr($shift['end_time'], 0, 5);
                        ?>
                        <div class="shift-item <?= $shift_class ?>">
                            <span><?= $shift_icon ?></span>
                            <span class="shift-time"><?= $time_str ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <button class="fab" onclick="window.location.href='schichten_bearbeiten.php'" title="Schichten bearbeiten">+</button>
    <?php endif; ?>

    <script>
    function editShift(userId, date) {
        <?php if ($is_admin): ?>
        window.location.href = 'schichten_bearbeiten.php?user=' + userId + '&date=' + date;
        <?php endif; ?>
    }
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$is_admin = is_admin();

// Aktueller Monat oder aus GET
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Events holen
$events_query = "
    SELECT e.*, 
           COUNT(DISTINCT ep.mitglied_id) as teilnehmer_count,
           SUM(CASE WHEN ep.status = 'zugesagt' THEN 1 ELSE 0 END) as zugesagt_count,
           SUM(CASE WHEN ep.status = 'abgesagt' THEN 1 ELSE 0 END) as abgesagt_count,
           (SELECT status FROM event_participants WHERE event_id = e.id AND mitglied_id = ?) as my_status
    FROM events e
    LEFT JOIN event_participants ep ON ep.event_id = e.id
    WHERE e.event_status = 'active'
    AND YEAR(e.datum) = ?
    AND MONTH(e.datum) = ?
    GROUP BY e.id
    ORDER BY e.datum ASC, e.start_time ASC
";

$stmt = $conn->prepare($events_query);
$stmt->bind_param('iii', $user_id, $current_year, $current_month);
$stmt->execute();
$events_result = $stmt->get_result();
$events = [];
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

// Alle kommenden Events (nächste 6 Monate)
$upcoming_query = "
    SELECT e.*, 
           COUNT(DISTINCT ep.mitglied_id) as teilnehmer_count,
           SUM(CASE WHEN ep.status = 'zugesagt' THEN 1 ELSE 0 END) as zugesagt_count,
           (SELECT status FROM event_participants WHERE event_id = e.id AND mitglied_id = ?) as my_status
    FROM events e
    LEFT JOIN event_participants ep ON ep.event_id = e.id
    WHERE e.event_status = 'active'
    AND e.datum >= CURDATE()
    GROUP BY e.id
    ORDER BY e.datum ASC, e.start_time ASC
    LIMIT 20
";

$stmt = $conn->prepare($upcoming_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$upcoming_result = $stmt->get_result();
$upcoming_events = [];
while ($row = $upcoming_result->fetch_assoc()) {
    $upcoming_events[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎉 Events – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .events-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .view-toggle {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 24px 0;
        }
        
        .toggle-btn {
            background: var(--bg-tertiary);
            border: 2px solid var(--border);
            color: var(--text-secondary);
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .toggle-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        
        .toggle-btn:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        /* Timeline View */
        .timeline-view {
            display: none;
            position: relative;
            padding: 0 32px;
        }
        
        .timeline-view.active {
            display: block;
        }
        
        .timeline-line {
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, var(--accent), transparent);
        }
        
        .timeline-item {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 32px;
            margin-bottom: 48px;
            position: relative;
        }
        
        .timeline-item.left .event-card {
            grid-column: 1;
        }
        
        .timeline-item.left .timeline-date {
            grid-column: 2;
        }
        
        .timeline-item.right .event-card {
            grid-column: 3;
        }
        
        .timeline-item.right .timeline-date {
            grid-column: 2;
        }
        
        .timeline-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            position: relative;
            z-index: 10;
        }
        
        .timeline-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--accent);
            border: 4px solid var(--bg-primary);
            box-shadow: 0 0 0 4px var(--accent-glow);
            animation: pulse 2s infinite;
        }
        
        .timeline-month {
            margin-top: 12px;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-align: center;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.7);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(139, 92, 246, 0);
            }
        }
        
        /* Grid View */
        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .grid-view.active {
            display: grid;
        }
        
        /* Event Cards */
        .event-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), #ec4899);
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        
        .event-card:hover::before {
            transform: scaleX(1);
        }
        
        .event-card:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.2);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .event-status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-zugesagt {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-abgesagt {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .status-offen {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 2px solid var(--border);
        }
        
        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .meta-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .event-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 20px 0;
            padding: 16px;
            background: var(--bg-tertiary);
            border-radius: 12px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-top: 4px;
        }
        
        .event-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn-event {
            flex: 1;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-join {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        
        .btn-leave {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-leave:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }
        
        .btn-details {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 2px solid var(--border);
        }
        
        .btn-details:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .time-until {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(139, 92, 246, 0.1);
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 8px;
            color: var(--accent);
            font-weight: 700;
            font-size: 0.875rem;
            margin-top: 12px;
        }
        
        .no-events {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }
        
        .no-events-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .timeline-view {
                padding: 0;
            }
            
            .timeline-item {
                grid-template-columns: auto 1fr;
                gap: 16px;
            }
            
            .timeline-item.left .event-card,
            .timeline-item.right .event-card {
                grid-column: 2;
            }
            
            .timeline-date {
                min-width: 80px;
            }
            
            .timeline-line {
                left: 40px;
            }
            
            .grid-view {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .event-card {
                padding: 20px;
            }
            
            .event-stats {
                grid-template-columns: repeat(2, 1fr);
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
                <a href="events.php" class="nav-item active">Events</a>
                <?php if ($is_admin): ?>
                <a href="admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>🎉 Events</h1>
            <p class="text-secondary">Alle kommenden Events und Termine</p>
        </div>

        <div class="view-toggle">
            <button class="toggle-btn active" onclick="switchView('timeline')">📅 Timeline</button>
            <button class="toggle-btn" onclick="switchView('grid')">🔲 Kacheln</button>
        </div>

        <div class="events-container">
            <!-- Timeline View -->
            <div class="timeline-view active" id="timelineView">
                <div class="timeline-line"></div>
                
                <?php if (empty($upcoming_events)): ?>
                <div class="no-events">
                    <div class="no-events-icon">📅</div>
                    <h3>Keine kommenden Events</h3>
                    <p style="margin-top: 8px;">Es sind aktuell keine Events geplant.</p>
                </div>
                <?php else: ?>
                    <?php 
                    $side = 'left';
                    foreach ($upcoming_events as $event): 
                        $event_date = new DateTime($event['datum'] . ' ' . ($event['start_time'] ?? '00:00:00'));
                        $now = new DateTime();
                        $diff = $now->diff($event_date);
                        
                        if ($diff->days == 0 && $diff->h < 24) {
                            $time_until = '🔥 Heute!';
                        } elseif ($diff->days == 1) {
                            $time_until = '⚡ Morgen';
                        } elseif ($diff->days <= 7) {
                            $time_until = 'In ' . $diff->days . ' Tagen';
                        } else {
                            $time_until = $event_date->format('d.m.Y');
                        }
                    ?>
                    <div class="timeline-item <?= $side ?>">
                        <div class="timeline-date">
                            <div class="timeline-dot"></div>
                            <div class="timeline-month">
                                <?= $event_date->format('M Y') ?>
                            </div>
                        </div>
                        
                        <div class="event-card">
                            <div class="event-header">
                                <div>
                                    <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                                    <?php if ($event['my_status']): ?>
                                        <span class="event-status-badge status-<?= $event['my_status'] ?>">
                                            <?= $event['my_status'] === 'zugesagt' ? '✓ Zugesagt' : '✗ Abgesagt' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="event-status-badge status-offen">⏱ Offen</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="event-meta">
                                <div class="meta-item">
                                    <div class="meta-icon">📅</div>
                                    <span><?= $event_date->format('d.m.Y') ?></span>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-icon">🕐</div>
                                    <span><?= $event_date->format('H:i') ?> Uhr</span>
                                </div>
                                <?php if ($event['location']): ?>
                                <div class="meta-item">
                                    <div class="meta-icon">📍</div>
                                    <span><?= htmlspecialchars($event['location']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($event['cost'] > 0): ?>
                                <div class="meta-item">
                                    <div class="meta-icon">💰</div>
                                    <span><?= number_format($event['cost'], 2, ',', '.') ?> €</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="time-until"><?= $time_until ?></div>
                            
                            <div class="event-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $event['zugesagt_count'] ?></div>
                                    <div class="stat-label">Zugesagt</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= $event['teilnehmer_count'] ?></div>
                                    <div class="stat-label">Gesamt</div>
                                </div>
                            </div>
                            
                            <div class="event-actions">
                                <?php if ($event['my_status'] !== 'zugesagt'): ?>
                                <button class="btn-event btn-join" onclick="respondEvent(<?= $event['id'] ?>, 'zugesagt')">
                                    ✓ Zusagen
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($event['my_status'] !== 'abgesagt'): ?>
                                <button class="btn-event btn-leave" onclick="respondEvent(<?= $event['id'] ?>, 'abgesagt')">
                                    ✗ Absagen
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn-event btn-details" onclick="window.open('/event.php?id=<?= $event['id'] ?>', '_blank')">
                                    👁️ Details
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php 
                        $side = ($side === 'left') ? 'right' : 'left';
                    endforeach; 
                    ?>
                <?php endif; ?>
            </div>

            <!-- Grid View -->
            <div class="grid-view" id="gridView">
                <?php if (empty($upcoming_events)): ?>
                <div class="no-events">
                    <div class="no-events-icon">📅</div>
                    <h3>Keine kommenden Events</h3>
                    <p style="margin-top: 8px;">Es sind aktuell keine Events geplant.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($upcoming_events as $event): 
                        $event_date = new DateTime($event['datum'] . ' ' . ($event['start_time'] ?? '00:00:00'));
                        $now = new DateTime();
                        $diff = $now->diff($event_date);
                        
                        if ($diff->days == 0 && $diff->h < 24) {
                            $time_until = '🔥 Heute!';
                        } elseif ($diff->days == 1) {
                            $time_until = '⚡ Morgen';
                        } elseif ($diff->days <= 7) {
                            $time_until = 'In ' . $diff->days . ' Tagen';
                        } else {
                            $time_until = $event_date->format('d.m.Y');
                        }
                    ?>
                    <div class="event-card">
                        <div class="event-header">
                            <div>
                                <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                                <?php if ($event['my_status']): ?>
                                    <span class="event-status-badge status-<?= $event['my_status'] ?>">
                                        <?= $event['my_status'] === 'zugesagt' ? '✓ Zugesagt' : '✗ Abgesagt' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="event-status-badge status-offen">⏱ Offen</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="event-meta">
                            <div class="meta-item">
                                <div class="meta-icon">📅</div>
                                <span><?= $event_date->format('d.m.Y') ?></span>
                            </div>
                            <div class="meta-item">
                                <div class="meta-icon">🕐</div>
                                <span><?= $event_date->format('H:i') ?> Uhr</span>
                            </div>
                            <?php if ($event['location']): ?>
                            <div class="meta-item">
                                <div class="meta-icon">📍</div>
                                <span><?= htmlspecialchars($event['location']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($event['cost'] > 0): ?>
                            <div class="meta-item">
                                <div class="meta-icon">💰</div>
                                <span><?= number_format($event['cost'], 2, ',', '.') ?> €</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="time-until"><?= $time_until ?></div>
                        
                        <div class="event-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= $event['zugesagt_count'] ?></div>
                                <div class="stat-label">Zugesagt</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $event['teilnehmer_count'] ?></div>
                                <div class="stat-label">Gesamt</div>
                            </div>
                        </div>
                        
                        <div class="event-actions">
                            <?php if ($event['my_status'] !== 'zugesagt'): ?>
                            <button class="btn-event btn-join" onclick="respondEvent(<?= $event['id'] ?>, 'zugesagt')">
                                ✓ Zusagen
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($event['my_status'] !== 'abgesagt'): ?>
                            <button class="btn-event btn-leave" onclick="respondEvent(<?= $event['id'] ?>, 'abgesagt')">
                                ✗ Absagen
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn-event btn-details" onclick="window.open('/event.php?id=<?= $event['id'] ?>', '_blank')">
                                👁️ Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function switchView(view) {
        const timelineView = document.getElementById('timelineView');
        const gridView = document.getElementById('gridView');
        const buttons = document.querySelectorAll('.toggle-btn');
        
        buttons.forEach(btn => btn.classList.remove('active'));
        
        if (view === 'timeline') {
            timelineView.classList.add('active');
            gridView.classList.remove('active');
            buttons[0].classList.add('active');
        } else {
            gridView.classList.add('active');
            timelineView.classList.remove('active');
            buttons[1].classList.add('active');
        }
    }
    
    async function respondEvent(eventId, status) {
        try {
            const response = await fetch('/api/event_respond.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_id: eventId, status: status })
            });
            
            const result = await response.json();
            if (result.status === 'success') {
                location.reload();
            } else {
                alert('Fehler: ' + (result.error || 'Unbekannter Fehler'));
            }
        } catch (error) {
            alert('Verbindungsfehler: ' + error.message);
        }
    }
    </script>
</body>
</html>

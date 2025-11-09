<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin_user = is_admin();

// Alle AKTIVEN Schichten (egal wann gestartet)
$next_24h_shifts = [];
$result = $conn->query("
    SELECT 
        s.mitglied_id,
        s.startzeit,
        s.aktiv,
        u.name,
        u.id
    FROM schichten s
    JOIN users u ON u.id = s.mitglied_id
    WHERE s.aktiv = 1
    ORDER BY u.name ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $next_24h_shifts[] = $row;
    }
}

$stats = [];
$result = $conn->query("SELECT kassenstand_verfuegbar FROM v_kasse_position");
if ($result && $row = $result->fetch_assoc()) $stats['balance'] = floatval($row['kassenstand_verfuegbar']);
$result = $conn->query("SELECT COUNT(*) as cnt FROM events WHERE event_status = 'active' AND start_time >= NOW()");
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
$result = $conn->query("SELECT id, title, start_time, location, cost FROM events WHERE event_status = 'active' AND start_time >= NOW() ORDER BY start_time ASC LIMIT 5");
if ($result) while ($row = $result->fetch_assoc()) $next_events[] = $row;
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
        
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
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
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <?php if ($is_admin_user): ?>
                    <a href="admin_kasse.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>
                Willkommen, <?= escape($name) ?>
                <?php if ($is_admin_user): ?>
                    <span class="badge">Admin</span>
                <?php endif; ?>
            </h1>
            <p class="text-secondary">Hier ist deine Übersicht</p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <div class="stat-value"><?= number_format($stats['balance'] ?? 0, 2, ',', '.') ?> €</div>
                <div class="stat-label">Kassenstand</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🎉</span>
                <div class="stat-value"><?= $stats['events'] ?? 0 ?></div>
                <div class="stat-label">Kommende Events</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">👥</span>
                <div class="stat-value"><?= $stats['members'] ?? 0 ?></div>
                <div class="stat-label">Crew Members</div>
            </div>
        </div>

        <!-- Live Schichten Timeline (24h) -->
        <div class="section" style="margin-bottom: 32px;">
            <div class="section-header">
                <span>🔴</span>
                <h2 class="section-title">Aktive Schichten (LIVE)</h2>
                <div id="currentTime" style="color: var(--text-secondary); font-size: 0.875rem;"></div>
            </div>
            
            <div style="background: var(--bg-tertiary); padding: 24px; border-radius: 12px;">
                <?php if (empty($next_24h_shifts)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        Aktuell keine aktiven Schichten
                    </div>
                <?php else: ?>
                    <!-- Aktive Schichten als Karten -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <?php 
                        $colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                        $color_index = 0;
                        foreach($next_24h_shifts as $shift): 
                            $shift_color = $colors[$color_index % count($colors)];
                            $color_index++;
                            $shift_start = new DateTime($shift['startzeit']);
                            $now = new DateTime();
                            $diff = $now->diff($shift_start);
                            
                            if ($shift_start > $now) {
                                $duration_text = "startet in " . ($diff->days * 24 + $diff->h) . "h " . $diff->i . "min";
                            } else {
                                $duration_text = "seit " . ($diff->days * 24 + $diff->h) . "h " . $diff->i . "min";
                            }
                        ?>
                        <div style="background: <?= $shift_color ?>; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.2); position: relative; overflow: hidden;">
                            <!-- Pulse Animation -->
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.1); animation: pulse 2s infinite;"></div>
                            
                            <div style="position: relative; z-index: 1;">
                                <div style="font-size: 2rem; margin-bottom: 8px;">🔴</div>
                                <div style="color: white; font-weight: 700; font-size: 1.25rem; margin-bottom: 4px;">
                                    <?= htmlspecialchars($shift['name']) ?>
                                </div>
                                <div style="color: rgba(255,255,255,0.9); font-size: 0.875rem; margin-bottom: 8px;">
                                    seit <?= $shift_start->format('d.m.Y H:i') ?> Uhr
                                </div>
                                <div style="background: rgba(0,0,0,0.2); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; display: inline-block;">
                                    <?= $duration_text ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <style>
                    @keyframes pulse {
                        0%, 100% { opacity: 0.3; }
                        50% { opacity: 0.6; }
                    }
                    </style>
                <?php endif; ?>
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
        
        // Seite alle 30 Sekunden neu laden für aktuelle Schicht-Dauer
        setInterval(() => location.reload(), 30000);
        </script>

        <div class="grid">
            <!-- Schichten der nächsten 24 Stunden -->
            <div class="section" style="grid-column: 1 / -1;">
                <div class="section-header">
                    <span>🕐</span>
                    <h2 class="section-title">Schichten nächste 24 Stunden</h2>
                </div>
                <?php if (empty($next_24h_shifts)): ?>
                    <div class="empty-state">Keine Schichten in den nächsten 24 Stunden</div>
                <?php else: ?>
                    <div style="display: grid; gap: 12px;">
                        <?php foreach($next_24h_shifts as $shift): ?>
                        <div style="padding: 16px; background: var(--bg-tertiary); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600; font-size: 1.125rem;">
                                    <?= htmlspecialchars($shift['name']) ?>
                                </div>
                                <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px;">
                                    📅 <?= date('d.m.Y H:i', strtotime($shift['startzeit'])) ?> Uhr
                                </div>
                            </div>
                            <div style="padding: 8px 16px; background: var(--accent); color: white; border-radius: 6px; font-weight: 600;">
                                🔴 Live
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <div class="section-header">
                    <span>📅</span>
                    <h2 class="section-title">Deine Schichten</h2>
                </div>
                <?php if (empty($my_shifts)): ?>
                    <div class="empty-state">Keine Schichten geplant</div>
                <?php else: ?>
                    <?php foreach ($my_shifts as $shift): ?>
                        <div class="list-item">
                            <div>
                                <div class="list-item-title"><?= date('d.m.Y', strtotime($shift['date'])) ?></div>
                                <div class="list-item-meta"><?= $shift['start'] ?> – <?= $shift['end'] ?></div>
                            </div>
                            <span><?php
                                $types = ['early' => '🌅 Früh', 'late' => '🌆 Spät', 'night' => '🌙 Nacht', 'day' => '☀️ Tag'];
                                echo $types[$shift['type']] ?? $shift['type'];
                            ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="section">
                <div class="section-header">
                    <span>🎉</span>
                    <h2 class="section-title">Nächste Events</h2>
                </div>
                <?php if (empty($next_events)): ?>
                    <div class="empty-state">Keine Events geplant</div>
                <?php else: ?>
                    <?php foreach ($next_events as $event): ?>
                        <div class="list-item">
                            <div>
                                <div class="list-item-title"><?= escape($event['title']) ?></div>
                                <div class="list-item-meta">
                                    <?= date('d.m.Y H:i', strtotime($event['start_time'])) ?>
                                    <?php if ($event['location']): ?>
                                        • <?= escape($event['location']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($event['cost'] > 0): ?>
                                <span class="text-success" style="font-weight: 600;"><?= number_format($event['cost'], 2) ?> €</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

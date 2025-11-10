<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin_user = is_admin();

// Schichten in den nächsten 24h
$next_24h_shifts = [];
$result = $conn->query("
    SELECT 
        s.date, s.type, s.start_time, s.end_time, u.name
    FROM shifts s
    JOIN users u ON u.id = s.user_id
    WHERE CONCAT(s.date, ' ', s.start_time) >= NOW()
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
                    <!-- Timeline mit Stunden-Markierungen -->
                    <div style="position: relative; padding: 40px 0 20px 0;">
                        <!-- Zeitachse (horizontale Linie) -->
                        <div style="position: relative; height: 2px; background: linear-gradient(90deg, #ef4444 0%, rgba(139,92,246,0.3) 5%, rgba(139,92,246,0.3) 95%, rgba(139,92,246,0.1) 100%); margin: 40px 0;">
                            
                            <!-- Stunden-Marker (alle 3h) -->
                            <?php
                            $now = new DateTime();
                            $timeline_start = clone $now;
                            for ($h = 0; $h <= 24; $h += 3):
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
                                
                                $left_percent = max(0, ($offset_minutes / 1440) * 100);
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
                    
                    <!-- Schichten-Liste darunter -->
                    <div style="display: grid; gap: 12px; margin-top: 40px;">
                        <?php foreach($next_24h_shifts as $shift): 
                            $shift_start = new DateTime($shift['date'] . ' ' . $shift['start_time']);
                            $shift_end = new DateTime($shift['date'] . ' ' . $shift['end_time']);
                            $type_label = $shift['type'] === 'early' ? '🌅 Frühschicht' : '🌙 Spätschicht';
                        ?>
                        <div style="padding: 16px; background: var(--bg-tertiary); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid <?= $shift['type'] === 'early' ? '#f59e0b' : '#8b5cf6' ?>;">
                            <div>
                                <div style="font-weight: 700; font-size: 1.125rem;">
                                    <?= htmlspecialchars($shift['name']) ?>
                                </div>
                                <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px;">
                                    <?= $type_label ?> · <?= $shift_start->format('H:i') ?> - <?= $shift_end->format('H:i') ?> Uhr
                                </div>
                            </div>
                            <div style="text-align: right; color: var(--text-secondary); font-size: 0.875rem;">
                                <?= $shift_start->format('d.m.Y') ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
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

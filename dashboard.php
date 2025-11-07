<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin_user = is_admin();

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

        <div class="grid">
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

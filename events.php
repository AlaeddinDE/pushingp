<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$is_admin = is_admin();

// Hole kommende Events
$events_query = "
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND status = 'zugesagt') as zugesagt,
           (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND status = 'abgesagt') as abgesagt,
           (SELECT status FROM event_participants WHERE event_id = e.id AND mitglied_id = ?) as my_status
    FROM events e
    WHERE e.event_status = 'active'
    AND e.datum >= CURDATE()
    ORDER BY e.datum ASC, e.start_time ASC
";

$stmt = $conn->prepare($events_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
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
        .event-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .event-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            display: grid;
            grid-template-columns: 100px 1fr 200px;
            gap: 24px;
            align-items: center;
            transition: all 0.2s;
        }
        
        .event-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .event-card.zugesagt {
            border-left: 4px solid #10b981;
        }
        
        .event-card.abgesagt {
            border-left: 4px solid #ef4444;
        }
        
        .event-date {
            text-align: center;
        }
        
        .date-day {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--accent);
            line-height: 1;
        }
        
        .date-month {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-top: 4px;
        }
        
        .event-info h3 {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0 0 12px 0;
        }
        
        .event-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .event-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .event-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
        }
        
        .btn-green {
            background: #10b981;
            color: white;
        }
        
        .btn-green:hover:not(:disabled) {
            background: #059669;
        }
        
        .btn-red {
            background: #ef4444;
            color: white;
        }
        
        .btn-red:hover:not(:disabled) {
            background: #dc2626;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .stats {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 8px;
        }
        
        @media (max-width: 768px) {
            .event-card {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .event-date {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .date-day {
                font-size: 2rem;
            }
            
            .event-actions {
                flex-direction: column;
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
            <p class="text-secondary">Kommende Termine</p>
        </div>

        <div class="event-list">
            <?php if (empty($events)): ?>
            <div style="text-align: center; padding: 60px; color: var(--text-secondary);">
                <div style="font-size: 3rem; margin-bottom: 16px;">📅</div>
                <h3>Keine Events geplant</h3>
            </div>
            <?php else: ?>
                <?php foreach ($events as $event): 
                    $date = new DateTime($event['datum']);
                    $status_class = $event['my_status'] ?? '';
                ?>
                <div class="event-card <?= $status_class ?>">
                    <div class="event-date">
                        <div class="date-day"><?= $date->format('d') ?></div>
                        <div class="date-month"><?= $date->format('M') ?></div>
                    </div>
                    
                    <div class="event-info">
                        <h3><?= htmlspecialchars($event['title']) ?></h3>
                        <div class="event-meta">
                            <span>🕐 <?= substr($event['start_time'], 0, 5) ?> Uhr</span>
                            <?php if ($event['location']): ?>
                            <span>📍 <?= htmlspecialchars($event['location']) ?></span>
                            <?php endif; ?>
                            <?php if ($event['cost'] > 0): ?>
                            <span>💰 <?= number_format($event['cost'], 2, ',', '.') ?> €</span>
                            <?php endif; ?>
                        </div>
                        <div class="stats">
                            ✓ <?= $event['zugesagt'] ?> zugesagt · ✗ <?= $event['abgesagt'] ?> abgesagt
                        </div>
                    </div>
                    
                    <div class="event-actions">
                        <button 
                            class="btn btn-green" 
                            onclick="respond(<?= $event['id'] ?>, 'zugesagt')"
                            <?= $event['my_status'] === 'zugesagt' ? 'disabled' : '' ?>>
                            <?= $event['my_status'] === 'zugesagt' ? '✓ Zugesagt' : 'Zusagen' ?>
                        </button>
                        <button 
                            class="btn btn-red" 
                            onclick="respond(<?= $event['id'] ?>, 'abgesagt')"
                            <?= $event['my_status'] === 'abgesagt' ? 'disabled' : '' ?>>
                            <?= $event['my_status'] === 'abgesagt' ? '✗ Abgesagt' : 'Absagen' ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    async function respond(eventId, status) {
        try {
            const res = await fetch('/api/event_respond.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_id: eventId, status: status })
            });
            const data = await res.json();
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannt'));
            }
        } catch (e) {
            alert('Fehler: ' + e.message);
        }
    }
    </script>
</body>
</html>

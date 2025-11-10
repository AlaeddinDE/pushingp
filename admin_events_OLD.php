<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Event-Status ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $event_id = intval($_POST['event_id']);
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE events SET event_status = ? WHERE id = ?");
    $stmt->bind_param('si', $new_status, $event_id);
    
    if ($stmt->execute()) {
        $success = "Event-Status aktualisiert!";
    } else {
        $error = "Fehler beim Aktualisieren";
    }
    $stmt->close();
}

// Event löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $event_id = intval($_POST['event_id']);
    
    $conn->query("DELETE FROM event_participation WHERE event_id = $event_id");
    
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    
    if ($stmt->execute()) {
        $success = "Event gelöscht!";
    } else {
        $error = "Fehler beim Löschen";
    }
    $stmt->close();
}

// Alle Events laden
$all_events = array();
$query = "
    SELECT 
        e.*,
        u.name as creator_name,
        u.username as creator_username
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    ORDER BY e.datum DESC, e.created_at DESC
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Zähle Teilnehmer
        $event_id = $row['id'];
        $part_result = $conn->query("SELECT status, COUNT(*) as cnt FROM event_participation WHERE event_id = $event_id GROUP BY status");
        $row['zusagen'] = 0;
        $row['absagen'] = 0;
        $row['vielleicht'] = 0;
        
        if ($part_result) {
            while ($part = $part_result->fetch_assoc()) {
                if ($part['status'] === 'zusage') $row['zusagen'] = $part['cnt'];
                if ($part['status'] === 'absage') $row['absagen'] = $part['cnt'];
                if ($part['status'] === 'vielleicht') $row['vielleicht'] = $part['cnt'];
            }
        }
        
        $all_events[] = $row;
    }
}

// Statistiken berechnen
$stats = array();
$stats['total'] = count($all_events);
$stats['active'] = 0;
$stats['upcoming'] = 0;
$stats['past'] = 0;
$stats['canceled'] = 0;

$today = date('Y-m-d');

foreach ($all_events as $event) {
    if ($event['event_status'] === 'active') {
        $stats['active']++;
    }
    if ($event['datum'] >= $today && $event['event_status'] === 'active') {
        $stats['upcoming']++;
    }
    if ($event['datum'] < $today) {
        $stats['past']++;
    }
    if ($event['event_status'] === 'canceled') {
        $stats['canceled']++;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Verwaltung – PUSHING P Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body.admin-page {
            --accent: #7f1010;
            --accent-hover: #650d0d;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-box .label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .stat-box .value {
            font-size: 2rem;
            font-weight: 900;
            color: var(--accent);
        }
        
        .events-list {
            display: grid;
            gap: 16px;
        }
        
        .event-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }
        
        .event-card:hover {
            border-color: var(--accent);
        }
        
        .event-card.past {
            opacity: 0.6;
        }
        
        .event-card.canceled {
            opacity: 0.5;
            border-color: var(--error);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin: 16px 0;
            padding: 12px;
            background: var(--bg-tertiary);
            border-radius: 8px;
        }
        
        .meta-item {
            font-size: 0.875rem;
        }
        
        .meta-label {
            color: var(--text-secondary);
            margin-right: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge.active {
            background: rgba(59, 186, 93, 0.15);
            color: #3bba5d;
        }
        
        .badge.canceled {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        
        .badge.completed {
            background: rgba(100, 100, 100, 0.15);
            color: #999;
        }
        
        .event-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.875rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .btn-edit {
            background: var(--accent);
            color: white;
        }
        
        .btn-status {
            background: rgba(255, 165, 0, 0.2);
            color: #ffa500;
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(59, 186, 93, 0.1);
            color: #3bba5d;
            border: 1px solid rgba(59, 186, 93, 0.3);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        select {
            width: 100%;
            padding: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="admin-page">
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">
                PUSHING P
                <span style="color: #7f1010; margin-left: 12px; font-weight: 700; font-size: 0.9rem; background: rgba(127, 16, 16, 0.1); padding: 4px 12px; border-radius: 6px; border: 1px solid rgba(127, 16, 16, 0.3);">Admin</span>
            </a>
            <nav class="nav">
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <a href="admin.php" class="nav-item">Admin</a>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>🎉 Event Verwaltung</h1>
            <p class="text-secondary">Alle Events verwalten und überwachen</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="label">Gesamt</div>
                <div class="value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Aktiv</div>
                <div class="value" style="color: var(--success);"><?php echo $stats['active']; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Bevorstehend</div>
                <div class="value" style="color: #104186;"><?php echo $stats['upcoming']; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Vergangen</div>
                <div class="value" style="color: var(--text-secondary);"><?php echo $stats['past']; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Abgesagt</div>
                <div class="value" style="color: var(--error);"><?php echo $stats['canceled']; ?></div>
            </div>
        </div>

        <div class="section">
            <div class="events-list">
                <?php foreach ($all_events as $event): 
                    $is_past = $event['datum'] < date('Y-m-d');
                    $is_canceled = $event['event_status'] === 'canceled';
                    $card_class = $is_canceled ? 'canceled' : ($is_past ? 'past' : '');
                ?>
                <div class="event-card <?php echo $card_class; ?>">
                    <div class="event-header">
                        <div>
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <span class="badge <?php echo $event['event_status']; ?>"><?php echo ucfirst($event['event_status']); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($event['description']): ?>
                        <p style="color: var(--text-secondary); margin-bottom: 12px;">
                            <?php echo htmlspecialchars($event['description']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="event-meta">
                        <div class="meta-item">
                            <span class="meta-label">📅 Datum:</span>
                            <strong><?php echo date('d.m.Y', strtotime($event['datum'])); ?></strong>
                        </div>
                        
                        <?php if ($event['start_time']): ?>
                        <div class="meta-item">
                            <span class="meta-label">🕐 Zeit:</span>
                            <strong><?php echo date('H:i', strtotime($event['start_time'])); ?> Uhr</strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($event['location']): ?>
                        <div class="meta-item">
                            <span class="meta-label">📍 Ort:</span>
                            <strong><?php echo htmlspecialchars($event['location']); ?></strong>
                        </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            <span class="meta-label">👤 Ersteller:</span>
                            <strong><?php echo htmlspecialchars($event['creator_name'] ?? 'Unbekannt'); ?></strong>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">👥 Teilnehmer:</span>
                            <strong style="color: var(--success);">✅ <?php echo $event['zusagen']; ?></strong>
                            <strong style="color: var(--error); margin-left: 8px;">❌ <?php echo $event['absagen']; ?></strong>
                            <strong style="color: var(--warning); margin-left: 8px;">❓ <?php echo $event['vielleicht']; ?></strong>
                        </div>
                        
                        <?php if ($event['cost'] > 0): ?>
                        <div class="meta-item">
                            <span class="meta-label">💰 Kosten:</span>
                            <strong>€<?php echo number_format($event['cost'], 2); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="event-actions">
                        <a href="events.php" class="btn-small btn-edit">✏️ Zur Events-Seite</a>
                        <button class="btn-small btn-status" onclick="openStatusModal(<?php echo $event['id']; ?>, '<?php echo $event['event_status']; ?>')">🔄 Status</button>
                        <button class="btn-small btn-delete" onclick="confirmDelete(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['title'], ENT_QUOTES); ?>')">🗑️ Löschen</button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($all_events)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                        <div style="font-size: 4rem; margin-bottom: 16px;">🎉</div>
                        <h3>Noch keine Events</h3>
                        <p style="margin-top: 8px;">Erstelle dein erstes Event auf der <a href="events.php" style="color: var(--accent);">Events-Seite</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Status ändern</h2>
            
            <form method="POST">
                <input type="hidden" name="event_id" id="status_event_id">
                
                <select name="new_status" id="new_status">
                    <option value="active">Aktiv</option>
                    <option value="canceled">Abgesagt</option>
                    <option value="completed">Abgeschlossen</option>
                </select>
                
                <button type="submit" name="change_status" class="btn" style="width: 100%;">Status ändern</button>
                <button type="button" onclick="closeStatusModal()" class="btn" style="width: 100%; margin-top: 8px; background: var(--bg-tertiary);">Abbrechen</button>
            </form>
        </div>
    </div>

    <script>
        function openStatusModal(id, currentStatus) {
            document.getElementById('status_event_id').value = id;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('statusModal').classList.add('active');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }
        
        function confirmDelete(id, title) {
            if (confirm('Event "' + title + '" wirklich ENDGÜLTIG löschen?\n\nDies entfernt auch alle Teilnahmen!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="event_id" value="' + id + '"><input type="hidden" name="delete_event" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>

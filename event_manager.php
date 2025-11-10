<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$msg = '';

// Status ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    try {
        $id = intval($_POST['event_id']);
        $status = $_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE events SET event_status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['msg'] = "✅ Status geändert!";
        }
    } catch (Exception $e) {
        $_SESSION['msg'] = "❌ Fehler: " . $e->getMessage();
    }
    header("Location: event_manager.php");
    exit;
}

// Löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    try {
        $id = intval($_POST['event_id']);
        
        // Erst Teilnehmer löschen
        $stmt = $conn->prepare("DELETE FROM event_participants WHERE event_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Dann Event löschen
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['msg'] = "🗑️ Event gelöscht!";
        }
    } catch (Exception $e) {
        $_SESSION['msg'] = "❌ Fehler: " . $e->getMessage();
    }
    header("Location: event_manager.php");
    exit;
}

// Nachricht aus Session holen
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Events laden
$events = array();
try {
    $res = $conn->query("
        SELECT e.*, u.name as creator
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id 
        ORDER BY e.datum DESC
    ");
    
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $events[] = $r;
        }
    }
} catch (Exception $e) {
    $msg = "❌ Fehler beim Laden: " . $e->getMessage();
}

// Statistiken
$total = count($events);
$aktiv = 0;
$abgesagt = 0;
$heute = date('Y-m-d');
$zukunft = 0;

foreach ($events as $e) {
    if ($e['event_status'] === 'active') $aktiv++;
    if ($e['event_status'] === 'canceled') $abgesagt++;
    if ($e['datum'] >= $heute && $e['event_status'] === 'active') $zukunft++;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Admin – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body.admin-page { --accent: #7f1010; --accent-hover: #650d0d; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 16px; text-align: center; }
        .stat .num { font-size: 2rem; font-weight: 900; color: var(--accent); }
        .stat .lbl { font-size: 0.875rem; color: var(--text-secondary); margin-top: 4px; }
        .card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .card.past { opacity: 0.5; }
        .card.cancel { opacity: 0.4; border-color: var(--error); }
        .title { font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; }
        .meta { display: flex; gap: 20px; flex-wrap: wrap; margin: 12px 0; font-size: 0.875rem; color: var(--text-secondary); }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge.active { background: rgba(59, 186, 93, 0.2); color: #3bba5d; }
        .badge.canceled { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge.completed { background: rgba(100, 100, 100, 0.2); color: #999; }
        .actions { display: flex; gap: 8px; margin-top: 12px; }
        .btn-sm { padding: 6px 12px; font-size: 0.875rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-status { background: rgba(255, 165, 0, 0.2); color: #ffa500; }
        .btn-del { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-box { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; padding: 24px; max-width: 400px; width: 90%; }
        .modal-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 16px; }
        select { width: 100%; padding: 12px; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); margin-bottom: 16px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert.success { background: rgba(59, 186, 93, 0.1); border: 1px solid rgba(59, 186, 93, 0.3); color: #3bba5d; }
        .alert.error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; }
    </style>
</head>
<body class="admin-page">
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit;">PUSHING P <span style="color: #7f1010; margin-left: 12px; font-weight: 700; font-size: 0.9rem; background: rgba(127, 16, 16, 0.1); padding: 4px 12px; border-radius: 6px;">Admin</span></a>
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
            <h1>🎉 Event Manager</h1>
            <p class="text-secondary">Alle Events verwalten</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?php echo strpos($msg, '✅') !== false || strpos($msg, '🗑️') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat">
                <div class="num"><?php echo $total; ?></div>
                <div class="lbl">Gesamt</div>
            </div>
            <div class="stat">
                <div class="num" style="color: var(--success);"><?php echo $aktiv; ?></div>
                <div class="lbl">Aktiv</div>
            </div>
            <div class="stat">
                <div class="num" style="color: #104186;"><?php echo $zukunft; ?></div>
                <div class="lbl">Bevorstehend</div>
            </div>
            <div class="stat">
                <div class="num" style="color: var(--error);"><?php echo $abgesagt; ?></div>
                <div class="lbl">Abgesagt</div>
            </div>
        </div>

        <div class="section">
            <?php if (empty($events)): ?>
                <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                    <div style="font-size: 4rem;">🎉</div>
                    <h3>Keine Events</h3>
                    <p style="margin-top: 8px;">Erstelle Events auf der <a href="events.php" style="color: var(--accent);">Events-Seite</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $e): 
                    $past = $e['datum'] < date('Y-m-d');
                    $cancel = $e['event_status'] === 'canceled';
                    $class = $cancel ? 'cancel' : ($past ? 'past' : '');
                ?>
                <div class="card <?php echo $class; ?>">
                    <div class="title"><?php echo htmlspecialchars($e['title']); ?></div>
                    <span class="badge <?php echo $e['event_status']; ?>"><?php echo $e['event_status']; ?></span>
                    
                    <?php if ($e['description']): ?>
                    <p style="color: var(--text-secondary); margin: 8px 0;"><?php echo htmlspecialchars($e['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="meta">
                        <span>📅 <?php echo date('d.m.Y', strtotime($e['datum'])); ?></span>
                        <?php if ($e['start_time']): ?>
                        <span>🕐 <?php echo date('H:i', strtotime($e['start_time'])); ?></span>
                        <?php endif; ?>
                        <?php if ($e['location']): ?>
                        <span>📍 <?php echo htmlspecialchars($e['location']); ?></span>
                        <?php endif; ?>
                        <span>👤 <?php echo htmlspecialchars($e['creator'] ?? 'Unbekannt'); ?></span>
                        <?php if ($e['cost'] > 0): ?>
                        <span>💰 €<?php echo number_format($e['cost'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="actions">
                        <button class="btn-sm btn-status" onclick="openStatus(<?php echo $e['id']; ?>, '<?php echo $e['event_status']; ?>')">🔄 Status</button>
                        <button class="btn-sm btn-del" onclick="deleteEvent(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars($e['title'], ENT_QUOTES); ?>')">🗑️ Löschen</button>
                        <a href="events.php" class="btn-sm" style="background: var(--accent); color: white;">✏️ Bearbeiten</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Modal -->
    <div id="modal" class="modal" onclick="if(event.target===this) closeModal()">
        <div class="modal-box">
            <div class="modal-title">Status ändern</div>
            <form method="POST" action="event_manager.php">
                <input type="hidden" name="event_id" id="eid">
                <select name="new_status" id="status">
                    <option value="active">Aktiv</option>
                    <option value="canceled">Abgesagt</option>
                    <option value="completed">Abgeschlossen</option>
                </select>
                <button type="submit" name="change_status" value="1" class="btn" style="width: 100%;">Speichern</button>
                <button type="button" onclick="closeModal()" class="btn" style="width: 100%; margin-top: 8px; background: var(--bg-tertiary);">Abbrechen</button>
            </form>
        </div>
    </div>

    <script>
        function openStatus(id, current) {
            document.getElementById('eid').value = id;
            document.getElementById('status').value = current;
            document.getElementById('modal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('modal').classList.remove('show');
        }
        
        function deleteEvent(id, title) {
            if (confirm('Event "' + title + '" wirklich löschen?\n\nDies entfernt auch alle Teilnahmen!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'event_manager.php';
                form.innerHTML = '<input type="hidden" name="event_id" value="' + id + '"><input type="hidden" name="delete" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

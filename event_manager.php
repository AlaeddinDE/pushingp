<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$is_admin = is_admin();

// Access control
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT created_by FROM events WHERE id = ?");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $stmt->bind_result($created_by);
    $stmt->fetch();
    $stmt->close();
    
    if (!$is_admin && $created_by != $user_id) {
        header('Location: events.php');
        exit;
    }
}

$msg = '';

// Handle Delete (Legacy POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    try {
        $id = intval($_POST['event_id']);
        // Delete participants first
        $stmt = $conn->prepare("DELETE FROM event_participants WHERE event_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        
        // Delete event
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['msg'] = "🗑️ Event gelöscht!";
    } catch (Exception $e) {
        $_SESSION['msg'] = "❌ Fehler: " . $e->getMessage();
    }
    header("Location: event_manager.php");
    exit;
}

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Load Events
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
        
        .card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; transition: all 0.2s; }
        .card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .card.past { opacity: 0.6; }
        .card.cancel { opacity: 0.6; border-color: var(--error); }
        
        .title { font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
        .meta { display: flex; gap: 20px; flex-wrap: wrap; margin: 12px 0; font-size: 0.875rem; color: var(--text-secondary); }
        
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge.active { background: rgba(59, 186, 93, 0.2); color: #3bba5d; }
        .badge.canceled { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge.completed { background: rgba(100, 100, 100, 0.2); color: #999; }
        
        .actions { display: flex; gap: 8px; margin-top: 12px; }
        .btn-sm { padding: 6px 12px; font-size: 0.875rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-edit { background: var(--accent); color: white; }
        .btn-del { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; backdrop-filter: blur(5px); }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-box { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.25rem; font-weight: 700; }
        .modal-close { background: none; border: none; color: var(--text-secondary); font-size: 1.5rem; cursor: pointer; }
        
        .modal-tabs { display: flex; border-bottom: 1px solid var(--border); }
        .tab-btn { flex: 1; padding: 16px; background: none; border: none; color: var(--text-secondary); font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); background: rgba(127, 16, 16, 0.05); }
        
        .modal-content { padding: 20px; overflow-y: auto; flex: 1; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-family: inherit; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        
        .participant-list { display: flex; flex-direction: column; gap: 8px; }
        .participant-item { display: flex; align-items: center; justify-content: space-between; padding: 10px; background: var(--bg-tertiary); border-radius: 8px; }
        .p-info { display: flex; align-items: center; gap: 10px; }
        .p-status { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-coming { background: rgba(59, 186, 93, 0.2); color: #3bba5d; }
        .status-declined { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .status-no_show { background: rgba(255, 165, 0, 0.2); color: #ffa500; }
        
        .add-p-form { display: flex; gap: 8px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
        
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
            <p class="text-secondary">Events bearbeiten & Teilnehmer verwalten</p>
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
                    <h3>Keine Events</h3>
                </div>
            <?php else: ?>
                <?php foreach ($events as $e): 
                    $past = $e['datum'] < date('Y-m-d');
                    $cancel = $e['event_status'] === 'canceled';
                    $class = $cancel ? 'cancel' : ($past ? 'past' : '');
                ?>
                <div class="card <?php echo $class; ?>">
                    <div class="title">
                        <?php echo htmlspecialchars($e['title']); ?>
                        <span class="badge <?php echo $e['event_status']; ?>"><?php echo $e['event_status']; ?></span>
                    </div>
                    
                    <div class="meta">
                        <span>📅 <?php echo date('d.m.Y', strtotime($e['datum'])); ?></span>
                        <?php if ($e['start_time']): ?>
                        <span>🕐 <?php echo date('H:i', strtotime($e['start_time'])); ?></span>
                        <?php endif; ?>
                        <?php if ($e['location']): ?>
                        <span>📍 <?php echo htmlspecialchars($e['location']); ?></span>
                        <?php endif; ?>
                        <span>👤 <?php echo htmlspecialchars($e['creator'] ?? 'Unbekannt'); ?></span>
                    </div>
                    
                    <div class="actions">
                        <button class="btn-sm btn-edit" onclick="openEditModal(<?php echo $e['id']; ?>)">✏️ Bearbeiten & Teilnehmer</button>
                        <button class="btn-sm btn-del" onclick="deleteEvent(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars($e['title'], ENT_QUOTES); ?>')">🗑️ Löschen</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-title">Event bearbeiten</div>
                <button class="modal-close" onclick="closeEditModal()">×</button>
            </div>
            <div class="modal-tabs">
                <button class="tab-btn active" onclick="switchTab('details')">Details</button>
                <button class="tab-btn" onclick="switchTab('participants')">Teilnehmer</button>
            </div>
            <div class="modal-content">
                <!-- Details Tab -->
                <div id="tab-details" class="tab-pane active">
                    <form id="editForm" onsubmit="saveEvent(event)">
                        <input type="hidden" name="event_id" id="edit_id">
                        <div class="form-group">
                            <label class="form-label">Titel</label>
                            <input type="text" name="title" id="edit_title" class="form-input" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Datum</label>
                                <input type="date" name="datum" id="edit_datum" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="event_status" id="edit_status" class="form-select">
                                    <option value="active">Aktiv</option>
                                    <option value="canceled">Abgesagt</option>
                                    <option value="completed">Abgeschlossen</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Startzeit</label>
                                <input type="time" name="start_time" id="edit_start" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Endzeit</label>
                                <input type="time" name="end_time" id="edit_end" class="form-input">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ort</label>
                            <input type="text" name="location" id="edit_location" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Beschreibung</label>
                            <textarea name="description" id="edit_desc" class="form-textarea" rows="3"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Kosten (Gesamt)</label>
                                <input type="number" step="0.01" name="cost" id="edit_cost" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Pro Person</label>
                                <input type="number" step="0.01" name="cost_per_person" id="edit_cpp" class="form-input">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bezahlung</label>
                            <select name="paid_by" id="edit_paid" class="form-select">
                                <option value="private">Privat</option>
                                <option value="pool">Pool</option>
                                <option value="anteilig">Anteilig</option>
                            </select>
                        </div>
                        <button type="submit" class="btn" style="width: 100%;">Speichern</button>
                    </form>
                </div>

                <!-- Participants Tab -->
                <div id="tab-participants" class="tab-pane">
                    <div class="add-p-form">
                        <select id="add_user_select" class="form-select" style="margin-bottom: 0;">
                            <option value="">Mitglied auswählen...</option>
                        </select>
                        <button onclick="addParticipant()" class="btn" style="white-space: nowrap;">+ Hinzufügen</button>
                    </div>
                    <div id="participants_list" class="participant-list">
                        <!-- Populated via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentEventId = 0;

        function openEditModal(id) {
            currentEventId = id;
            document.getElementById('editModal').classList.add('show');
            loadEventDetails(id);
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            
            document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
            document.getElementById(`tab-${tab}`).classList.add('active');
        }

        async function loadEventDetails(id) {
            try {
                const res = await fetch(`/api/v2/get_event_details_admin.php?id=${id}`);
                const data = await res.json();
                
                if (data.error) {
                    alert(data.error);
                    return;
                }

                // Fill Details
                const e = data.event;
                document.getElementById('edit_id').value = e.id;
                document.getElementById('edit_title').value = e.title;
                document.getElementById('edit_datum').value = e.datum;
                document.getElementById('edit_status').value = e.event_status;
                document.getElementById('edit_start').value = e.start_time;
                document.getElementById('edit_end').value = e.end_time;
                document.getElementById('edit_location').value = e.location;
                document.getElementById('edit_desc').value = e.description;
                document.getElementById('edit_cost').value = e.cost;
                document.getElementById('edit_cpp').value = e.cost_per_person;
                document.getElementById('edit_paid').value = e.paid_by;

                // Fill Users Dropdown
                const userSelect = document.getElementById('add_user_select');
                userSelect.innerHTML = '<option value="">Mitglied auswählen...</option>';
                data.users.forEach(u => {
                    userSelect.innerHTML += `<option value="${u.id}">${u.name} (${u.username})</option>`;
                });

                // Fill Participants List
                renderParticipants(data.participants);

            } catch (err) {
                console.error(err);
                alert('Fehler beim Laden der Daten');
            }
        }

        function renderParticipants(participants) {
            const list = document.getElementById('participants_list');
            list.innerHTML = '';
            
            if (participants.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-secondary); padding: 20px;">Keine Teilnehmer</div>';
                return;
            }

            participants.forEach(p => {
                list.innerHTML += `
                    <div class="participant-item">
                        <div class="p-info">
                            <strong>${p.name}</strong>
                            <span class="p-status status-${p.status}">${p.status}</span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <select onchange="updateParticipant(${p.mitglied_id}, this.value)" style="padding: 4px; margin: 0; width: auto; font-size: 0.8rem;">
                                <option value="coming" ${p.status === 'coming' ? 'selected' : ''}>Zusage</option>
                                <option value="declined" ${p.status === 'declined' ? 'selected' : ''}>Absage</option>
                                <option value="no_show" ${p.status === 'no_show' ? 'selected' : ''}>Nicht erschienen</option>
                            </select>
                            <button onclick="removeParticipant(${p.mitglied_id})" class="btn-del" style="padding: 4px 8px;">×</button>
                        </div>
                    </div>
                `;
            });
        }

        async function saveEvent(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('editForm'));
            
            try {
                const res = await fetch('/api/v2/update_event_admin.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    alert('✅ Gespeichert!');
                    location.reload();
                } else {
                    alert('❌ Fehler: ' + data.error);
                }
            } catch (err) {
                alert('Netzwerkfehler');
            }
        }

        async function updateParticipant(userId, status) {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('event_id', currentEventId);
            formData.append('user_id', userId);
            formData.append('status', status);

            try {
                const res = await fetch('/api/v2/update_event_participant.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.status === 'success') {
                    // Reload details to refresh list
                    loadEventDetails(currentEventId);
                } else {
                    alert('Fehler: ' + data.error);
                }
            } catch (err) {
                alert('Netzwerkfehler');
            }
        }

        async function addParticipant() {
            const userId = document.getElementById('add_user_select').value;
            if (!userId) return;

            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('event_id', currentEventId);
            formData.append('user_id', userId);
            formData.append('status', 'coming'); // Default

            try {
                const res = await fetch('/api/v2/update_event_participant.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.status === 'success') {
                    loadEventDetails(currentEventId);
                    document.getElementById('add_user_select').value = '';
                } else {
                    alert('Fehler: ' + data.error);
                }
            } catch (err) {
                alert('Netzwerkfehler');
            }
        }

        async function removeParticipant(userId) {
            if (!confirm('Teilnehmer entfernen?')) return;

            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('event_id', currentEventId);
            formData.append('user_id', userId);

            try {
                const res = await fetch('/api/v2/update_event_participant.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.status === 'success') {
                    loadEventDetails(currentEventId);
                } else {
                    alert('Fehler: ' + data.error);
                }
            } catch (err) {
                alert('Netzwerkfehler');
            }
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

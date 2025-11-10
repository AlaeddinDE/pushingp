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

// Hole alle Benutzer
$users = [];
$result = $conn->query("SELECT id, username, name, email, phone, status, role, created_at, last_login FROM users ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// PIN zurücksetzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pin'])) {
    $user_id = intval($_POST['user_id']);
    
    $stmt = $conn->prepare("UPDATE users SET pin_hash = NULL, password = '' WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success = "PIN wurde zurückgesetzt. Standard-PIN: 0000";
    } else {
        $error = "Fehler beim Zurücksetzen";
    }
    $stmt->close();
}

// Benutzer bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param('sssssi', $name, $email, $phone, $role, $status, $user_id);
    
    if ($stmt->execute()) {
        $success = "Benutzer erfolgreich aktualisiert";
        // Refresh users list
        $users = [];
        $result = $conn->query("SELECT id, username, name, email, phone, status, role, created_at, last_login FROM users ORDER BY name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
    } else {
        $error = "Fehler beim Aktualisieren";
    }
    $stmt->close();
}

// Neuen PIN setzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pin'])) {
    $user_id = intval($_POST['user_id']);
    $new_pin = $_POST['new_pin'];
    
    if (preg_match('/^\d{4}$/', $new_pin)) {
        $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET pin_hash = ? WHERE id = ?");
        $stmt->bind_param('si', $pin_hash, $user_id);
        
        if ($stmt->execute()) {
            $success = "Neuer PIN wurde gesetzt";
        } else {
            $error = "Fehler beim Setzen des PINs";
        }
        $stmt->close();
    } else {
        $error = "PIN muss 4 Ziffern haben";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Verwaltung – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body.admin-page {
            --accent: #7f1010;
            --accent-hover: #650d0d;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }
        
        .user-table th {
            background: var(--bg-tertiary);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
        }
        
        .user-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }
        
        .user-table tr:hover {
            background: var(--bg-tertiary);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge.active {
            background: rgba(59, 186, 93, 0.1);
            color: #3bba5d;
        }
        
        .badge.inactive {
            background: rgba(255, 165, 0, 0.1);
            color: #ffa500;
        }
        
        .badge.admin {
            background: rgba(127, 16, 16, 0.1);
            color: #7f1010;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.875rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .btn-edit {
            background: var(--accent);
            color: white;
        }
        
        .btn-edit:hover {
            background: var(--accent-hover);
        }
        
        .btn-reset {
            background: rgba(255, 165, 0, 0.2);
            color: #ffa500;
            margin-left: 8px;
        }
        
        .btn-reset:hover {
            background: rgba(255, 165, 0, 0.3);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
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
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
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
                <a href="admin_kasse.php" class="nav-item">Admin Kasse</a>
                <a href="admin_members.php" class="nav-item active">Members</a>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>👥 Member Verwaltung</h1>
            <p class="text-secondary">Benutzer bearbeiten, PINs zurücksetzen und Rollen verwalten</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Alle Benutzer</h2>
                <span class="text-secondary"><?= count($users) ?> Mitglieder</span>
            </div>
            
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>E-Mail</th>
                        <th>Telefon</th>
                        <th>Status</th>
                        <th>Rolle</th>
                        <th>Letzter Login</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user['name'] ?? $user['username']) ?></strong></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                        </td>
                        <td>
                            <?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Nie' ?>
                        </td>
                        <td>
                            <button class="btn-small btn-edit" onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'] ?? $user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES) ?>', '<?= $user['role'] ?>', '<?= $user['status'] ?>')">
                                ✏️ Bearbeiten
                            </button>
                            <button class="btn-small btn-reset" onclick="openPinModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'] ?? $user['username'], ENT_QUOTES) ?>')">
                                🔑 PIN
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Benutzer bearbeiten</h2>
                <button class="close-modal" onclick="closeEditModal()">×</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="edit_username" readonly style="opacity: 0.6; cursor: not-allowed;">
                </div>
                
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                
                <div class="form-group">
                    <label>E-Mail</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="tel" name="phone" id="edit_phone">
                </div>
                
                <div class="form-group">
                    <label>Rolle</label>
                    <select name="role" id="edit_role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Aktiv</option>
                        <option value="inactive">Inaktiv</option>
                        <option value="locked">Gesperrt</option>
                    </select>
                </div>
                
                <button type="submit" name="edit_user" class="btn" style="width: 100%;">Speichern</button>
            </form>
        </div>
    </div>

    <!-- PIN Modal -->
    <div id="pinModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">PIN verwalten</h2>
                <button class="close-modal" onclick="closePinModal()">×</button>
            </div>
            
            <p class="text-secondary" style="margin-bottom: 24px;">
                Benutzer: <strong id="pin_user_name"></strong>
            </p>
            
            <form method="POST" style="margin-bottom: 20px;">
                <input type="hidden" name="user_id" id="pin_user_id">
                
                <div class="form-group">
                    <label>Neuer PIN (4 Ziffern)</label>
                    <input type="text" name="new_pin" pattern="\d{4}" maxlength="4" placeholder="0000" required>
                </div>
                
                <button type="submit" name="set_pin" class="btn" style="width: 100%; background: var(--success);">
                    PIN setzen
                </button>
            </form>
            
            <form method="POST">
                <input type="hidden" name="user_id" id="pin_user_id_reset">
                <button type="submit" name="reset_pin" class="btn" style="width: 100%; background: var(--warning);">
                    PIN auf 0000 zurücksetzen
                </button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, username, email, phone, role, status) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_status').value = status;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function openPinModal(id, name) {
            document.getElementById('pin_user_id').value = id;
            document.getElementById('pin_user_id_reset').value = id;
            document.getElementById('pin_user_name').textContent = name;
            document.getElementById('pinModal').classList.add('active');
        }
        
        function closePinModal() {
            document.getElementById('pinModal').classList.remove('active');
        }
        
        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>

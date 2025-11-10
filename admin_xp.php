<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/xp_system.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$user_id = get_current_user_id();

// Get all users with XP info
$all_users = [];
$result = $conn->query("SELECT * FROM v_user_xp_progress ORDER BY xp_total DESC");
while ($row = $result->fetch_assoc()) {
    $all_users[] = $row;
}

// Get all badges
$all_badges = [];
$result = $conn->query("SELECT * FROM badges ORDER BY requirement_value ASC");
while ($row = $result->fetch_assoc()) {
    $all_badges[] = $row;
}

// Get all XP actions
$all_xp_actions = [];
$result = $conn->query("SELECT * FROM xp_actions ORDER BY category, action_code");
while ($row = $result->fetch_assoc()) {
    $all_xp_actions[] = $row;
}

// Get all levels
$all_levels = [];
$result = $conn->query("SELECT * FROM level_config ORDER BY level_id");
while ($row = $result->fetch_assoc()) {
    $all_levels[] = $row;
}

// Get recent XP history
$recent_xp_history = [];
$result = $conn->query("
    SELECT xh.*, u.name, u.username 
    FROM xp_history xh
    JOIN users u ON xh.user_id = u.id
    ORDER BY xh.timestamp DESC 
    LIMIT 50
");
while ($row = $result->fetch_assoc()) {
    $recent_xp_history[] = $row;
}

// Stats
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM xp_history");
$stats['total_xp_transactions'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT SUM(xp_change) as total FROM xp_history WHERE xp_change > 0");
$stats['total_xp_awarded'] = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(*) as total FROM user_badges");
$stats['total_badges_awarded'] = $result->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XP System Admin – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .admin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            overflow-x: auto;
        }
        
        .admin-tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .admin-tab:hover {
            color: var(--text-primary);
        }
        
        .admin-tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .admin-stat-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .admin-stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 8px;
        }
        
        .admin-stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .user-table, .history-table, .config-table {
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table-row {
            display: grid;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .table-row:hover {
            background: var(--bg-tertiary);
        }
        
        .table-header {
            background: var(--bg-tertiary);
            font-weight: 700;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .user-table .table-row {
            grid-template-columns: 200px 100px 150px 120px 1fr;
        }
        
        .history-table .table-row {
            grid-template-columns: 150px 120px 80px 1fr 150px;
        }
        
        .config-table .table-row {
            grid-template-columns: 200px 100px 100px 1fr 100px;
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
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary:hover {
            opacity: 0.8;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            opacity: 0.8;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            padding: 32px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .badge-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-tertiary);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin: 4px;
        }
        
        .xp-positive {
            color: #10b981;
        }
        
        .xp-negative {
            color: #ef4444;
        }
        
        .status-active {
            color: #10b981;
        }
        
        .status-inactive {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">PUSHING P</a>
            <nav class="nav">
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <a href="chat.php" class="nav-item">Chat</a>
                <a href="admin.php" class="nav-item">Admin</a>
                <a href="admin_xp.php" class="nav-item" style="border-bottom: 2px solid var(--accent);">⚙️ XP System</a>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>
    
    <div class="container" style="max-width: 1400px;">
        <div class="welcome">
            <h1>⚙️ XP System Administration</h1>
            <p style="color: var(--text-secondary);">Vollständige Kontrolle über das Leveling-System</p>
        </div>
        
        <!-- Stats Overview -->
        <div class="admin-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= count($all_users) ?></div>
                <div class="admin-stat-label">Aktive User</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= number_format($stats['total_xp_awarded']) ?></div>
                <div class="admin-stat-label">Gesamt XP vergeben</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_xp_transactions'] ?></div>
                <div class="admin-stat-label">XP Transaktionen</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_badges_awarded'] ?></div>
                <div class="admin-stat-label">Badges verliehen</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="admin-tabs">
            <button class="admin-tab active" onclick="switchTab('users')">👥 User Management</button>
            <button class="admin-tab" onclick="switchTab('history')">📊 XP History</button>
            <button class="admin-tab" onclick="switchTab('actions')">⚙️ XP Actions</button>
            <button class="admin-tab" onclick="switchTab('badges')">🏅 Badges</button>
            <button class="admin-tab" onclick="switchTab('levels')">🏆 Levels</button>
        </div>
        
        <!-- Tab: User Management -->
        <div id="tab-users" class="tab-content active">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">User XP Management</h2>
                    <button class="btn-small btn-primary" onclick="openAwardXPModal()">+ XP vergeben</button>
                </div>
                
                <div class="user-table">
                    <div class="table-row table-header">
                        <div>User</div>
                        <div>Level</div>
                        <div>XP</div>
                        <div>Badges</div>
                        <div>Aktionen</div>
                    </div>
                    <?php foreach ($all_users as $user): ?>
                    <div class="table-row">
                        <div>
                            <div style="font-weight: 600;"><?= escape($user['name']) ?></div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">@<?= escape($user['username']) ?></div>
                        </div>
                        <div>
                            <?php if (!empty($user['level_image'])): ?>
                                <img src="<?= $user['level_image'] ?>" alt="Level <?= $user['level_id'] ?>" style="width: 32px; height: 32px; object-fit: contain; vertical-align: middle; margin-right: 4px;">
                            <?php else: ?>
                                <span style="font-size: 1.25rem;"><?= $user['level_emoji'] ?></span>
                            <?php endif; ?>
                            <?= $user['level_id'] ?>
                        </div>
                        <div>
                            <div style="font-weight: 700;"><?= number_format($user['xp_total']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= round($user['progress_percent']) ?>%</div>
                        </div>
                        <div>
                            <?php 
                            $user_badges = get_user_badges($user['id']);
                            echo count($user_badges) . ' Badges';
                            ?>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-small btn-primary" onclick="awardXP(<?= $user['id'] ?>, '<?= escape($user['name']) ?>')">+ XP</button>
                            <button class="btn-small btn-primary" onclick="viewUserDetails(<?= $user['id'] ?>)">Details</button>
                            <button class="btn-small btn-danger" onclick="resetUserXP(<?= $user['id'] ?>, '<?= escape($user['name']) ?>')">Reset</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Tab: XP History -->
        <div id="tab-history" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Recent XP Transactions</h2>
                </div>
                
                <div class="history-table">
                    <div class="table-row table-header">
                        <div>Zeitpunkt</div>
                        <div>User</div>
                        <div>XP</div>
                        <div>Aktion</div>
                        <div>Beschreibung</div>
                    </div>
                    <?php foreach ($recent_xp_history as $entry): ?>
                    <div class="table-row">
                        <div style="font-size: 0.875rem;">
                            <?= date('d.m.Y H:i', strtotime($entry['timestamp'])) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?= escape($entry['name']) ?></div>
                        </div>
                        <div class="<?= $entry['xp_change'] > 0 ? 'xp-positive' : 'xp-negative' ?>" style="font-weight: 700;">
                            <?= $entry['xp_change'] > 0 ? '+' : '' ?><?= $entry['xp_change'] ?>
                        </div>
                        <div style="font-size: 0.875rem;">
                            <code><?= escape($entry['action_code']) ?></code>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?= escape($entry['description']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Tab: XP Actions -->
        <div id="tab-actions" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">XP Actions Configuration</h2>
                    <button class="btn-small btn-primary" onclick="createXPAction()">+ Neue Action</button>
                </div>
                
                <div class="config-table">
                    <div class="table-row table-header">
                        <div>Action Code</div>
                        <div>Kategorie</div>
                        <div>XP Wert</div>
                        <div>Beschreibung</div>
                        <div>Status</div>
                    </div>
                    <?php foreach ($all_xp_actions as $action): ?>
                    <div class="table-row">
                        <div style="font-weight: 600;">
                            <code><?= escape($action['action_code']) ?></code>
                        </div>
                        <div>
                            <span class="badge-item"><?= escape($action['category']) ?></span>
                        </div>
                        <div class="<?= $action['xp_value'] > 0 ? 'xp-positive' : 'xp-negative' ?>" style="font-weight: 700;">
                            <?= $action['xp_value'] > 0 ? '+' : '' ?><?= $action['xp_value'] ?>
                        </div>
                        <div style="font-size: 0.875rem;">
                            <?= escape($action['description']) ?>
                        </div>
                        <div>
                            <button class="btn-small <?= $action['is_active'] ? 'btn-primary' : 'btn-danger' ?>" 
                                    onclick="toggleActionStatus(<?= $action['id'] ?>, <?= $action['is_active'] ?>)">
                                <?= $action['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Tab: Badges -->
        <div id="tab-badges" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Badge Management</h2>
                    <button class="btn-small btn-primary" onclick="createBadge()">+ Neues Badge</button>
                </div>
                
                <div style="display: grid; gap: 16px;">
                    <?php foreach ($all_badges as $badge): 
                        // Count how many users have this badge
                        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_badges WHERE badge_id = ?");
                        $count_stmt->bind_param('i', $badge['id']);
                        $count_stmt->execute();
                        $count_stmt->bind_result($badge_count);
                        $count_stmt->fetch();
                        $count_stmt->close();
                    ?>
                    <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div style="font-size: 2rem; margin-bottom: 8px;"><?= $badge['emoji'] ?></div>
                            <div style="font-weight: 700; font-size: 1.125rem; margin-bottom: 4px;">
                                <?= escape($badge['title']) ?>
                            </div>
                            <div style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 12px;">
                                <?= escape($badge['description']) ?>
                            </div>
                            <div style="display: flex; gap: 12px; font-size: 0.875rem;">
                                <span class="badge-item">+<?= $badge['xp_reward'] ?> XP Reward</span>
                                <span class="badge-item"><?= $badge_count ?> User haben es</span>
                                <span class="badge-item"><?= escape($badge['requirement_type']) ?>: <?= $badge['requirement_value'] ?></span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-small btn-primary" onclick="manualAwardBadge(<?= $badge['id'] ?>)">Manuell vergeben</button>
                            <button class="btn-small btn-primary" onclick="editBadge(<?= $badge['id'] ?>)">Bearbeiten</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Tab: Levels -->
        <div id="tab-levels" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Level Configuration</h2>
                </div>
                
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($all_levels as $level): 
                        // Count users at this level
                        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE level_id = ? AND status = 'active'");
                        $count_stmt->bind_param('i', $level['level_id']);
                        $count_stmt->execute();
                        $count_stmt->bind_result($level_count);
                        $count_stmt->fetch();
                        $count_stmt->close();
                    ?>
                    <div style="background: var(--bg-secondary); padding: 16px 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <div style="font-size: 2rem;"><?= $level['emoji'] ?></div>
                            <div>
                                <div style="font-weight: 700; font-size: 1.125rem;">
                                    Level <?= $level['level_id'] ?> – <?= escape($level['title']) ?>
                                </div>
                                <div style="color: var(--text-secondary); font-size: 0.875rem;">
                                    <?= escape($level['unlock_text']) ?>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: var(--accent);">
                                <?= number_format($level['xp_required']) ?> XP
                            </div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                <?= $level_count ?> User
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Award XP Modal -->
    <div id="awardXPModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">XP vergeben</div>
            <form id="awardXPForm">
                <div class="form-group">
                    <label class="form-label">User</label>
                    <select id="awardUserId" class="form-select" required>
                        <option value="">Wähle User...</option>
                        <?php foreach ($all_users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= escape($user['name']) ?> (@<?= escape($user['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">XP Menge (negativ für Abzug)</label>
                    <input type="number" id="awardXPAmount" class="form-input" required placeholder="z.B. 100 oder -50">
                </div>
                <div class="form-group">
                    <label class="form-label">Grund</label>
                    <textarea id="awardXPReason" class="form-textarea" placeholder="Warum wird XP vergeben?"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-small btn-primary" style="flex: 1;">XP vergeben</button>
                    <button type="button" class="btn-small btn-danger" onclick="closeModal('awardXPModal')">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.admin-tab').forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function openAwardXPModal() {
            document.getElementById('awardXPModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function awardXP(userId, userName) {
            document.getElementById('awardUserId').value = userId;
            openAwardXPModal();
        }
        
        // Award XP Form Submit
        document.getElementById('awardXPForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const userId = document.getElementById('awardUserId').value;
            const xpAmount = parseInt(document.getElementById('awardXPAmount').value);
            const reason = document.getElementById('awardXPReason').value || 'Manuelle XP-Vergabe (Admin)';
            
            try {
                const response = await fetch('/api/v2/admin_award_xp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        user_id: userId,
                        xp_amount: xpAmount,
                        reason: reason
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert(`✅ ${xpAmount > 0 ? '+' : ''}${xpAmount} XP vergeben!\n${data.data.level_up ? '🎉 Level Up!' : ''}`);
                    location.reload();
                } else {
                    alert('❌ Fehler: ' + data.error);
                }
            } catch (error) {
                alert('❌ Fehler beim Vergeben von XP');
                console.error(error);
            }
        });
        
        async function toggleActionStatus(actionId, currentStatus) {
            const newStatus = currentStatus ? 0 : 1;
            
            try {
                const response = await fetch('/api/v2/admin_toggle_xp_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action_id: actionId,
                        is_active: newStatus
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('❌ Fehler: ' + data.error);
                }
            } catch (error) {
                alert('❌ Fehler beim Aktualisieren');
                console.error(error);
            }
        }
        
        async function resetUserXP(userId, userName) {
            if (!confirm(`Wirklich alle XP von ${userName} zurücksetzen?\n\nDies kann nicht rückgängig gemacht werden!`)) {
                return;
            }
            
            try {
                const response = await fetch('/api/v2/admin_reset_user_xp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ user_id: userId })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert('✅ XP zurückgesetzt!');
                    location.reload();
                } else {
                    alert('❌ Fehler: ' + data.error);
                }
            } catch (error) {
                alert('❌ Fehler beim Zurücksetzen');
                console.error(error);
            }
        }
        
        function viewUserDetails(userId) {
            window.location.href = '/admin_user_xp.php?user_id=' + userId;
        }
        
        function createXPAction() {
            alert('Feature wird implementiert...');
        }
        
        function createBadge() {
            alert('Feature wird implementiert...');
        }
        
        function editBadge(badgeId) {
            alert('Feature wird implementiert...');
        }
        
        function manualAwardBadge(badgeId) {
            alert('Feature wird implementiert...');
        }
        
        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>

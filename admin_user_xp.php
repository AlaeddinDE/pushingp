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

$view_user_id = intval($_GET['user_id'] ?? 0);

if (!$view_user_id) {
    header('Location: admin_xp.php');
    exit;
}

// Get user info
$user_info = get_user_level_info($view_user_id);
if (!$user_info) {
    header('Location: admin_xp.php');
    exit;
}

// Get user badges
$user_badges = get_user_badges($view_user_id);

// Get XP history for this user
$xp_history = get_xp_history($view_user_id, 100);

// Get user basic info
$stmt = $conn->prepare("SELECT username, name, email, created_at, last_login, status FROM users WHERE id = ?");
$stmt->bind_param('i', $view_user_id);
$stmt->execute();
$stmt->bind_result($username, $name, $email, $created_at, $last_login, $status);
$stmt->fetch();
$stmt->close();

// Calculate membership days
$membership_days = floor((time() - strtotime($created_at)) / 86400);

// Get streak info
$stmt = $conn->prepare("SELECT login_streak, last_login_date, event_streak, last_event_date FROM user_streaks WHERE user_id = ?");
$stmt->bind_param('i', $view_user_id);
$stmt->execute();
$stmt->bind_result($login_streak, $last_login_date, $event_streak, $last_event_date);
if (!$stmt->fetch()) {
    $login_streak = 0;
    $event_streak = 0;
}
$stmt->close();

// Get activity stats
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM event_participants WHERE mitglied_id = ? AND status = 'coming'");
$stmt->bind_param('i', $view_user_id);
$stmt->execute();
$stmt->bind_result($events_attended);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM events WHERE created_by = ?");
$stmt->bind_param('i', $view_user_id);
$stmt->execute();
$stmt->bind_result($events_created);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM transaktionen WHERE mitglied_id = ? AND typ = 'EINZAHLUNG'");
$stmt->bind_param('i', $view_user_id);
$stmt->execute();
$stmt->bind_result($total_payments);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User XP Details – <?= escape($name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .user-header {
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
            color: white;
        }
        
        .user-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        
        .user-stat {
            background: rgba(255,255,255,0.1);
            padding: 16px;
            border-radius: 12px;
            text-align: center;
        }
        
        .user-stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        
        .user-stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .progress-bar {
            height: 24px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            margin: 16px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.5s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }
        
        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .badge-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .badge-emoji {
            font-size: 3rem;
            margin-bottom: 12px;
        }
        
        .history-list {
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .history-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .xp-gain {
            color: #10b981;
            font-weight: 700;
        }
        
        .xp-loss {
            color: #ef4444;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container" style="max-width: 1200px;">
        <div style="margin-bottom: 24px;">
            <a href="admin_xp.php" style="color: var(--accent); text-decoration: none; font-weight: 600;">← Zurück zum Admin Panel</a>
        </div>
        
        <div class="user-header">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 24px;">
                <div>
                    <h1 style="font-size: 2.5rem; margin-bottom: 8px;"><?= escape($name) ?></h1>
                    <p style="opacity: 0.9;">@<?= escape($username) ?> · Mitglied seit <?= $membership_days ?> Tagen</p>
                </div>
                <div style="text-align: right;">
                    <?php if (!empty($user_info['level_image'])): ?>
                        <img src="<?= $user_info['level_image'] ?>" alt="<?= escape($user_info['current_level']) ?>" style="width: 120px; height: 120px; object-fit: contain; margin-bottom: 12px;">
                    <?php else: ?>
                        <div style="font-size: 3rem; margin-bottom: 8px;"><?= $user_info['level_emoji'] ?></div>
                    <?php endif; ?>
                    <div style="font-size: 1.25rem; font-weight: 700;">Level <?= $user_info['level_id'] ?></div>
                    <div style="opacity: 0.9;"><?= escape($user_info['current_level']) ?></div>
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-weight: 600;"><?= number_format($user_info['xp_total']) ?> XP</span>
                    <span><?= number_format($user_info['xp_in_current_level']) ?> / <?= number_format($user_info['xp_needed_for_next']) ?> XP</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= min(100, $user_info['progress_percent']) ?>%;">
                        <?= round($user_info['progress_percent']) ?>%
                    </div>
                </div>
            </div>
            
            <div class="user-stats-grid">
                <div class="user-stat">
                    <div class="user-stat-value"><?= count($user_badges) ?></div>
                    <div class="user-stat-label">🏅 Badges</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-value"><?= $login_streak ?></div>
                    <div class="user-stat-label">🔥 Login-Streak</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-value"><?= $events_attended ?></div>
                    <div class="user-stat-label">🎉 Events besucht</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-value"><?= $events_created ?></div>
                    <div class="user-stat-label">🎬 Events erstellt</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-value"><?= $total_payments ?></div>
                    <div class="user-stat-label">💰 Zahlungen</div>
                </div>
            </div>
        </div>
        
        <!-- Badges Section -->
        <?php if (count($user_badges) > 0): ?>
        <div class="section" style="margin-bottom: 32px;">
            <div class="section-header">
                <h2 class="section-title">🏅 Errungene Badges</h2>
            </div>
            
            <div class="badge-grid">
                <?php foreach ($user_badges as $badge): ?>
                <div class="badge-card">
                    <div class="badge-emoji"><?= $badge['emoji'] ?></div>
                    <div style="font-weight: 700; margin-bottom: 4px;"><?= escape($badge['title']) ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 8px;">
                        <?= escape($badge['description']) ?>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--accent); font-weight: 600;">
                        +<?= $badge['xp_reward'] ?> XP
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 8px;">
                        Erhalten am <?= date('d.m.Y', strtotime($badge['earned_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- XP History -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">📊 XP Verlauf (letzte 100)</h2>
            </div>
            
            <div class="history-list">
                <?php foreach ($xp_history as $entry): ?>
                <div class="history-item">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; margin-bottom: 4px;">
                            <?= escape($entry['description']) ?>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">
                                <?= escape($entry['action_code']) ?>
                            </code>
                            · <?= date('d.m.Y H:i', strtotime($entry['timestamp'])) ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div class="<?= $entry['xp_change'] > 0 ? 'xp-gain' : 'xp-loss' ?>" style="font-size: 1.25rem;">
                            <?= $entry['xp_change'] > 0 ? '+' : '' ?><?= $entry['xp_change'] ?> XP
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                            <?= number_format($entry['xp_before']) ?> → <?= number_format($entry['xp_after']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Admin Actions -->
        <div class="section" style="margin-top: 32px;">
            <div class="section-header">
                <h2 class="section-title">⚙️ Admin Aktionen</h2>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button class="btn-small btn-primary" onclick="location.href='admin_xp.php'">
                    Zurück zur Übersicht
                </button>
                <button class="btn-small btn-primary" onclick="openAwardXPModal()">
                    + XP vergeben
                </button>
                <button class="btn-small btn-danger" onclick="resetUserXP()">
                    XP zurücksetzen
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function openAwardXPModal() {
            const xp = prompt('Wie viel XP vergeben? (negativ für Abzug)');
            if (!xp) return;
            
            const reason = prompt('Grund für XP-Vergabe:', 'Manuelle Vergabe (Admin)');
            if (!reason) return;
            
            awardXP(parseInt(xp), reason);
        }
        
        async function awardXP(amount, reason) {
            try {
                const response = await fetch('/api/v2/admin_award_xp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        user_id: <?= $view_user_id ?>,
                        xp_amount: amount,
                        reason: reason
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert(`✅ ${amount > 0 ? '+' : ''}${amount} XP vergeben!${data.data.level_up ? '\n🎉 Level Up!' : ''}`);
                    location.reload();
                } else {
                    alert('❌ Fehler: ' + data.error);
                }
            } catch (error) {
                alert('❌ Fehler beim Vergeben von XP');
                console.error(error);
            }
        }
        
        async function resetUserXP() {
            if (!confirm('Wirklich alle XP zurücksetzen?\n\nDies kann nicht rückgängig gemacht werden!')) {
                return;
            }
            
            try {
                const response = await fetch('/api/v2/admin_reset_user_xp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ user_id: <?= $view_user_id ?> })
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
    </script>
</body>
</html>

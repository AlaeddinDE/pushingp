<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin = is_admin();
$is_admin_user = $is_admin;
$page_title = 'Settings';

// Load user data
$stmt = $conn->prepare("SELECT username, name, email, avatar, shift_enabled, bio, discord_tag, notifications_enabled, event_notifications, phone, birthday, team_role, city, two_factor_enabled, email_verified, visibility_status, auto_decline_events FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $name, $email, $avatar, $shift_enabled, $bio, $discord_tag, $notifications_enabled, $event_notifications, $phone, $birthday, $team_role, $city, $two_factor_enabled, $email_verified, $visibility_status, $auto_decline_events);
$stmt->fetch();
$stmt->close();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'profile') {
        $name_new = trim($_POST['name'] ?? '');
        $email_new = trim($_POST['email'] ?? '');
        $avatar_new = trim($_POST['avatar'] ?? '');
        $shift_enabled_new = isset($_POST['shift_enabled']) ? 1 : 0;
        $bio_new = trim($_POST['bio'] ?? '');
        $discord_tag_new = trim($_POST['discord_tag'] ?? '');
        $phone_new = trim($_POST['phone'] ?? '');
        $birthday_new = trim($_POST['birthday'] ?? '');
        $team_role_new = trim($_POST['team_role'] ?? '');
        $city_new = trim($_POST['city'] ?? '');
        
        if (empty($birthday_new)) $birthday_new = null;
        
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, avatar=?, shift_enabled=?, bio=?, discord_tag=?, phone=?, birthday=?, team_role=?, city=? WHERE id=?");
        $stmt->bind_param('ssssssssssi', $name_new, $email_new, $avatar_new, $shift_enabled_new, $bio_new, $discord_tag_new, $phone_new, $birthday_new, $team_role_new, $city_new, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Profil gespeichert!';
        
        // Reload
        header('Location: settings.php?saved=1');
        exit;
    }
    
    if ($action === 'preferences') {
        $notifications_enabled_new = isset($_POST['notifications_enabled']) ? 1 : 0;
        $event_notifications_new = isset($_POST['event_notifications']) ? 1 : 0;
        $auto_decline_events_new = isset($_POST['auto_decline_events']) ? 1 : 0;
        $visibility_status_new = $_POST['visibility_status'] ?? 'online';
        
        $stmt = $conn->prepare("UPDATE users SET notifications_enabled=?, event_notifications=?, auto_decline_events=?, visibility_status=? WHERE id=?");
        $stmt->bind_param('iiisi', $notifications_enabled_new, $event_notifications_new, $auto_decline_events_new, $visibility_status_new, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Einstellungen gespeichert!';
        
        header('Location: settings.php?saved=1');
        exit;
    }
    

    if ($action === 'pin') {
        $new_pin = $_POST['new_pin'] ?? '';
        
        if (!preg_match('/^\d{6}$/', $new_pin)) {
            $error = 'PIN muss aus 6 Ziffern bestehen.';
        } else {
            // Check uniqueness
            $stmt = $conn->prepare("SELECT id FROM users WHERE pin_hash = ? AND id != ?");
            $stmt->bind_param('si', $new_pin, $user_id);
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt->close();
                $error = 'Diese PIN ist bereits vergeben. Bitte wähle eine andere.';
            } else {
                $stmt->close();
                $stmt = $conn->prepare("UPDATE users SET pin_hash=? WHERE id=?");
                $stmt->bind_param('si', $new_pin, $user_id);
                $stmt->execute();
                $stmt->close();
                $success = 'PIN geändert!';
                
                header('Location: settings.php?saved=1');
                exit;
            }
        }
    }
}

if (isset($_GET['saved'])) $success = 'Änderungen gespeichert!';

require_once __DIR__ . '/includes/header.php';
?>
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }
        
        .settings-grid > * {
            animation: slideInLeft 0.6s ease forwards;
        }
        
        .settings-grid > *:nth-child(2) {
            animation: slideInRight 0.6s ease forwards;
            animation-delay: 0.1s;
            opacity: 0;
        }
        
        .settings-grid > *:nth-child(1) {
            opacity: 0;
        }
        
        .settings-grid > *:nth-child(3) {
            animation: slideInLeft 0.6s ease forwards;
            animation-delay: 0.2s;
            opacity: 0;
        }
        
        .settings-grid > *:nth-child(4) {
            animation: slideInRight 0.6s ease forwards;
            animation-delay: 0.3s;
            opacity: 0;
        }
        
        .section {
            position: relative;
        }
        
        .section-header span {
            display: inline-block;
            font-size: 2rem;
            animation: float 3s ease-in-out infinite;
        }
        
        .form-group {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.2s; }
        .form-group:nth-child(2) { animation-delay: 0.3s; }
        .form-group:nth-child(3) { animation-delay: 0.4s; }
        .form-group:nth-child(4) { animation-delay: 0.5s; }
        .form-group:nth-child(5) { animation-delay: 0.6s; }
        .form-group:nth-child(6) { animation-delay: 0.7s; }
        .form-group:nth-child(7) { animation-delay: 0.8s; }
        
        .radio-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .radio-option {
            flex: 1;
            min-width: 120px;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .radio-option:hover {
            border-color: #104186;
            transform: translateY(-2px);
        }
        
        .radio-option input[type="radio"] {
            display: none;
        }
        
        .radio-option input[type="radio"]:checked + label {
            color: #104186;
            font-weight: 600;
        }
        
        .radio-option input[type="radio"]:checked ~ .radio-option {
            border-color: #104186;
            background: rgba(16, 65, 134, 0.1);
        }
        
        @media (max-width: 768px) {
            .settings-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="container">
        <div class="welcome">
            <h1>⚙️ Einstellungen</h1>
            <p class="text-secondary">Verwalte dein Profil und Preferences</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <div class="settings-grid">
            <div class="section">
                <div class="section-header">
                    <span>👤</span>
                    <h2 class="section-title">Profil</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="profile">
                    
                    <div class="form-group">
                        <label>Benutzername</label>
                        <input type="text" value="<?= escape($username) ?>" disabled style="opacity: 0.6;">
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">Der Benutzername kann nicht geändert werden</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?= escape($name) ?>" placeholder="Dein Name">
                    </div>
                    
                    <div class="form-group">
                        <label>E-Mail</label>
                        <input type="email" name="email" value="<?= escape($email) ?>" placeholder="deine@email.de">
                    </div>
                    
                    <div class="form-group">
                        <label>💬 Discord ID</label>
                        <input type="text" name="discord_tag" value="<?= escape($discord_tag) ?>" placeholder="123456789012345678">
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">Deine Discord User-ID für Verknüpfungen</small>
                    </div>
                    
                    <div class="form-group">
                        <label>📱 Telefonnummer</label>
                        <input type="tel" name="phone" value="<?= escape($phone) ?>" placeholder="+49 123 456789">
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">Für Notfälle & direkte Erreichbarkeit</small>
                    </div>
                    
                    <div class="form-group">
                        <label>🎂 Geburtstag</label>
                        <input type="date" name="birthday" value="<?= escape($birthday) ?>">
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">Für Geburtstagswünsche & Team-Events</small>
                    </div>
                    
                    <div class="form-group">
                        <label>🎯 Rolle im Team</label>
                        <select name="team_role" style="padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary); font-size: 1rem; width: 100%;">
                            <option value="">-- Keine Rolle --</option>
                            <option value="Event-Manager" <?= $team_role === 'Event-Manager' ? 'selected' : '' ?>>🎉 Event-Manager</option>
                            <option value="Kassenwart" <?= $team_role === 'Kassenwart' ? 'selected' : '' ?>>💰 Kassenwart</option>
                            <option value="Schichtkoordinator" <?= $team_role === 'Schichtkoordinator' ? 'selected' : '' ?>>📅 Schichtkoordinator</option>
                            <option value="Social Media" <?= $team_role === 'Social Media' ? 'selected' : '' ?>>📱 Social Media</option>
                            <option value="Technik" <?= $team_role === 'Technik' ? 'selected' : '' ?>>🔧 Technik</option>
                            <option value="Member" <?= $team_role === 'Member' ? 'selected' : '' ?>>👤 Member</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>🌍 Stadt/Standort</label>
                        <input type="text" name="city" value="<?= escape($city) ?>" placeholder="z.B. München">
                    </div>
                    
                    <div class="form-group">
                        <label>Avatar URL</label>
                        <input type="url" name="avatar" value="<?= escape($avatar) ?>" placeholder="https://...">
                    </div>
                    
                    <div class="form-group">
                        <label>Bio / Über mich</label>
                        <textarea name="bio" rows="3" placeholder="Erzähl etwas über dich..." style="min-height: 80px; resize: vertical;"><?= escape($bio) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                            <input type="checkbox" name="shift_enabled" value="1" <?= $shift_enabled ? 'checked' : '' ?> style="width: auto; margin: 0;">
                            <span>Ich arbeite in Schichten (im Schichtplan anzeigen)</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn">Profil speichern</button>
                </form>
            </div>

            <div class="section">
                <div class="section-header">
                    <span>🔐</span>
                    <h2 class="section-title">PIN</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="pin">
                    
                    <div class="form-group">
                        <label>Neue PIN (6 Ziffern)</label>
                        <input type="text" name="new_pin" placeholder="123456" maxlength="6" pattern="\d{6}" inputmode="numeric">
                    </div>
                    
                    <button type="submit" class="btn">PIN ändern</button>
                </form>
                
                <div style="margin-top: 24px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;">
                    <p class="text-secondary" style="font-size: 0.875rem;">
                        💡 Wähle eine sichere 6-stellige PIN, die du dir gut merken kannst.
                    </p>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <span>⚙️</span>
                    <h2 class="section-title">Benachrichtigungen & Präferenzen</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="preferences">
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                            <input type="checkbox" name="notifications_enabled" value="1" <?= $notifications_enabled ? 'checked' : '' ?> style="width: auto; margin: 0;">
                            <span>🔔 Benachrichtigungen aktivieren</span>
                        </label>
                        <small style="color: var(--text-secondary); font-size: 0.875rem; margin-left: 28px;">Allgemeine Benachrichtigungen für alle Aktivitäten</small>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                            <input type="checkbox" name="event_notifications" value="1" <?= $event_notifications ? 'checked' : '' ?> style="width: auto; margin: 0;">
                            <span>🎉 Event-Erinnerungen</span>
                        </label>
                        <small style="color: var(--text-secondary); font-size: 0.875rem; margin-left: 28px;">Benachrichtigungen für anstehende Events</small>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                            <input type="checkbox" name="auto_decline_events" value="1" <?= $auto_decline_events ? 'checked' : '' ?> style="width: auto; margin: 0;">
                            <span>🚫 Auto-Ablehnung bei Konflikten</span>
                        </label>
                        <small style="color: var(--text-secondary); font-size: 0.875rem; margin-left: 28px;">Lehne Events automatisch ab, wenn du Schicht hast</small>
                    </div>
                    
                    <div class="form-group">
                        <label>👁️ Sichtbarkeitsstatus</label>
                        <select name="visibility_status" style="padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary); font-size: 1rem; width: 100%;">
                            <option value="online" <?= $visibility_status === 'online' ? 'selected' : '' ?>>🟢 Online - Für alle sichtbar</option>
                            <option value="away" <?= $visibility_status === 'away' ? 'selected' : '' ?>>🟡 Abwesend - Eingeschränkt verfügbar</option>
                            <option value="busy" <?= $visibility_status === 'busy' ? 'selected' : '' ?>>🔴 Beschäftigt - Bitte nicht stören</option>
                            <option value="invisible" <?= $visibility_status === 'invisible' ? 'selected' : '' ?>>⚫ Unsichtbar - Offline erscheinen</option>
                        </select>
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">Wie möchtest du für andere Mitglieder erscheinen?</small>
                    </div>
                    
                    <button type="submit" class="btn">Einstellungen speichern</button>
                </form>
                
                <div style="margin-top: 24px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;">
                    <p class="text-secondary" style="font-size: 0.875rem;">
                        💡 Du kannst einzelne Benachrichtigungen gezielt aktivieren oder deaktivieren.
                    </p>
                </div>
            </div>
            

        </div>
    </div>
    
    <script>
        // Smooth animations on form groups
        document.querySelectorAll('.form-group').forEach((group, index) => {
            group.style.opacity = '0';
            group.style.transform = 'translateY(10px)';
            setTimeout(() => {
                group.style.transition = 'all 0.5s ease';
                group.style.opacity = '1';
                group.style.transform = 'translateY(0)';
            }, index * 50);
        });
        

    </script>
</body>
</html>

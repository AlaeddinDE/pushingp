<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$is_admin = is_admin();

// Load user data
$stmt = $conn->prepare("SELECT username, name, email, avatar, shift_enabled, bio, discord_tag, aktiv_ab, inaktiv_ab, notifications_enabled, theme, language, profile_visible FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $name, $email, $avatar, $shift_enabled, $bio, $discord_tag, $aktiv_ab, $inaktiv_ab, $notifications_enabled, $theme, $language, $profile_visible);
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
        
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, avatar=?, shift_enabled=?, bio=?, discord_tag=? WHERE id=?");
        $stmt->bind_param('sssissi', $name_new, $email_new, $avatar_new, $shift_enabled_new, $bio_new, $discord_tag_new, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Profil gespeichert!';
        
        // Reload
        header('Location: settings.php?saved=1');
        exit;
    }
    
    if ($action === 'preferences') {
        $notifications_enabled_new = isset($_POST['notifications_enabled']) ? 1 : 0;
        $theme_new = $_POST['theme'] ?? 'dark';
        $language_new = $_POST['language'] ?? 'de';
        $profile_visible_new = isset($_POST['profile_visible']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET notifications_enabled=?, theme=?, language=?, profile_visible=? WHERE id=?");
        $stmt->bind_param('issii', $notifications_enabled_new, $theme_new, $language_new, $profile_visible_new, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Einstellungen gespeichert!';
        
        header('Location: settings.php?saved=1');
        exit;
    }
    
    if ($action === 'activity') {
        $aktiv_ab_new = $_POST['aktiv_ab'] ?? null;
        $inaktiv_ab_new = $_POST['inaktiv_ab'] ?? null;
        
        if (empty($aktiv_ab_new)) $aktiv_ab_new = null;
        if (empty($inaktiv_ab_new)) $inaktiv_ab_new = null;
        
        $stmt = $conn->prepare("UPDATE users SET aktiv_ab=?, inaktiv_ab=? WHERE id=?");
        $stmt->bind_param('ssi', $aktiv_ab_new, $inaktiv_ab_new, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Aktivitätszeitraum gespeichert!';
        
        header('Location: settings.php?saved=1');
        exit;
    }
    
    if ($action === 'password') {
        $new_pass = $_POST['new_password'] ?? '';
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Passwort geändert!';
        
        header('Location: settings.php?saved=1');
        exit;
    }
}

if (isset($_GET['saved'])) $success = 'Änderungen gespeichert!';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
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
                <?php if ($is_admin): ?>
                    <a href="admin_kasse.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

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
                        <label>Discord Tag</label>
                        <input type="text" name="discord_tag" value="<?= escape($discord_tag) ?>" placeholder="username#1234">
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
                    <h2 class="section-title">Passwort</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="password">
                    
                    <div class="form-group">
                        <label>Neues Passwort</label>
                        <input type="password" name="new_password" placeholder="Neues Passwort eingeben">
                    </div>
                    
                    <button type="submit" class="btn">Passwort ändern</button>
                </form>
                
                <div style="margin-top: 24px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;">
                    <p class="text-secondary" style="font-size: 0.875rem;">
                        💡 Wähle ein sicheres Passwort mit mindestens 8 Zeichen.
                    </p>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <span>⚙️</span>
                    <h2 class="section-title">Einstellungen</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="preferences">
                    
                    <div class="form-group">
                        <label>Theme</label>
                        <div style="display: flex; gap: 12px;">
                            <label style="flex: 1; padding: 12px; border: 2px solid var(--border); border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.3s ease; <?= $theme === 'dark' ? 'border-color: #104186; background: rgba(16, 65, 134, 0.1);' : '' ?>">
                                <input type="radio" name="theme" value="dark" <?= $theme === 'dark' ? 'checked' : '' ?> style="display: none;">
                                <span style="<?= $theme === 'dark' ? 'color: #104186; font-weight: 600;' : '' ?>">🌙 Dark</span>
                            </label>
                            <label style="flex: 1; padding: 12px; border: 2px solid var(--border); border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.3s ease; <?= $theme === 'light' ? 'border-color: #104186; background: rgba(16, 65, 134, 0.1);' : '' ?>">
                                <input type="radio" name="theme" value="light" <?= $theme === 'light' ? 'checked' : '' ?> style="display: none;">
                                <span style="<?= $theme === 'light' ? 'color: #104186; font-weight: 600;' : '' ?>">☀️ Light</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Sprache</label>
                        <div style="display: flex; gap: 12px;">
                            <label style="flex: 1; padding: 12px; border: 2px solid var(--border); border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.3s ease; <?= $language === 'de' ? 'border-color: #104186; background: rgba(16, 65, 134, 0.1);' : '' ?>">
                                <input type="radio" name="language" value="de" <?= $language === 'de' ? 'checked' : '' ?> style="display: none;">
                                <span style="<?= $language === 'de' ? 'color: #104186; font-weight: 600;' : '' ?>">🇩🇪 Deutsch</span>
                            </label>
                            <label style="flex: 1; padding: 12px; border: 2px solid var(--border); border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.3s ease; <?= $language === 'en' ? 'border-color: #104186; background: rgba(16, 65, 134, 0.1);' : '' ?>">
                                <input type="radio" name="language" value="en" <?= $language === 'en' ? 'checked' : '' ?> style="display: none;">
                                <span style="<?= $language === 'en' ? 'color: #104186; font-weight: 600;' : '' ?>">🇬🇧 English</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                            <input type="checkbox" name="notifications_enabled" value="1" <?= $notifications_enabled ? 'checked' : '' ?> style="width: auto; margin: 0;">
                            <span>🔔 Benachrichtigungen aktivieren</span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                            <input type="checkbox" name="profile_visible" value="1" <?= $profile_visible ? 'checked' : '' ?> style="width: auto; margin: 0;">
                            <span>👁️ Profil für andere sichtbar</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn">Einstellungen speichern</button>
                </form>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <span>📅</span>
                    <h2 class="section-title">Aktivitätszeitraum</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="activity">
                    
                    <div class="form-group">
                        <label>Aktiv ab</label>
                        <input type="date" name="aktiv_ab" value="<?= escape($aktiv_ab) ?>">
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">Ab wann bist du im Team aktiv?</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Inaktiv ab</label>
                        <input type="date" name="inaktiv_ab" value="<?= escape($inaktiv_ab) ?>">
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">Optionales Austrittsdatum</small>
                    </div>
                    
                    <button type="submit" class="btn">Zeitraum speichern</button>
                </form>
                
                <div style="margin-top: 24px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;">
                    <p class="text-secondary" style="font-size: 0.875rem;">
                        💡 Diese Daten helfen bei der Verwaltung und Statistiken.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

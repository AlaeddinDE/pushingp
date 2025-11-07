<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$is_admin = is_admin();

// Load user data
$stmt = $conn->prepare("SELECT name, email, avatar_url, bio, pflicht_monatlich, shift_enabled, shift_mode, shift_start, shift_end FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $avatar_url, $bio, $pflicht_monatlich, $shift_enabled, $shift_mode, $shift_start, $shift_end);
$stmt->fetch();
$stmt->close();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'profile') {
        $name_new = trim($_POST['name'] ?? '');
        $email_new = trim($_POST['email'] ?? '');
        $avatar_new = trim($_POST['avatar_url'] ?? '');
        $bio_new = trim($_POST['bio'] ?? '');
        $pflicht_new = floatval($_POST['pflicht_monatlich'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, avatar_url=?, bio=?, pflicht_monatlich=? WHERE id=?");
        $stmt->bind_param('ssssdi', $name_new, $email_new, $avatar_new, $bio_new, $pflicht_new, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Profil gespeichert!';
        
        // Reload
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
                        <label>Name</label>
                        <input type="text" name="name" value="<?= escape($name) ?>" placeholder="Dein Name">
                    </div>
                    
                    <div class="form-group">
                        <label>E-Mail</label>
                        <input type="email" name="email" value="<?= escape($email) ?>" placeholder="deine@email.de">
                    </div>
                    
                    <div class="form-group">
                        <label>Avatar URL</label>
                        <input type="url" name="avatar_url" value="<?= escape($avatar_url) ?>" placeholder="https://...">
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="4" placeholder="Über dich..."><?= escape($bio) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Monatlicher Pflichtbeitrag (€)</label>
                        <input type="number" step="0.01" name="pflicht_monatlich" value="<?= $pflicht_monatlich ?>" placeholder="0.00">
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
        </div>
    </div>
</body>
</html>

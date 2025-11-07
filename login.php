<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
if (is_logged_in()) { header('Location: dashboard.php'); exit; }
check_rate_limit('login', 5, 300);

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username)) {
        $login_error = 'Bitte Benutzername eingeben';
    } else {
        $stmt = $conn->prepare("SELECT id, username, name, password, role, roles, status FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($user_id, $db_username, $name, $hash, $role, $roles_json, $status);
        if ($stmt->fetch()) {
            $stmt->close();
            if (empty($hash) && $password === '0000') {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $db_username;
                $_SESSION['name'] = $name ?? $db_username;
                $_SESSION['role'] = $role ?? 'user';
                $_SESSION['roles'] = $roles_json ? json_decode($roles_json, true) : [$role ?? 'user'];
                session_regenerate_id(true);
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param('i', $user_id);
                $update_stmt->execute();
                $update_stmt->close();
                header('Location: settings.php?first_login=1');
                exit;
            }
            if (!empty($hash) && password_verify($password, $hash)) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $db_username;
                $_SESSION['name'] = $name ?? $db_username;
                $_SESSION['role'] = $role ?? 'user';
                $_SESSION['roles'] = $roles_json ? json_decode($roles_json, true) : [$role ?? 'user'];
                session_regenerate_id(true);
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param('i', $user_id);
                $update_stmt->execute();
                $update_stmt->close();
                header('Location: dashboard.php');
                exit;
            }
            $login_error = 'Falsches Passwort';
        } else {
            $stmt->close();
            $login_error = 'Benutzer nicht gefunden';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            padding: 20px;
            animation: fadeIn 0.6s ease;
        }
        
        .login-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 48px 40px;
            animation: fadeIn 0.8s ease;
        }
        
        .login-logo {
            font-size: 2rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            text-align: center;
            margin-bottom: 8px;
            animation: fadeIn 1s ease;
        }
        
        .subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9375rem;
            margin-bottom: 40px;
            animation: fadeIn 1.2s ease;
        }
        
        .form-group {
            animation: slideIn 1s ease forwards;
            opacity: 0;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }
        
        button {
            animation: fadeIn 1.4s ease;
        }
        
        .back-link {
            text-align: center;
            margin-top: 24px;
            animation: fadeIn 1.6s ease;
        }
        
        .back-link a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9375rem;
            transition: all 0.2s;
        }
        
        .back-link a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-logo">PUSHING P</h1>
            <p class="subtitle">Crew Platform</p>
            
            <?php if ($login_error): ?>
                <div class="alert alert-error"><?= escape($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autocomplete="username"
                        autofocus
                        placeholder="Dein Username"
                        value="<?= escape($_POST['username'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        autocomplete="current-password"
                        placeholder="••••••••"
                    >
                </div>
                
                <button type="submit" class="btn">Anmelden</button>
            </form>
            
            <div class="back-link">
                <a href="index.php">← Zurück zur Startseite</a>
            </div>
        </div>
    </div>
</body>
</html>

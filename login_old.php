<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
if (is_logged_in()) { header('Location: dashboard.php'); exit; }
// check_rate_limit('login', 5, 300); // Temporarily disabled

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .animated-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 65, 134, 0.15) 0%, transparent 70%);
            filter: blur(40px);
        }

        .circle1 {
            width: 500px;
            height: 500px;
            top: -200px;
            right: -200px;
        }

        .circle2 {
            width: 400px;
            height: 400px;
            bottom: -150px;
            left: -150px;
        }

        .circle3 {
            width: 300px;
            height: 300px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .login-container {
            position: relative;
            z-index: 10;
            max-width: 440px;
            width: 100%;
            padding: 20px;
        }

        .login-card {
            background: rgba(20, 20, 30, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(16, 65, 134, 0.2);
            border-radius: 24px;
            padding: 56px 48px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #104186, #1a5bb8, #104186);
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 48px;
        }

        .login-logo {
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #ffffff 0%, #a0b4d4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.05em;
        }

        .alert {
            margin-bottom: 24px;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 10px;
            letter-spacing: 0.01em;
        }

        .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(16, 65, 134, 0.3);
            border-radius: 12px;
            color: #ffffff;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .form-group input:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #104186;
            box-shadow: 0 0 0 4px rgba(16, 65, 134, 0.1);
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #104186 0%, #1a5bb8 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(16, 65, 134, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(16, 65, 134, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 28px;
        }

        .back-link a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-link a:hover {
            color: #104186;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 40px 32px;
            }

            .login-logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="animated-bg">
        <div class="circle circle1"></div>
        <div class="circle circle2"></div>
        <div class="circle circle3"></div>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <h1 class="login-logo">PUSHING P</h1>
                <p class="subtitle">CREW PLATFORM</p>
            </div>
            
            <?php if ($login_error): ?>
                <div class="alert alert-error"><?= escape($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Benutzername</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autocomplete="username"
                        autofocus
                        placeholder="Username eingeben"
                        value="<?= escape($_POST['username'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    >
                </div>
                
                <button type="submit" class="btn">Anmelden</button>
            </form>
            
            <div class="back-link">
                <a href="index.php">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 12L6 8l4-4"/>
                    </svg>
                    Zurück zur Startseite
                </a>
            </div>
        </div>
    </div>

    <script>
        gsap.registerPlugin();

        const tl = gsap.timeline();
        
        tl.from('.login-card', {
            duration: 1,
            y: 50,
            opacity: 0,
            scale: 0.95,
            ease: 'power3.out'
        })
        .from('.login-logo', {
            duration: 0.8,
            y: -20,
            opacity: 0,
            ease: 'power2.out'
        }, '-=0.6')
        .from('.subtitle', {
            duration: 0.8,
            y: -10,
            opacity: 0,
            ease: 'power2.out'
        }, '-=0.6')
        .from('.form-group', {
            duration: 0.6,
            y: 20,
            opacity: 0,
            stagger: 0.1,
            ease: 'power2.out'
        }, '-=0.4')
        .from('.btn', {
            duration: 0.6,
            y: 20,
            opacity: 0,
            ease: 'power2.out'
        }, '-=0.3')
        .from('.back-link', {
            duration: 0.6,
            opacity: 0,
            ease: 'power2.out'
        }, '-=0.3');

        gsap.to('.circle1', {
            duration: 20,
            x: -100,
            y: 100,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut'
        });

        gsap.to('.circle2', {
            duration: 25,
            x: 100,
            y: -100,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut'
        });

        gsap.to('.circle3', {
            duration: 15,
            scale: 1.2,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut'
        });
    </script>
</body>
</html>

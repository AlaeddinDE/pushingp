<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/xp_system.php';
secure_session_start();
if (is_logged_in()) { header('Location: dashboard.php'); exit; }

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    
    if (empty($pin)) {
        $login_error = 'Bitte PIN eingeben';
    } else {
        $stmt = $conn->prepare("SELECT id, username, name, password, pin_hash, role, roles, status FROM users WHERE pin_hash = ? AND status = 'active'");
        $stmt->bind_param('s', $pin);
        $stmt->execute();
        $stmt->bind_result($user_id, $db_username, $name, $hash, $pin_hash, $role, $roles_json, $status);
        if ($stmt->fetch()) {
            $stmt->close();
            
            $login_success = true;
            
            if ($login_success) {
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
                
                // Update login streak and award daily XP
                update_login_streak($user_id);
                
                // Track login for XP
                require_once __DIR__ . '/includes/xp_system.php';
                track_login_xp($user_id);
                check_profile_completion_xp($user_id);
                check_milestone_badges($user_id);
                
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $stmt->close();
            $login_error = 'Ungültige PIN';
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif;
            background: #000000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* iPhone-style Hintergrund */
        .wallpaper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, #1a1a2e 0%, #0f0f1e 50%, #000000 100%);
            z-index: 0;
        }

        .wallpaper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(16, 65, 134, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(26, 91, 184, 0.1) 0%, transparent 50%);
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 400px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            justify-content: center;
        }

        .time-display {
            text-align: center;
            color: #ffffff;
            margin-bottom: 20px;
            font-weight: 300;
        }

        .time {
            font-size: 4.5rem;
            font-weight: 200;
            letter-spacing: -2px;
            line-height: 1;
        }

        .date {
            font-size: 1rem;
            font-weight: 500;
            margin-top: 8px;
            opacity: 0.9;
        }

        .login-card {
            background: rgba(30, 30, 40, 0.85);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 32px 24px;
            text-align: center;
            margin-top: 20px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
        }

        .logo {
            font-size: 1.375rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.875rem;
            margin-bottom: 32px;
            font-weight: 400;
        }

        .error-message {
            background: rgba(255, 59, 48, 0.15);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #ff453a;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 0.875rem;
            margin-bottom: 24px;
            animation: shake 0.4s;
            font-weight: 500;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .username-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(120, 120, 128, 0.16);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            text-align: center;
            margin-bottom: 28px;
            outline: none;
            transition: all 0.2s;
            font-weight: 400;
            -webkit-appearance: none;
        }

        .username-input:focus {
            background: rgba(120, 120, 128, 0.24);
        }

        .username-input::placeholder {
            color: rgba(235, 235, 245, 0.3);
        }

        .pin-dots {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-bottom: 40px;
        }

        .pin-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .pin-dot.filled {
            background: #ffffff;
            transform: scale(1.15);
            box-shadow: 0 0 16px rgba(255, 255, 255, 0.4);
        }

        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 16px;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .num-btn {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 50%;
            color: #ffffff;
            font-size: 2rem;
            font-weight: 300;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            position: relative;
            padding: 8px;
        }

        .num-btn .number {
            font-size: 2rem;
            font-weight: 300;
            line-height: 1;
        }

        .num-btn .letters {
            font-size: 0.5rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            opacity: 0.5;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .num-btn:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .num-btn:active {
            transform: scale(0.92);
            background: rgba(255, 255, 255, 0.2);
        }

        .num-btn.delete {
            font-size: 1.5rem;
            background: transparent;
        }

        .num-btn.delete:active {
            background: rgba(255, 255, 255, 0.1);
        }

        .back-link {
            margin-top: 28px;
        }

        .back-link a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.9375rem;
            transition: color 0.2s;
            font-weight: 400;
        }

        .back-link a:hover {
            color: rgba(255, 255, 255, 0.8);
        }

        #pinInput {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* iPhone Notch (optional) */
        .notch {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 220px;
            height: 30px;
            background: #000000;
            border-radius: 0 0 20px 20px;
            z-index: 1000;
            display: none; /* Optional anzeigen */
        }

        @media (max-width: 480px) {
            .time {
                font-size: 3.5rem;
            }
            
            .date {
                font-size: 0.9375rem;
            }
            
            .login-card {
                padding: 28px 20px;
            }
            
            .numpad {
                gap: 14px;
                max-width: 280px;
            }
            
            .num-btn .number {
                font-size: 1.75rem;
            }
            
            .num-btn .letters {
                font-size: 0.4375rem;
            }
        }
        
        @media (max-height: 700px) {
            .time {
                font-size: 3rem;
            }
            
            .time-display {
                margin-bottom: 12px;
            }
            
            .login-card {
                margin-top: 12px;
                padding: 24px 20px;
            }
            
            .subtitle {
                margin-bottom: 24px;
            }
            
            .pin-dots {
                margin-bottom: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="wallpaper"></div>
    
    <div class="login-container">
        <div class="time-display">
            <div class="time" id="currentTime">12:34</div>
            <div class="date" id="currentDate">Sonntag, 10. November</div>
        </div>
        
        <form method="POST" id="loginForm">
            <div class="login-card">
                <h1 class="logo">PUSHING P</h1>
                <p class="subtitle">Code eingeben</p>
                
                <?php if ($login_error): ?>
                    <div class="error-message"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                
                <div class="pin-dots">
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                </div>
                
                <input type="password" name="pin" id="pinInput" maxlength="6" autocomplete="off">
                
                <div class="numpad">
                    <button type="button" class="num-btn" data-num="1">
                        <span class="number">1</span>
                    </button>
                    <button type="button" class="num-btn" data-num="2">
                        <span class="number">2</span>
                        <span class="letters">ABC</span>
                    </button>
                    <button type="button" class="num-btn" data-num="3">
                        <span class="number">3</span>
                        <span class="letters">DEF</span>
                    </button>
                    <button type="button" class="num-btn" data-num="4">
                        <span class="number">4</span>
                        <span class="letters">GHI</span>
                    </button>
                    <button type="button" class="num-btn" data-num="5">
                        <span class="number">5</span>
                        <span class="letters">JKL</span>
                    </button>
                    <button type="button" class="num-btn" data-num="6">
                        <span class="number">6</span>
                        <span class="letters">MNO</span>
                    </button>
                    <button type="button" class="num-btn" data-num="7">
                        <span class="number">7</span>
                        <span class="letters">PQRS</span>
                    </button>
                    <button type="button" class="num-btn" data-num="8">
                        <span class="number">8</span>
                        <span class="letters">TUV</span>
                    </button>
                    <button type="button" class="num-btn" data-num="9">
                        <span class="number">9</span>
                        <span class="letters">WXYZ</span>
                    </button>
                    <button type="button" class="num-btn" style="opacity: 0; pointer-events: none;"></button>
                    <button type="button" class="num-btn" data-num="0">
                        <span class="number">0</span>
                    </button>
                    <button type="button" class="num-btn delete" data-action="delete">⌫</button>
                </div>
                
                <div class="back-link">
                    <a href="index.php">← Zurück zur Startseite</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        const pinInput = document.getElementById('pinInput');
        const dots = document.querySelectorAll('.pin-dot');
        const form = document.getElementById('loginForm');
        let currentPin = '';

        // Numpad clicks
        document.querySelectorAll('.num-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const num = btn.getAttribute('data-num');
                const action = btn.getAttribute('data-action');
                
                if (action === 'delete') {
                    currentPin = currentPin.slice(0, -1);
                } else if (num && currentPin.length < 6) {
                    currentPin += num;
                }
                
                updateDots();
                pinInput.value = currentPin;
                
                // Auto-submit bei 6 Ziffern
                if (currentPin.length === 6) {
                    setTimeout(() => form.submit(), 300);
                }
            });
        });

        // Tastatur-Eingabe
        document.addEventListener('keydown', (e) => {
            if (e.key >= '0' && e.key <= '9' && currentPin.length < 6) {
                e.preventDefault();
                currentPin += e.key;
                updateDots();
                pinInput.value = currentPin;
                
                if (currentPin.length === 6) {
                    setTimeout(() => form.submit(), 300);
                }
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                currentPin = currentPin.slice(0, -1);
                updateDots();
                pinInput.value = currentPin;
            } else if (e.key === 'Enter' && currentPin.length === 6) {
                form.submit();
            }
        });

        function updateDots() {
            dots.forEach((dot, i) => {
                if (i < currentPin.length) {
                    dot.classList.add('filled');
                } else {
                    dot.classList.remove('filled');
                }
            });
        }
        
        // Live Clock
        function updateClock() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('currentTime').textContent = hours + ':' + minutes;
            
            const options = { weekday: 'long', day: 'numeric', month: 'long' };
            const dateStr = now.toLocaleDateString('de-DE', options);
            document.getElementById('currentDate').textContent = dateStr;
        }
        
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>

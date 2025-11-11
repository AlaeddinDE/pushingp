<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$balance = 0.0;
$stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();
$casino_available_balance = max(0, $balance - 10.00);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Crash – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); min-height: 100vh; padding: 20px; }
        .game-container { max-width: 1000px; margin: 0 auto; background: var(--bg-primary); border-radius: 24px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .game-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .balance-display { background: var(--bg-secondary); padding: 16px 24px; border-radius: 12px; text-align: center; }
        .balance-value { font-size: 1.5rem; font-weight: 800; color: var(--success); }
        .crash-graph { width: 100%; height: 400px; background: linear-gradient(180deg, #000814, #001d3d); border-radius: 20px; position: relative; border: 4px solid var(--border); overflow: hidden; }
        .multiplier-display { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 5rem; font-weight: 900; color: var(--success); text-shadow: 0 0 30px rgba(16, 185, 129, 0.8); }
        .crashed { color: var(--error) !important; text-shadow: 0 0 30px rgba(239, 68, 68, 0.8) !important; }
        .controls { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 24px; }
        .control-box { background: var(--bg-secondary); padding: 24px; border-radius: 16px; }
        .bet-input { width: 100%; padding: 16px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.125rem; font-weight: 700; text-align: center; margin-bottom: 16px; }
        .start-btn { width: 100%; padding: 18px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 800; font-size: 1.25rem; cursor: pointer; }
        .cashout-btn { width: 100%; padding: 18px; background: linear-gradient(135deg, var(--success), #059669); border: none; border-radius: 12px; color: white; font-weight: 800; font-size: 1.25rem; cursor: pointer; }
        .back-btn { display: inline-block; padding: 12px 24px; background: var(--bg-secondary); border-radius: 12px; color: var(--text-primary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 2.5rem; margin: 16px 0 8px 0;">🚀 Crash</h1>
                <p style="color: var(--text-secondary); margin: 0;">Cashout bevor es crasht!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div class="crash-graph">
            <div class="multiplier-display" id="multiplier">1.00x</div>
            <div style="position: absolute; bottom: 20px; left: 20px; color: white; font-size: 0.875rem;">🚀 Rakete startet...</div>
        </div>

        <div class="controls">
            <div class="control-box">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">💰 Einsatz</label>
                <input type="number" id="betAmount" class="bet-input" min="0.10" max="100" step="0.10" value="1.00">
                <button id="startBtn" class="start-btn" onclick="startCrash()">🚀 Starten</button>
            </div>
            <div class="control-box">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">💰 Aktueller Gewinn</label>
                <div id="currentWin" style="font-size: 2rem; font-weight: 900; color: var(--success); text-align: center; margin-bottom: 16px;">0.00€</div>
                <button id="cashoutBtn" class="cashout-btn" onclick="cashout()" style="display: none;">💰 Auszahlen</button>
            </div>
        </div>

        <div id="resultBox" style="margin-top: 20px;"></div>
    </div>

    <script>
        let balance = <?= $casino_available_balance ?>;
        let gameActive = false;
        let currentMultiplier = 1.0;
        let betAmount = 0;
        let crashPoint = 0;
        let interval = null;

        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + ' €';
        }

        async function startCrash() {
            const bet = parseFloat(document.getElementById('betAmount').value);
            if (bet < 0.10 || bet > balance) { alert('Ungültiger Einsatz!'); return; }

            try {
                const response = await fetch('/api/casino/start_crash.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bet })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    gameActive = true;
                    betAmount = bet;
                    balance -= bet;
                    updateBalance();
                    crashPoint = data.crash_point;
                    currentMultiplier = 1.0;

                    document.getElementById('startBtn').style.display = 'none';
                    document.getElementById('cashoutBtn').style.display = 'block';
                    document.getElementById('resultBox').innerHTML = '';

                    startAnimation();
                } else {
                    alert(data.error);
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        function startAnimation() {
            interval = setInterval(() => {
                if (!gameActive) return;

                currentMultiplier += 0.01;
                document.getElementById('multiplier').textContent = currentMultiplier.toFixed(2) + 'x';
                document.getElementById('currentWin').textContent = (betAmount * currentMultiplier).toFixed(2) + '€';

                if (currentMultiplier >= crashPoint) {
                    crash();
                }
            }, 50);
        }

        async function cashout() {
            if (!gameActive) return;
            clearInterval(interval);
            gameActive = false;

            try {
                const response = await fetch('/api/casino/cashout_crash.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ multiplier: currentMultiplier })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    balance = data.new_balance;
                    updateBalance();
                    document.getElementById('resultBox').innerHTML = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 2px solid var(--success); border-radius: 12px; text-align: center;"><div style="font-size: 3rem;">🎉</div><div style="font-size: 1.5rem; font-weight: 900; color: var(--success); margin-top: 12px;">Gewinn! +${data.win.toFixed(2)}€</div><div style="margin-top: 8px; color: var(--text-secondary);">Ausgecasht bei ${currentMultiplier.toFixed(2)}x</div></div>`;
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }

            reset();
        }

        function crash() {
            clearInterval(interval);
            gameActive = false;
            document.getElementById('multiplier').classList.add('crashed');
            document.getElementById('multiplier').textContent = '💥 CRASHED!';
            document.getElementById('resultBox').innerHTML = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); border: 2px solid var(--error); border-radius: 12px; text-align: center;"><div style="font-size: 3rem;">💥</div><div style="font-size: 1.5rem; font-weight: 900; color: var(--error); margin-top: 12px;">Crashed bei ${crashPoint.toFixed(2)}x</div><div style="margin-top: 8px; color: var(--text-secondary);">Verloren: -${betAmount.toFixed(2)}€</div></div>`;
            reset();
        }

        function reset() {
            setTimeout(() => {
                document.getElementById('startBtn').style.display = 'block';
                document.getElementById('cashoutBtn').style.display = 'none';
                document.getElementById('multiplier').classList.remove('crashed');
                document.getElementById('multiplier').textContent = '1.00x';
                document.getElementById('currentWin').textContent = '0.00€';
            }, 3000);
        }
    </script>
</body>
</html>

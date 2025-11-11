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
    <title>🎯 Plinko – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); min-height: 100vh; padding: 20px; }
        .game-container { max-width: 900px; margin: 0 auto; background: var(--bg-primary); border-radius: 24px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .game-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .balance-display { background: var(--bg-secondary); padding: 16px 24px; border-radius: 12px; text-align: center; }
        .balance-value { font-size: 1.5rem; font-weight: 800; color: var(--success); }
        .plinko-board { width: 100%; max-width: 600px; height: 500px; background: linear-gradient(180deg, #1a1a2e, #16213e); border-radius: 20px; margin: 20px auto; position: relative; border: 4px solid var(--border); }
        .multiplier-row { display: flex; justify-content: space-around; padding: 12px; background: var(--bg-secondary); border-radius: 12px; margin: 20px 0; }
        .multiplier { padding: 12px 20px; border-radius: 8px; font-weight: 800; font-size: 1.125rem; }
        .multiplier.x0-5 { background: linear-gradient(135deg, #ef4444, #b91c1c); color: white; }
        .multiplier.x1 { background: linear-gradient(135deg, #6b7280, #4b5563); color: white; }
        .multiplier.x2 { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .multiplier.x5 { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .controls { background: var(--bg-secondary); padding: 24px; border-radius: 16px; }
        .bet-input { width: 100%; padding: 16px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.25rem; font-weight: 700; text-align: center; margin-bottom: 16px; }
        .drop-btn { width: 100%; padding: 20px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 16px; color: white; font-weight: 900; font-size: 1.5rem; cursor: pointer; }
        .drop-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(139, 92, 246, 0.6); }
        .back-btn { display: inline-block; padding: 12px 24px; background: var(--bg-secondary); border-radius: 12px; color: var(--text-primary); text-decoration: none; font-weight: 600; }
        .back-btn:hover { background: var(--bg-tertiary); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 2.5rem; margin: 16px 0 8px 0;">🎯 Plinko</h1>
                <p style="color: var(--text-secondary); margin: 0;">Ball fällt durch Pins!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div class="plinko-board" id="plinkoBoard">
            <canvas id="plinkoCanvas" width="600" height="500"></canvas>
        </div>

        <div class="multiplier-row">
            <div class="multiplier x0-5">0.5x</div>
            <div class="multiplier x1">1x</div>
            <div class="multiplier x2">2x</div>
            <div class="multiplier x5">5x</div>
            <div class="multiplier x2">2x</div>
            <div class="multiplier x1">1x</div>
            <div class="multiplier x0-5">0.5x</div>
        </div>

        <div class="controls">
            <input type="number" id="betAmount" class="bet-input" min="0.10" max="100" step="0.10" value="1.00" placeholder="Einsatz">
            <button id="dropBtn" class="drop-btn" onclick="dropBall()">⚡ BALL WERFEN</button>
        </div>

        <div id="resultBox" style="margin-top: 16px;"></div>
    </div>

    <script>
        let balance = <?= $casino_available_balance ?>;
        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + ' €';
        }

        async function dropBall() {
            const bet = parseFloat(document.getElementById('betAmount').value);
            if (bet < 0.10 || bet > balance) { alert('Ungültiger Einsatz!'); return; }

            try {
                const response = await fetch('/api/casino/play_plinko.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bet })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    balance = data.new_balance;
                    updateBalance();
                    
                    const resultBox = document.getElementById('resultBox');
                    if (data.win > 0) {
                        resultBox.innerHTML = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 2px solid var(--success); border-radius: 12px; text-align: center; color: var(--success); font-weight: 800; font-size: 1.25rem;">🎉 GEWINN! +${data.win.toFixed(2)}€ (${data.multiplier}x)</div>`;
                    } else {
                        resultBox.innerHTML = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); border: 2px solid var(--error); border-radius: 12px; text-align: center; color: var(--error); font-weight: 800; font-size: 1.25rem;">Verloren: -${bet.toFixed(2)}€</div>`;
                    }
                } else {
                    alert(data.error);
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }
    </script>
</body>
</html>

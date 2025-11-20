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
    <title>🐔 Chicken – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); min-height: 100vh; padding: 20px; }
        .game-container { max-width: 1000px; margin: 0 auto; background: var(--bg-primary); border-radius: 24px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .game-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .balance-display { background: var(--bg-secondary); padding: 16px 24px; border-radius: 12px; text-align: center; }
        .balance-value { font-size: 1.5rem; font-weight: 800; color: var(--success); }
        .road { display: grid; grid-template-columns: repeat(10, 1fr); gap: 8px; margin: 20px 0; }
        .road-tile { aspect-ratio: 1; background: var(--bg-secondary); border: 3px solid var(--border); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 2rem; cursor: pointer; transition: all 0.3s; }
        .road-tile:hover { transform: scale(1.05); border-color: var(--accent); }
        .road-tile.safe { background: linear-gradient(135deg, #10b981, #059669); border-color: var(--success); cursor: default; }
        .road-tile.danger { background: linear-gradient(135deg, #ef4444, #b91c1c); border-color: var(--error); cursor: default; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; background: var(--bg-secondary); padding: 20px; border-radius: 16px; margin-bottom: 20px; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: 900; color: var(--accent); }
        .controls { background: var(--bg-secondary); padding: 24px; border-radius: 16px; }
        .start-btn { width: 100%; padding: 20px; background: linear-gradient(135deg, #f59e0b, #ef4444); border: none; border-radius: 16px; color: white; font-weight: 900; font-size: 1.5rem; cursor: pointer; }
        .cashout-btn { width: 100%; padding: 20px; background: linear-gradient(135deg, var(--success), #059669); border: none; border-radius: 16px; color: white; font-weight: 900; font-size: 1.5rem; cursor: pointer; margin-top: 12px; }
        .back-btn { display: inline-block; padding: 12px 24px; background: var(--bg-secondary); border-radius: 12px; color: var(--text-primary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 2.5rem; margin: 16px 0 8px 0;">🐔 Chicken Cross Road</h1>
                <p style="color: var(--text-secondary); margin: 0;">Überquere die Straßen!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div id="statsSection" class="stats" style="display: none;">
            <div class="stat-item">
                <div style="font-size: 0.875rem; color: var(--text-secondary);">Überquert</div>
                <div class="stat-value" id="crossed">0/10</div>
            </div>
            <div class="stat-item">
                <div style="font-size: 0.875rem; color: var(--text-secondary);">Multiplikator</div>
                <div class="stat-value" id="multiplier">1.00x</div>
            </div>
            <div class="stat-item">
                <div style="font-size: 0.875rem; color: var(--text-secondary);">Potenzial</div>
                <div class="stat-value" id="potential" style="color: var(--success);">0.00€</div>
            </div>
        </div>

        <div class="road" id="roadGrid"></div>

        <div class="controls">
            <div id="configSection">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">💰 Einsatz</label>
                <input type="number" id="betAmount" style="width: 100%; padding: 16px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.125rem; font-weight: 700; text-align: center; margin-bottom: 16px;" min="0.10" max="100" step="0.10" value="1.00">
                <button class="start-btn" onclick="startGame()">🐔 Spiel starten</button>
            </div>
            <button id="cashoutBtn" class="cashout-btn" onclick="cashout()" style="display: none;">💰 Auszahlen: <span id="cashoutAmount">0.00€</span></button>
        </div>

        <div id="resultBox" style="margin-top: 20px;"></div>
    </div>

    <script>
        let balance = <?= $casino_available_balance ?>;
        let gameActive = false;
        let currentBet = 0;
        let crossed = 0;

        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + ' €';
        }

        function createRoad() {
            const grid = document.getElementById('roadGrid');
            grid.innerHTML = '';
            for (let i = 0; i < 10; i++) {
                const tile = document.createElement('div');
                tile.className = 'road-tile';
                tile.id = `road-${i}`;
                tile.innerHTML = '🛣️';
                tile.onclick = () => cross(i);
                grid.appendChild(tile);
            }
        }

        async function startGame() {
            const bet = parseFloat(document.getElementById('betAmount').value);
            if (bet < 0.10 || bet > balance) { alert('Ungültiger Einsatz!'); return; }

            try {
                const response = await fetch('/api/casino/chicken_cross.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'start', bet })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    gameActive = true;
                    currentBet = bet;
                    crossed = 0;
                    balance -= bet;
                    updateBalance();

                    document.getElementById('configSection').style.display = 'none';
                    document.getElementById('statsSection').style.display = 'grid';
                    document.getElementById('resultBox').innerHTML = '';

                    createRoad();
                    updateStats(0, 1.0, 0);
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        async function cross(road) {
            if (!gameActive) return;

            try {
                const response = await fetch('/api/casino/chicken_cross.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cross', road })
                });
                const data = await response.json();

                const tile = document.getElementById(`road-${road}`);
                
                if (data.status === 'hit') {
                    tile.classList.add('danger');
                    tile.innerHTML = '🚗💥';
                    gameActive = false;
                    showResult(false, 0);
                } else if (data.status === 'safe') {
                    tile.classList.add('safe');
                    tile.innerHTML = '✅';
                    crossed++;
                    updateStats(crossed, data.multiplier, data.potential_win);
                    
                    if (crossed > 0) {
                        document.getElementById('cashoutBtn').style.display = 'block';
                        document.getElementById('cashoutAmount').textContent = data.potential_win.toFixed(2) + '€';
                    }

                    if (crossed === 10) {
                        setTimeout(() => cashout(), 500);
                    }
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        async function cashout() {
            if (!gameActive) return;

            try {
                const response = await fetch('/api/casino/chicken_cross.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cashout' })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    gameActive = false;
                    balance = data.new_balance;
                    updateBalance();
                    showResult(true, data.win, data.multiplier);
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        function updateStats(cr, mult, pot) {
            document.getElementById('crossed').textContent = cr + '/10';
            document.getElementById('multiplier').textContent = mult.toFixed(2) + 'x';
            document.getElementById('potential').textContent = pot.toFixed(2) + '€';
        }

        function showResult(won, amount, mult = 0) {
            const resultBox = document.getElementById('resultBox');
            document.getElementById('cashoutBtn').style.display = 'none';

            if (won) {
                const profit = amount - currentBet;
                resultBox.innerHTML = `<div style="padding: 24px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 2px solid var(--success); border-radius: 16px; text-align: center;"><div style="font-size: 4rem;">🎉</div><div style="font-size: 2rem; font-weight: 900; color: var(--success); margin: 12px 0;">Gewinn!</div><div style="font-size: 1.5rem; font-weight: 800; color: var(--success);">+${profit.toFixed(2)}€ (${mult.toFixed(2)}x)</div><button onclick="reset()" style="margin-top: 20px; padding: 12px 32px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer;">✨ Neues Spiel</button></div>`;
            } else {
                resultBox.innerHTML = `<div style="padding: 24px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); border: 2px solid var(--error); border-radius: 16px; text-align: center;"><div style="font-size: 4rem;">💥</div><div style="font-size: 2rem; font-weight: 900; color: var(--error); margin: 12px 0;">Erwischt!</div><div style="font-size: 1.5rem; font-weight: 800; color: var(--error);">-${currentBet.toFixed(2)}€</div><button onclick="reset()" style="margin-top: 20px; padding: 12px 32px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer;">🔄 Nochmal</button></div>`;
            }
        }

        function reset() {
            gameActive = false;
            crossed = 0;
            document.getElementById('configSection').style.display = 'block';
            document.getElementById('statsSection').style.display = 'none';
            document.getElementById('cashoutBtn').style.display = 'none';
            document.getElementById('resultBox').innerHTML = '';
            createRoad();
        }

        createRoad();
    </script>
</body>
</html>

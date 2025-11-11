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
    <title>🃏 Blackjack – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); min-height: 100vh; padding: 20px; }
        .game-container { max-width: 1000px; margin: 0 auto; background: var(--bg-primary); border-radius: 24px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .game-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .balance-display { background: var(--bg-secondary); padding: 16px 24px; border-radius: 12px; text-align: center; }
        .balance-value { font-size: 1.5rem; font-weight: 800; color: var(--success); }
        .card-area { background: linear-gradient(135deg, #0d1b2a, #1b263b); border-radius: 20px; padding: 32px; margin: 20px 0; min-height: 300px; }
        .hand { margin: 20px 0; }
        .hand-label { font-size: 1.125rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 12px; }
        .cards { display: flex; gap: 12px; flex-wrap: wrap; }
        .card { width: 80px; height: 120px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .controls { background: var(--bg-secondary); padding: 24px; border-radius: 16px; }
        .action-btns { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; }
        .action-btn { padding: 16px; border: none; border-radius: 12px; font-weight: 800; font-size: 1.125rem; cursor: pointer; transition: all 0.2s; }
        .hit-btn { background: linear-gradient(135deg, var(--accent), #a855f7); color: white; }
        .stand-btn { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .deal-btn { background: linear-gradient(135deg, #f59e0b, #ef4444); color: white; }
        .back-btn { display: inline-block; padding: 12px 24px; background: var(--bg-secondary); border-radius: 12px; color: var(--text-primary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 2.5rem; margin: 16px 0 8px 0;">🃏 Blackjack</h1>
                <p style="color: var(--text-secondary); margin: 0;">Schlag den Dealer!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div class="card-area">
            <div class="hand">
                <div class="hand-label">🎩 Dealer: <span id="dealerScore">?</span></div>
                <div class="cards" id="dealerCards"></div>
            </div>
            <div class="hand">
                <div class="hand-label">👤 Du: <span id="playerScore">0</span></div>
                <div class="cards" id="playerCards"></div>
            </div>
        </div>

        <div class="controls">
            <label style="display: block; margin-bottom: 8px; font-weight: 600;">💰 Einsatz</label>
            <input type="number" id="betAmount" style="width: 100%; padding: 16px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.125rem; font-weight: 700; text-align: center;" min="0.10" max="100" step="0.10" value="1.00">
            
            <div class="action-btns">
                <button class="action-btn deal-btn" onclick="deal()">🎴 Geben</button>
                <button class="action-btn hit-btn" id="hitBtn" onclick="hit()" style="display: none;">🃏 Karte</button>
                <button class="action-btn stand-btn" id="standBtn" onclick="stand()" style="display: none;">✋ Halten</button>
            </div>
        </div>

        <div id="resultBox" style="margin-top: 20px;"></div>
    </div>

    <script>
        let balance = <?= $casino_available_balance ?>;
        let gameActive = false;

        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + ' €';
        }

        async function deal() {
            const bet = parseFloat(document.getElementById('betAmount').value);
            if (bet < 0.10 || bet > balance) { alert('Ungültiger Einsatz!'); return; }

            try {
                const response = await fetch('/api/casino/play_blackjack.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deal', bet })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    gameActive = true;
                    balance -= bet;
                    updateBalance();
                    renderGame(data);
                    document.getElementById('hitBtn').style.display = 'block';
                    document.getElementById('standBtn').style.display = 'block';
                    document.getElementById('resultBox').innerHTML = '';
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        async function hit() {
            try {
                const response = await fetch('/api/casino/play_blackjack.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'hit' })
                });
                const data = await response.json();
                renderGame(data);
                if (data.game_over) endGame(data);
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        async function stand() {
            try {
                const response = await fetch('/api/casino/play_blackjack.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'stand' })
                });
                const data = await response.json();
                renderGame(data);
                endGame(data);
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        function renderGame(data) {
            document.getElementById('playerCards').innerHTML = data.player_hand.map(c => `<div class="card">${c}</div>`).join('');
            document.getElementById('dealerCards').innerHTML = data.dealer_hand.map(c => `<div class="card">${c}</div>`).join('');
            document.getElementById('playerScore').textContent = data.player_score;
            document.getElementById('dealerScore').textContent = data.dealer_visible ? data.dealer_score : '?';
        }

        function endGame(data) {
            gameActive = false;
            balance = data.new_balance;
            updateBalance();
            document.getElementById('hitBtn').style.display = 'none';
            document.getElementById('standBtn').style.display = 'none';

            const resultBox = document.getElementById('resultBox');
            if (data.result === 'win') {
                resultBox.innerHTML = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 2px solid var(--success); border-radius: 12px; text-align: center; color: var(--success); font-weight: 800; font-size: 1.25rem;">🎉 GEWINN! +${data.win.toFixed(2)}€</div>`;
            } else if (data.result === 'push') {
                resultBox.innerHTML = `<div style="padding: 20px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 12px; text-align: center; color: var(--text-primary); font-weight: 800; font-size: 1.25rem;">🤝 Unentschieden</div>`;
            } else {
                resultBox.innerHTML = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); border: 2px solid var(--error); border-radius: 12px; text-align: center; color: var(--error); font-weight: 800; font-size: 1.25rem;">Verloren</div>`;
            }
        }
    </script>
</body>
</html>

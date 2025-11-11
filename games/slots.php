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
    <title>🎰 Slots – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); min-height: 100vh; padding: 20px; }
        .game-container { max-width: 900px; margin: 0 auto; background: var(--bg-primary); border-radius: 24px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .game-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .balance-display { background: var(--bg-secondary); padding: 16px 24px; border-radius: 12px; text-align: center; }
        .balance-value { font-size: 1.5rem; font-weight: 800; color: var(--success); }
        .slots-reels { display: flex; gap: 20px; justify-content: center; margin: 30px 0; }
        .slot-reel { width: 140px; height: 160px; background: linear-gradient(145deg, #1a0033, #2d0052); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 5rem; border: 6px solid transparent; background-image: linear-gradient(#1a0033, #2d0052), linear-gradient(135deg, #8b5cf6, #ec4899, #f59e0b, #8b5cf6); background-origin: border-box; background-clip: padding-box, border-box; box-shadow: inset 0 0 40px rgba(0,0,0,0.7), 0 15px 40px rgba(0,0,0,0.6); }
        .slot-reel.spinning { animation: slotSpin 0.05s linear infinite; }
        @keyframes slotSpin { 0% { transform: translateY(0); } 100% { transform: translateY(-20px); } }
        .controls { background: var(--bg-secondary); padding: 24px; border-radius: 16px; margin-top: 24px; }
        .bet-input { width: 100%; padding: 16px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.25rem; font-weight: 700; text-align: center; margin-bottom: 16px; }
        .spin-btn { width: 100%; padding: 20px; background: linear-gradient(135deg, #f59e0b, #ef4444); border: none; border-radius: 16px; color: white; font-weight: 900; font-size: 1.5rem; cursor: pointer; transition: all 0.3s; }
        .spin-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(245, 158, 11, 0.6); }
        .spin-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .result-box { margin-top: 20px; padding: 20px; border-radius: 12px; text-align: center; font-size: 1.25rem; font-weight: 800; }
        .result-box.win { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 2px solid var(--success); color: var(--success); }
        .result-box.loss { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); border: 2px solid var(--error); color: var(--error); }
        .back-btn { display: inline-block; padding: 12px 24px; background: var(--bg-secondary); border-radius: 12px; color: var(--text-primary); text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .back-btn:hover { background: var(--bg-tertiary); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 2.5rem; margin: 16px 0 8px 0;">🎰 Slot Machine</h1>
                <p style="color: var(--text-secondary); margin: 0;">Drei gleiche Symbole = Gewinn!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div class="slots-reels">
            <div class="slot-reel" id="reel1">��</div>
            <div class="slot-reel" id="reel2">🍋</div>
            <div class="slot-reel" id="reel3">⭐</div>
        </div>

        <div class="controls">
            <input type="number" id="betAmount" class="bet-input" min="0.10" max="100" step="0.10" value="1.00" placeholder="Einsatz">
            <button id="spinBtn" class="spin-btn" onclick="spin()">🎰 DREHEN</button>
        </div>

        <div id="resultBox"></div>

        <div style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 12px; font-size: 0.875rem; color: var(--text-secondary);">
            <strong>Auszahlungen:</strong><br>
            💎💎💎 = 100x | 7️⃣7️⃣7️⃣ = 50x | ⭐⭐⭐ = 20x<br>
            🍋🍋🍋 = 10x | 🍒🍒🍒 = 5x
        </div>
    </div>

    <script>
        let balance = <?= $casino_available_balance ?>;
        let spinning = false;
        const symbols = ['��', '🍋', '⭐', '7️⃣', '💎'];
        const payouts = { '💎💎💎': 100, '7️⃣7️⃣7️⃣': 50, '⭐⭐⭐': 20, '🍋🍋🍋': 10, '🍒🍒🍒': 5 };

        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + ' €';
        }

        async function spin() {
            if (spinning) return;
            const bet = parseFloat(document.getElementById('betAmount').value);
            if (bet < 0.10 || bet > balance) { alert('Ungültiger Einsatz!'); return; }

            spinning = true;
            document.getElementById('spinBtn').disabled = true;
            document.getElementById('resultBox').innerHTML = '';

            const reels = [document.getElementById('reel1'), document.getElementById('reel2'), document.getElementById('reel3')];
            reels.forEach(r => { r.classList.add('spinning'); r.textContent = '❓'; });

            try {
                const response = await fetch('/api/casino/play_slots.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bet })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    balance = data.new_balance;
                    setTimeout(() => {
                        reels.forEach((r, i) => {
                            r.classList.remove('spinning');
                            r.textContent = data.symbols[i];
                        });

                        const resultBox = document.getElementById('resultBox');
                        if (data.win > 0) {
                            resultBox.innerHTML = `<div class="result-box win">🎉 GEWINN! +${data.win.toFixed(2)}€ (${data.multiplier}x)</div>`;
                        } else {
                            resultBox.innerHTML = `<div class="result-box loss">Verloren: -${bet.toFixed(2)}€</div>`;
                        }
                        updateBalance();
                        spinning = false;
                        document.getElementById('spinBtn').disabled = false;
                    }, 2000);
                } else {
                    alert(data.error);
                    spinning = false;
                    document.getElementById('spinBtn').disabled = false;
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
                spinning = false;
                document.getElementById('spinBtn').disabled = false;
            }
        }
    </script>
</body>
</html>

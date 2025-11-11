<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';

// Get balance
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
    <title>💎 Mines – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .game-container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--bg-primary);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .balance-display {
            background: var(--bg-secondary);
            padding: 16px 24px;
            border-radius: 12px;
            text-align: center;
        }
        
        .balance-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .balance-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--success);
        }
        
        .config-section {
            background: var(--bg-secondary);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        
        .input-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .input-group input {
            width: 100%;
            padding: 12px;
            background: var(--bg-tertiary);
            border: 2px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1.125rem;
            font-weight: 700;
        }
        
        .quick-bet-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        
        .quick-bet-btn {
            padding: 10px 16px;
            background: var(--bg-tertiary);
            border: 2px solid transparent;
            border-radius: 8px;
            color: var(--text-primary);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-bet-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .quick-bet-btn.active {
            background: linear-gradient(135deg, var(--accent), #a855f7);
            border-color: var(--accent);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        
        .start-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 800;
            font-size: 1.125rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .start-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.5);
        }
        
        .stats-section {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(16, 185, 129, 0.1));
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            border: 2px solid var(--border);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            text-align: center;
        }
        
        .stat-item {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 900;
            margin-top: 4px;
        }
        
        .mines-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 24px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .mine-tile {
            aspect-ratio: 1;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.2));
            border: 3px solid rgba(139, 92, 246, 0.4);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .mine-tile:hover:not(.revealed) {
            transform: scale(1.05);
            border-color: var(--accent);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.5);
        }
        
        .mine-tile.revealed {
            cursor: default;
        }
        
        .cashout-btn {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, var(--success), #059669);
            border: none;
            border-radius: 16px;
            color: white;
            font-weight: 900;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
            transition: all 0.3s;
        }
        
        .cashout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(16, 185, 129, 0.6);
        }
        
        .info-box {
            margin-top: 24px;
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 12px;
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .result-box {
            padding: 24px;
            border-radius: 16px;
            text-align: center;
            margin-top: 16px;
            animation: slideIn 0.5s ease-out;
        }
        
        .result-box.win {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border: 2px solid var(--success);
        }
        
        .result-box.loss {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2));
            border: 2px solid var(--error);
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes mineExplode {
            0% { transform: scale(1); }
            50% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        @keyframes diamondSparkle {
            0%, 100% { transform: scale(1); filter: brightness(1); }
            50% { transform: scale(1.2); filter: brightness(1.5); }
        }
        
        .back-btn {
            display: inline-block;
            padding: 12px 24px;
            background: var(--bg-secondary);
            border-radius: 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: var(--bg-tertiary);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück zum Casino</a>
                <h1 style="font-size: 2.5rem; margin: 16px 0 8px 0;">💎 Mines</h1>
                <p style="color: var(--text-secondary); margin: 0;">Finde Diamanten, vermeide Minen! Mathematisch fair (RTP 96%)</p>
            </div>
            <div class="balance-display">
                <div class="balance-label">Verfügbares Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <!-- Configuration -->
        <div id="configSection" class="config-section">
            <div class="input-group">
                <div>
                    <label>💰 Einsatz</label>
                    <input type="number" id="betAmount" min="0.10" max="1000" step="0.10" value="1.00">
                </div>
                <div>
                    <label>💣 Anzahl Minen (1-24)</label>
                    <input type="number" id="mineCount" min="1" max="24" step="1" value="3">
                </div>
            </div>
            
            <div class="quick-bet-btns">
                <button class="quick-bet-btn" onclick="setBet(0.50)">0.50€</button>
                <button class="quick-bet-btn active" onclick="setBet(1.00)">1.00€</button>
                <button class="quick-bet-btn" onclick="setBet(5.00)">5.00€</button>
                <button class="quick-bet-btn" onclick="setBet(10.00)">10.00€</button>
                <button class="quick-bet-btn" onclick="setBet(25.00)">25.00€</button>
            </div>

            <button class="start-btn" onclick="startGame()">🎮 Spiel starten</button>
        </div>

        <!-- Game Stats -->
        <div id="statsSection" class="stats-section" style="display: none;">
            <div class="stats-grid">
                <div class="stat-item">
                    <div>💎 Aufgedeckt</div>
                    <div class="stat-value" id="revealed" style="color: var(--success);">0</div>
                </div>
                <div class="stat-item">
                    <div>📈 Multiplikator</div>
                    <div class="stat-value" id="multiplier" style="color: var(--accent);">1.00x</div>
                </div>
                <div class="stat-item">
                    <div>💰 Potenzial</div>
                    <div class="stat-value" id="potential" style="color: var(--success);">0.00€</div>
                </div>
            </div>
        </div>

        <!-- Mines Grid -->
        <div id="minesGrid" class="mines-grid"></div>

        <!-- Cashout Button -->
        <button id="cashoutBtn" class="cashout-btn" style="display: none;" onclick="cashout()">
            💰 Auszahlen: <span id="cashoutAmount">0.00€</span>
        </button>

        <!-- Info Box -->
        <div class="info-box">
            <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">📊 Spielmechanik:</div>
            • <strong>Mathematisch Fair:</strong> Jedes Feld hat berechenbare Wahrscheinlichkeiten<br>
            • <strong>1. Klick bei 3 Minen:</strong> 88% sicher (22/25), 12% Mine (3/25)<br>
            • <strong>Multiplikator:</strong> Steigt mit jedem sicheren Feld dynamisch<br>
            • <strong>RTP 96%:</strong> Faire Quoten mit 4% House Edge<br>
            • <strong>Strategie:</strong> Je mehr Minen, desto höher die Multiplikatoren!
        </div>

        <div id="resultBox"></div>
    </div>

    <script>
        let userBalance = <?= $casino_available_balance ?>;
        let gameActive = false;
        let currentBet = 0;
        let revealed = [];

        function setBet(amount) {
            document.getElementById('betAmount').value = amount.toFixed(2);
            document.querySelectorAll('.quick-bet-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function updateBalance() {
            document.getElementById('balance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
        }

        function createGrid() {
            const grid = document.getElementById('minesGrid');
            grid.innerHTML = '';
            
            for (let i = 0; i < 25; i++) {
                const tile = document.createElement('div');
                tile.className = 'mine-tile';
                tile.id = `tile-${i}`;
                tile.innerHTML = '<div style="opacity: 0;">?</div>';
                tile.onclick = () => revealTile(i);
                grid.appendChild(tile);
            }
        }

        async function startGame() {
            const bet = parseFloat(document.getElementById('betAmount').value);
            const mines = parseInt(document.getElementById('mineCount').value);

            if (bet < 0.10 || bet > userBalance) {
                alert('Ungültiger Einsatz!');
                return;
            }

            if (mines < 1 || mines > 24) {
                alert('Wähle 1-24 Minen!');
                return;
            }

            try {
                const response = await fetch('/api/casino/play_mines.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'start', bet, mines })
                });

                const data = await response.json();

                if (data.status === 'success') {
                    gameActive = true;
                    currentBet = bet;
                    revealed = [];
                    userBalance -= bet;
                    updateBalance();

                    document.getElementById('configSection').style.display = 'none';
                    document.getElementById('statsSection').style.display = 'block';
                    document.getElementById('resultBox').innerHTML = '';

                    createGrid();
                    updateStats(0, 1.0, 0);
                } else {
                    alert(data.error);
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        async function revealTile(position) {
            if (!gameActive || revealed.includes(position)) return;

            try {
                const response = await fetch('/api/casino/play_mines.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reveal', position })
                });

                const data = await response.json();
                const tile = document.getElementById(`tile-${position}`);

                if (data.status === 'mine_hit') {
                    // BOOM
                    tile.innerHTML = '💣';
                    tile.style.background = 'linear-gradient(135deg, #ef4444, #b91c1c)';
                    tile.style.borderColor = '#ef4444';
                    tile.style.animation = 'mineExplode 0.5s ease-out';
                    tile.classList.add('revealed');
                    gameActive = false;

                    setTimeout(() => {
                        data.mine_positions.forEach(pos => {
                            if (pos !== position) {
                                const mineTile = document.getElementById(`tile-${pos}`);
                                mineTile.innerHTML = '💣';
                                mineTile.style.background = 'linear-gradient(135deg, rgba(239, 68, 68, 0.3), rgba(185, 28, 28, 0.3))';
                                mineTile.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                                mineTile.classList.add('revealed');
                            }
                        });
                        showResult(false, 0);
                    }, 800);

                } else if (data.status === 'safe') {
                    // Diamond
                    tile.innerHTML = '💎';
                    tile.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                    tile.style.borderColor = '#10b981';
                    tile.style.animation = 'diamondSparkle 0.6s ease-out';
                    tile.classList.add('revealed');
                    revealed.push(position);

                    updateStats(data.revealed_count, data.current_multiplier, data.potential_win);

                    if (data.revealed_count > 0) {
                        document.getElementById('cashoutBtn').style.display = 'block';
                        document.getElementById('cashoutAmount').textContent = data.potential_win.toFixed(2) + '€';
                    }

                    if (data.all_revealed) {
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
                const response = await fetch('/api/casino/play_mines.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cashout' })
                });

                const data = await response.json();

                if (data.status === 'cashout') {
                    gameActive = false;
                    userBalance += data.win_amount;
                    updateBalance();

                    data.mine_positions.forEach(pos => {
                        if (!revealed.includes(pos)) {
                            const mineTile = document.getElementById(`tile-${pos}`);
                            mineTile.innerHTML = '💣';
                            mineTile.style.background = 'linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2))';
                            mineTile.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                            mineTile.classList.add('revealed');
                        }
                    });

                    showResult(true, data.win_amount, data.revealed, data.multiplier);
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        function updateStats(rev, mult, pot) {
            document.getElementById('revealed').textContent = rev;
            document.getElementById('multiplier').textContent = mult.toFixed(3) + 'x';
            document.getElementById('potential').textContent = pot.toFixed(2) + '€';
        }

        function showResult(won, amount, rev = 0, mult = 0) {
            const resultBox = document.getElementById('resultBox');
            document.getElementById('cashoutBtn').style.display = 'none';

            if (won) {
                const profit = amount - currentBet;
                resultBox.innerHTML = `
                    <div class="result-box win">
                        <div style="font-size: 4rem; margin-bottom: 16px;">🎉</div>
                        <div style="font-size: 2rem; font-weight: 900; color: var(--success); margin-bottom: 12px;">Gewinn!</div>
                        <div style="font-size: 1.25rem; margin-bottom: 8px;">
                            Multiplikator: <span style="color: var(--accent); font-weight: 800;">${mult.toFixed(3)}x</span>
                        </div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);">+${profit.toFixed(2)}€</div>
                        <div style="margin-top: 16px; font-size: 0.875rem; color: var(--text-secondary);">
                            ${rev} sichere Felder aufgedeckt!
                        </div>
                        <button onclick="reset()" style="margin-top: 20px; padding: 12px 32px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer;">
                            ✨ Neues Spiel
                        </button>
                    </div>
                `;
            } else {
                resultBox.innerHTML = `
                    <div class="result-box loss">
                        <div style="font-size: 4rem; margin-bottom: 16px;">💥</div>
                        <div style="font-size: 2rem; font-weight: 900; color: var(--error); margin-bottom: 12px;">BOOM!</div>
                        <div style="font-size: 1.125rem; color: var(--text-secondary); margin-bottom: 8px;">Mine getroffen!</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--error);">-${currentBet.toFixed(2)}€</div>
                        <div style="margin-top: 16px; font-size: 0.875rem; color: var(--text-secondary);">
                            ${revealed.length} Felder aufgedeckt
                        </div>
                        <button onclick="reset()" style="margin-top: 20px; padding: 12px 32px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer;">
                            🔄 Nochmal versuchen
                        </button>
                    </div>
                `;
            }
        }

        function reset() {
            gameActive = false;
            currentBet = 0;
            revealed = [];
            document.getElementById('configSection').style.display = 'block';
            document.getElementById('statsSection').style.display = 'none';
            document.getElementById('cashoutBtn').style.display = 'none';
            document.getElementById('resultBox').innerHTML = '';
            document.getElementById('minesGrid').innerHTML = '';
        }

        // Initialize
        createGrid();
    </script>
</body>
</html>

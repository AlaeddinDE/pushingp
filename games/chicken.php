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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>🐔 Chicken – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); 
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        
        .game-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* HEADER */
        .header {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .back-btn {
            padding: 10px 20px;
            background: var(--bg-secondary);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .balance-display {
            text-align: right;
        }
        
        .balance-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .balance-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--success);
        }
        
        /* MAIN LAYOUT */
        .game-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        
        /* GAME BOARD */
        .game-board {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }
        
        .board-title {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .chicken-grid {
            display: flex;
            flex-direction: column-reverse;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .grid-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            position: relative;
        }
        
        .row-label {
            position: absolute;
            left: -50px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--text-secondary);
        }
        
        .grid-tile {
            aspect-ratio: 1;
            background: var(--bg-secondary);
            border: 3px solid var(--border);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .grid-tile::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.1));
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .grid-tile:hover:not(.revealed):not(.disabled) {
            transform: translateY(-4px);
            border-color: var(--accent);
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
        }
        
        .grid-tile:hover:not(.revealed):not(.disabled)::before {
            opacity: 1;
        }
        
        .grid-tile.revealed {
            cursor: default;
        }
        
        .grid-tile.revealed.safe {
            background: linear-gradient(135deg, #10b981, #059669);
            border-color: var(--success);
            animation: tileReveal 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .grid-tile.revealed.danger {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            border-color: var(--error);
            animation: tileShake 0.5s;
        }
        
        .grid-tile.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        @keyframes tileReveal {
            0% { transform: scale(0.8) rotateY(0deg); }
            50% { transform: scale(1.1) rotateY(180deg); }
            100% { transform: scale(1) rotateY(360deg); }
        }
        
        @keyframes tileShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px) rotate(-5deg); }
            75% { transform: translateX(10px) rotate(5deg); }
        }
        
        /* CONTROLS PANEL */
        .controls-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .stats-box {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .stat-row:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--accent);
        }
        
        .stat-value.big {
            font-size: 1.75rem;
            color: var(--success);
        }
        
        .controls-box {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .control-label {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .bet-input {
            width: 100%;
            padding: 16px;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 12px;
        }
        
        .bet-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .quick-bets {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .quick-bet {
            padding: 10px;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-bet:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        
        .action-btn {
            width: 100%;
            padding: 20px;
            border: none;
            border-radius: 12px;
            font-size: 1.25rem;
            font-weight: 900;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .start-btn {
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            color: white;
        }
        
        .cashout-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            margin-top: 12px;
            display: none;
        }
        
        .cashout-btn.active {
            display: block;
        }
        
        /* RESULT MODAL */
        .result-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .result-modal.active {
            display: flex;
        }
        
        .result-content {
            background: var(--bg-primary);
            padding: 48px;
            border-radius: 24px;
            text-align: center;
            max-width: 450px;
            animation: modalPop 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes modalPop {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .result-icon {
            font-size: 6rem;
            margin-bottom: 24px;
        }
        
        .result-title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 16px;
        }
        
        .result-amount {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 32px;
        }
        
        /* MOBILE */
        @media (max-width: 1024px) {
            .game-layout {
                grid-template-columns: 1fr;
            }
            
            .row-label {
                left: -35px;
                font-size: 1rem;
            }
            
            .game-board {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <!-- HEADER -->
        <div class="header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 2rem; font-weight: 900; margin-top: 8px;">🐔 Chicken</h1>
            </div>
            <div class="balance-display">
                <div class="balance-label">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?>€</div>
            </div>
        </div>
        
        <!-- GAME LAYOUT -->
        <div class="game-layout">
            <!-- GAME BOARD -->
            <div class="game-board">
                <div class="board-title">🐔 Wähle dein Feld</div>
                <div class="chicken-grid" id="chickenGrid"></div>
            </div>
            
            <!-- CONTROLS -->
            <div class="controls-panel">
                <!-- STATS -->
                <div class="stats-box">
                    <div class="stat-row">
                        <span class="stat-label">🎯 Einsatz</span>
                        <span class="stat-value" id="currentBet">0.00€</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">🔢 Reihe</span>
                        <span class="stat-value" id="currentRow">0/5</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">📊 Multiplikator</span>
                        <span class="stat-value" id="multiplier">1.00x</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">💰 Gewinn</span>
                        <span class="stat-value big" id="potentialWin">0.00€</span>
                    </div>
                </div>
                
                <!-- CONTROLS -->
                <div class="controls-box" id="betControls">
                    <label class="control-label">💰 Einsatz</label>
                    <input type="number" id="betInput" class="bet-input" min="0.10" max="100" step="0.10" value="1.00">
                    
                    <div class="quick-bets">
                        <button class="quick-bet" onclick="setBet(1)">1€</button>
                        <button class="quick-bet" onclick="setBet(5)">5€</button>
                        <button class="quick-bet" onclick="setBet(10)">10€</button>
                        <button class="quick-bet" onclick="setBet(20)">20€</button>
                    </div>
                    
                    <button class="action-btn start-btn" onclick="startGame()">🐔 Starten</button>
                </div>
                
                <button class="action-btn cashout-btn" id="cashoutBtn" onclick="cashout()">
                    💰 Auszahlen: <span id="cashoutAmount">0.00€</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- RESULT MODAL -->
    <div class="result-modal" id="resultModal">
        <div class="result-content">
            <div class="result-icon" id="resultIcon">🎉</div>
            <div class="result-title" id="resultTitle">Gewonnen!</div>
            <div class="result-amount" id="resultAmount">+0.00€</div>
            <button class="action-btn start-btn" onclick="resetGame()">🔄 Neues Spiel</button>
        </div>
    </div>

    <script>
        let balance = <?= $casino_available_balance ?>;
        let gameActive = false;
        let currentBet = 0;
        let currentRow = 0;
        let gameState = [];
        
        const ROWS = 5;
        const COLS = 3;
        const DANGER_CHANCE = 0.33; // 1 aus 3 ist gefährlich
        
        function setBet(amount) {
            document.getElementById('betInput').value = amount.toFixed(2);
        }
        
        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + '€';
        }
        
        function createGrid() {
            const grid = document.getElementById('chickenGrid');
            grid.innerHTML = '';
            
            for (let row = 0; row < ROWS; row++) {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'grid-row';
                rowDiv.innerHTML = `<div class="row-label">${row + 1}</div>`;
                
                for (let col = 0; col < COLS; col++) {
                    const tile = document.createElement('div');
                    tile.className = 'grid-tile';
                    tile.dataset.row = row;
                    tile.dataset.col = col;
                    tile.innerHTML = '🥚';
                    tile.onclick = () => selectTile(row, col);
                    rowDiv.appendChild(tile);
                }
                
                grid.appendChild(rowDiv);
            }
        }
        
        async function startGame() {
            const bet = parseFloat(document.getElementById('betInput').value);
            
            if (bet < 0.10 || bet > balance) {
                alert('Ungültiger Einsatz!');
                return;
            }
            
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
                    currentRow = 0;
                    balance -= bet;
                    updateBalance();
                    
                    // Generate game state
                    gameState = [];
                    for (let row = 0; row < ROWS; row++) {
                        const dangerPos = Math.floor(Math.random() * COLS);
                        gameState.push(dangerPos);
                    }
                    
                    document.getElementById('betControls').style.display = 'none';
                    document.getElementById('currentBet').textContent = bet.toFixed(2) + '€';
                    
                    createGrid();
                    updateStats();
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }
        
        async function selectTile(row, col) {
            if (!gameActive || row !== currentRow) return;
            
            const tile = document.querySelector(`[data-row="${row}"][data-col="${col}"]`);
            if (tile.classList.contains('revealed')) return;
            
            // Disable all tiles in current row
            document.querySelectorAll(`[data-row="${row}"]`).forEach(t => {
                t.classList.add('disabled');
                t.style.pointerEvents = 'none';
            });
            
            try {
                const response = await fetch('/api/casino/chicken_cross.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cross', road: row })
                });
                const data = await response.json();
                
                // Check if safe
                const isDanger = col === gameState[row];
                
                setTimeout(() => {
                    tile.classList.add('revealed');
                    
                    if (isDanger) {
                        // Hit danger
                        tile.classList.add('danger');
                        tile.innerHTML = '💀';
                        
                        // Reveal all dangers in this row
                        document.querySelectorAll(`[data-row="${row}"]`).forEach((t, i) => {
                            if (i === gameState[row]) {
                                setTimeout(() => {
                                    t.classList.add('revealed', 'danger');
                                    t.innerHTML = '💀';
                                }, 200);
                            }
                        });
                        
                        gameOver(false);
                    } else {
                        // Safe
                        tile.classList.add('safe');
                        tile.innerHTML = '🐔';
                        
                        if (data.status === 'safe') {
                            currentRow++;
                            updateStats();
                            
                            document.getElementById('cashoutBtn').classList.add('active');
                            document.getElementById('cashoutAmount').textContent = data.potential_win.toFixed(2) + '€';
                            
                            // Enable next row
                            if (currentRow < ROWS) {
                                document.querySelectorAll(`[data-row="${currentRow}"]`).forEach(t => {
                                    t.classList.remove('disabled');
                                    t.style.pointerEvents = 'auto';
                                });
                            } else {
                                // All rows completed
                                setTimeout(() => cashout(), 1000);
                            }
                        }
                    }
                }, 300);
                
            } catch (error) {
                console.error('Error:', error);
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
        
        function gameOver(won) {
            gameActive = false;
            showResult(won, 0, 0);
        }
        
        function updateStats() {
            document.getElementById('currentRow').textContent = currentRow + '/' + ROWS;
            
            const multiplier = Math.pow(1.5, currentRow);
            const potential = currentBet * multiplier;
            
            document.getElementById('multiplier').textContent = multiplier.toFixed(2) + 'x';
            document.getElementById('potentialWin').textContent = potential.toFixed(2) + '€';
        }
        
        function showResult(won, amount, mult) {
            const modal = document.getElementById('resultModal');
            const icon = document.getElementById('resultIcon');
            const title = document.getElementById('resultTitle');
            const amountEl = document.getElementById('resultAmount');
            
            if (won) {
                const profit = amount - currentBet;
                icon.textContent = '🎉';
                title.textContent = 'Gewonnen!';
                title.style.color = 'var(--success)';
                amountEl.textContent = '+' + profit.toFixed(2) + '€ (' + mult.toFixed(2) + 'x)';
                amountEl.style.color = 'var(--success)';
            } else {
                icon.textContent = '💀';
                title.textContent = 'Verloren!';
                title.style.color = 'var(--error)';
                amountEl.textContent = '-' + currentBet.toFixed(2) + '€';
                amountEl.style.color = 'var(--error)';
            }
            
            modal.classList.add('active');
        }
        
        function resetGame() {
            document.getElementById('resultModal').classList.remove('active');
            document.getElementById('betControls').style.display = 'block';
            document.getElementById('cashoutBtn').classList.remove('active');
            
            gameActive = false;
            currentRow = 0;
            gameState = [];
            
            createGrid();
        }
        
        // Initialize
        createGrid();
    </script>
</body>
</html>

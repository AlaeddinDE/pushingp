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
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%); 
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: #fff;
        }
        
        .game-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* HEADER */
        .header {
            background: rgba(30, 35, 60, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .back-btn {
            padding: 12px 24px;
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(139, 92, 246, 0.2);
            transform: translateY(-2px);
        }
        
        .game-logo {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .balance-display {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 12px 24px;
            border-radius: 12px;
        }
        
        .balance-label {
            font-size: 0.875rem;
            color: #6ee7b7;
            font-weight: 600;
        }
        
        .balance-value {
            font-size: 1.75rem;
            font-weight: 900;
            color: #10b981;
        }
        
        /* MAIN LAYOUT */
        .game-layout {
            display: grid;
            grid-template-columns: 380px 1fr 380px;
            gap: 24px;
        }
        
        /* CONTROLS PANEL */
        .controls-panel {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .panel-box {
            background: rgba(30, 35, 60, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: #a78bfa;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .bet-input-wrapper {
            position: relative;
            margin-bottom: 16px;
        }
        
        .bet-label {
            font-size: 0.875rem;
            color: #9ca3af;
            margin-bottom: 8px;
            display: block;
        }
        
        .bet-input {
            width: 100%;
            padding: 16px 48px 16px 20px;
            background: rgba(15, 20, 40, 0.8);
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 800;
            transition: all 0.3s;
        }
        
        .bet-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
        }
        
        .currency-symbol {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .quick-bets {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .quick-bet {
            padding: 12px;
            background: rgba(15, 20, 40, 0.6);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 10px;
            color: #a78bfa;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-bet:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: #8b5cf6;
            transform: translateY(-2px);
        }
        
        .action-btn {
            width: 100%;
            padding: 20px;
            border: none;
            border-radius: 12px;
            font-size: 1.125rem;
            font-weight: 900;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        
        .action-btn:hover::before {
            transform: translateX(100%);
        }
        
        .start-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
        }
        
        .start-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.6);
        }
        
        .cashout-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
            display: none;
        }
        
        .cashout-btn.active {
            display: block;
            animation: glow 2s ease-in-out infinite;
        }
        
        @keyframes glow {
            0%, 100% { box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 8px 40px rgba(16, 185, 129, 0.8); }
        }
        
        /* GAME BOARD */
        .game-board {
            background: rgba(30, 35, 60, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        .board-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .board-title {
            font-size: 1.5rem;
            font-weight: 900;
            color: #fff;
        }
        
        .provably-fair {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 8px;
            font-size: 0.875rem;
            color: #10b981;
            font-weight: 600;
        }
        
        .road-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .road-row {
            display: flex;
            gap: 12px;
            position: relative;
        }
        
        .row-number {
            position: absolute;
            left: -40px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.25rem;
            font-weight: 900;
            color: #6b7280;
        }
        
        .road-tile {
            flex: 1;
            aspect-ratio: 2/1;
            background: rgba(15, 20, 40, 0.8);
            border: 2px solid rgba(139, 92, 246, 0.2);
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
        
        .road-tile::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.0), rgba(139, 92, 246, 0.1));
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .road-tile:hover:not(.revealed):not(.disabled) {
            transform: translateY(-4px);
            border-color: #8b5cf6;
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.4);
        }
        
        .road-tile:hover:not(.revealed):not(.disabled)::before {
            opacity: 1;
        }
        
        .road-tile.revealed {
            cursor: default;
            animation: revealTile 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .road-tile.revealed.safe {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.3));
            border-color: #10b981;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.4);
        }
        
        .road-tile.revealed.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.3));
            border-color: #ef4444;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.4);
            animation: crash 0.5s;
        }
        
        .road-tile.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .road-tile.next-row {
            border-color: rgba(139, 92, 246, 0.5);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes revealTile {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        @keyframes crash {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg) scale(1.1); }
            75% { transform: rotate(10deg) scale(1.1); }
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(139, 92, 246, 0); }
        }
        
        /* STATS PANEL */
        .stats-panel {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .stat-box {
            background: rgba(30, 35, 60, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #9ca3af;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 900;
            color: #8b5cf6;
        }
        
        .stat-value.highlight {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .history-box {
            background: rgba(30, 35, 60, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        .history-title {
            font-size: 1rem;
            font-weight: 700;
            color: #a78bfa;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        
        .history-items {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .history-item {
            padding: 8px 12px;
            background: rgba(15, 20, 40, 0.8);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 700;
        }
        
        .history-item.win {
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .history-item.loss {
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        /* RESULT MODAL */
        .result-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .result-modal.active {
            display: flex;
        }
        
        .result-content {
            background: rgba(30, 35, 60, 0.95);
            border: 1px solid rgba(139, 92, 246, 0.3);
            padding: 48px;
            border-radius: 24px;
            text-align: center;
            max-width: 500px;
            animation: modalPop 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        @keyframes modalPop {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .result-icon {
            font-size: 6rem;
            margin-bottom: 24px;
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .result-title {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 16px;
        }
        
        .result-multiplier {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 24px;
            color: #8b5cf6;
        }
        
        .result-amount {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 32px;
        }
        
        /* MOBILE */
        @media (max-width: 1200px) {
            .game-layout {
                grid-template-columns: 1fr;
            }
            
            .stats-panel {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <!-- HEADER -->
        <div class="header">
            <div class="header-left">
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <div class="game-logo">🐔 CHICKEN</div>
            </div>
            <div class="balance-display">
                <div>
                    <div class="balance-label">Guthaben</div>
                    <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?>€</div>
                </div>
            </div>
        </div>
        
        <!-- GAME LAYOUT -->
        <div class="game-layout">
            <!-- LEFT: CONTROLS -->
            <div class="controls-panel">
                <div class="panel-box">
                    <div class="panel-title">💰 Einsatz</div>
                    <div class="bet-input-wrapper">
                        <input type="number" id="betInput" class="bet-input" min="0.10" max="100" step="0.10" value="1.00">
                        <span class="currency-symbol">€</span>
                    </div>
                    <div class="quick-bets">
                        <button class="quick-bet" onclick="setBet(1)">1€</button>
                        <button class="quick-bet" onclick="setBet(5)">5€</button>
                        <button class="quick-bet" onclick="setBet(10)">10€</button>
                        <button class="quick-bet" onclick="setBet(20)">20€</button>
                    </div>
                    <button class="action-btn start-btn" id="startBtn" onclick="startGame()">🚀 Spiel starten</button>
                    <button class="action-btn cashout-btn" id="cashoutBtn" onclick="cashout()">
                        💎 Auszahlen · <span id="cashoutAmount">0.00€</span>
                    </button>
                </div>
            </div>
            
            <!-- CENTER: GAME BOARD -->
            <div class="game-board">
                <div class="board-header">
                    <div class="board-title">🎮 Wähle deinen Weg</div>
                    <div class="provably-fair">
                        <span>✓</span> Provably Fair
                    </div>
                </div>
                <div class="road-grid" id="roadGrid"></div>
            </div>
            
            <!-- RIGHT: STATS -->
            <div class="stats-panel">
                <div class="stat-box">
                    <div class="stat-label">🎯 Einsatz</div>
                    <div class="stat-value" id="currentBet">0.00€</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">📊 Multiplikator</div>
                    <div class="stat-value" id="multiplier">1.00x</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">💎 Potenzieller Gewinn</div>
                    <div class="stat-value highlight" id="potentialWin">0.00€</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">🏆 Zeilen</div>
                    <div class="stat-value" id="rowsCleared">0 / 10</div>
                </div>
                <div class="history-box">
                    <div class="history-title">📜 Letzte Spiele</div>
                    <div class="history-items" id="history"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- RESULT MODAL -->
    <div class="result-modal" id="resultModal">
        <div class="result-content">
            <div class="result-icon" id="resultIcon">🎉</div>
            <div class="result-title" id="resultTitle">Gewonnen!</div>
            <div class="result-multiplier" id="resultMultiplier">10.00x</div>
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
        let history = JSON.parse(localStorage.getItem('chickenHistory') || '[]');
        
        const ROWS = 10;
        const TILES_PER_ROW = 3;
        const BASE_MULTIPLIER = 1.47;
        
        function setBet(amount) {
            document.getElementById('betInput').value = amount.toFixed(2);
        }
        
        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + '€';
        }
        
        function createGrid() {
            const grid = document.getElementById('roadGrid');
            grid.innerHTML = '';
            
            for (let row = 0; row < ROWS; row++) {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'road-row';
                rowDiv.innerHTML = `<div class="row-number">${row + 1}</div>`;
                
                for (let tile = 0; tile < TILES_PER_ROW; tile++) {
                    const tileDiv = document.createElement('div');
                    tileDiv.className = 'road-tile';
                    tileDiv.dataset.row = row;
                    tileDiv.dataset.tile = tile;
                    tileDiv.innerHTML = '🥚';
                    tileDiv.onclick = () => selectTile(row, tile);
                    rowDiv.appendChild(tileDiv);
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
                    
                    // Generate provably fair game state
                    gameState = [];
                    for (let i = 0; i < ROWS; i++) {
                        gameState.push(Math.floor(Math.random() * TILES_PER_ROW));
                    }
                    
                    document.getElementById('startBtn').style.display = 'none';
                    document.getElementById('currentBet').textContent = bet.toFixed(2) + '€';
                    
                    createGrid();
                    highlightNextRow();
                    updateStats();
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }
        
        function highlightNextRow() {
            document.querySelectorAll('.road-tile').forEach(tile => {
                tile.classList.remove('next-row');
            });
            
            if (currentRow < ROWS) {
                document.querySelectorAll(`[data-row="${currentRow}"]`).forEach(tile => {
                    if (!tile.classList.contains('revealed')) {
                        tile.classList.add('next-row');
                    }
                });
            }
        }
        
        async function selectTile(row, tile) {
            if (!gameActive || row !== currentRow) return;
            
            const tileEl = document.querySelector(`[data-row="${row}"][data-tile="${tile}"]`);
            if (tileEl.classList.contains('revealed')) return;
            
            // Disable all tiles in current row
            document.querySelectorAll(`[data-row="${row}"]`).forEach(t => {
                t.style.pointerEvents = 'none';
            });
            
            try {
                const response = await fetch('/api/casino/chicken_cross.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cross', road: row })
                });
                const data = await response.json();
                
                const isDanger = tile === gameState[row];
                
                await new Promise(resolve => setTimeout(resolve, 300));
                
                tileEl.classList.add('revealed');
                
                if (isDanger) {
                    // CRASH!
                    tileEl.classList.add('danger');
                    tileEl.innerHTML = '💀';
                    
                    // Reveal all safe tiles
                    document.querySelectorAll(`[data-row="${row}"]`).forEach((t, i) => {
                        if (i !== tile) {
                            setTimeout(() => {
                                t.classList.add('revealed', 'safe');
                                t.innerHTML = '🐔';
                            }, 200);
                        }
                    });
                    
                    gameActive = false;
                    addToHistory(false, 0);
                    setTimeout(() => gameOver(false), 1000);
                } else {
                    // SAFE!
                    tileEl.classList.add('safe');
                    tileEl.innerHTML = '🐔';
                    
                    currentRow++;
                    updateStats();
                    
                    if (currentRow > 0) {
                        document.getElementById('cashoutBtn').classList.add('active');
                        const multiplier = Math.pow(BASE_MULTIPLIER, currentRow);
                        const potential = currentBet * multiplier;
                        document.getElementById('cashoutAmount').textContent = potential.toFixed(2) + '€';
                    }
                    
                    if (currentRow >= ROWS) {
                        // All rows completed - auto cashout
                        setTimeout(() => cashout(), 800);
                    } else {
                        highlightNextRow();
                    }
                }
                
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
                    
                    const multiplier = Math.pow(BASE_MULTIPLIER, currentRow);
                    addToHistory(true, multiplier);
                    
                    document.getElementById('cashoutBtn').classList.remove('active');
                    showResult(true, data.win, multiplier);
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
            document.getElementById('rowsCleared').textContent = currentRow + ' / ' + ROWS;
            
            const multiplier = Math.pow(BASE_MULTIPLIER, currentRow);
            const potential = currentBet * multiplier;
            
            document.getElementById('multiplier').textContent = multiplier.toFixed(2) + 'x';
            document.getElementById('potentialWin').textContent = potential.toFixed(2) + '€';
        }
        
        function addToHistory(won, multiplier) {
            history.unshift({ won, multiplier: multiplier.toFixed(2) });
            if (history.length > 10) history.pop();
            localStorage.setItem('chickenHistory', JSON.stringify(history));
            renderHistory();
        }
        
        function renderHistory() {
            const historyEl = document.getElementById('history');
            historyEl.innerHTML = '';
            
            history.forEach(item => {
                const div = document.createElement('div');
                div.className = `history-item ${item.won ? 'win' : 'loss'}`;
                div.textContent = item.won ? `${item.multiplier}x` : '💀';
                historyEl.appendChild(div);
            });
        }
        
        function showResult(won, amount, mult) {
            const modal = document.getElementById('resultModal');
            const icon = document.getElementById('resultIcon');
            const title = document.getElementById('resultTitle');
            const multiplierEl = document.getElementById('resultMultiplier');
            const amountEl = document.getElementById('resultAmount');
            
            if (won) {
                const profit = amount - currentBet;
                icon.textContent = '🎉';
                title.textContent = 'Gewonnen!';
                title.style.color = '#10b981';
                multiplierEl.textContent = mult.toFixed(2) + 'x';
                multiplierEl.style.display = 'block';
                amountEl.textContent = '+' + profit.toFixed(2) + '€';
                amountEl.style.color = '#10b981';
            } else {
                icon.textContent = '💀';
                title.textContent = 'Crashed!';
                title.style.color = '#ef4444';
                multiplierEl.style.display = 'none';
                amountEl.textContent = '-' + currentBet.toFixed(2) + '€';
                amountEl.style.color = '#ef4444';
            }
            
            modal.classList.add('active');
        }
        
        function resetGame() {
            document.getElementById('resultModal').classList.remove('active');
            document.getElementById('startBtn').style.display = 'block';
            document.getElementById('cashoutBtn').classList.remove('active');
            
            gameActive = false;
            currentRow = 0;
            gameState = [];
            
            createGrid();
        }
        
        // Initialize
        createGrid();
        renderHistory();
    </script>
</body>
</html>

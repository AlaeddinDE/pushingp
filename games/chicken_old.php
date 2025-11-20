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
    <title>🐔 Chicken Road – PUSHING P Casino</title>
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
            max-width: 1400px;
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
            transition: all 0.2s;
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
        
        /* LAYOUT */
        .game-layout {
            display: grid;
            grid-template-columns: 350px 1fr 350px;
            gap: 20px;
        }
        
        /* CONTROLS */
        .controls-box {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            height: fit-content;
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
        
        .start-btn {
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            color: white;
        }
        
        .step-btn {
            background: linear-gradient(135deg, var(--accent), #a855f7);
            color: white;
            display: none;
        }
        
        .step-btn.active {
            display: block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4); }
            50% { box-shadow: 0 8px 24px rgba(139, 92, 246, 0.8); }
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
        
        /* GAME BOARD */
        .game-board {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            position: relative;
            min-height: 600px;
        }
        
        .road-canvas {
            width: 100%;
            height: 600px;
            border-radius: 12px;
            background: linear-gradient(180deg, #87CEEB 0%, #B0E0E6 20%, #2c3e50 20%, #34495e 100%);
            position: relative;
            overflow: hidden;
        }
        
        .chicken {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 4rem;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            z-index: 100;
        }
        
        .chicken.dead {
            animation: explode 0.5s;
        }
        
        @keyframes explode {
            0% { transform: translateX(-50%) scale(1) rotate(0deg); }
            50% { transform: translateX(-50%) scale(1.5) rotate(180deg); opacity: 1; }
            100% { transform: translateX(-50%) scale(0) rotate(360deg); opacity: 0; }
        }
        
        .road-lane {
            position: absolute;
            width: 100%;
            height: 60px;
            border-bottom: 2px dashed rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
        }
        
        .obstacle {
            position: absolute;
            font-size: 3.5rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.5));
            animation: slideRight 2s linear infinite;
        }
        
        .obstacle.fast {
            animation-duration: 1.5s !important;
        }
        
        .obstacle.slow {
            animation-duration: 3s !important;
        }
        
        @keyframes slideRight {
            0% { 
                right: -120px; 
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% { 
                right: calc(100% + 120px);
                transform: scale(1);
            }
        }
        
        /* STATS */
        .stats-box {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            height: fit-content;
        }
        
        .stat-item {
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 12px;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .stat-item:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 900;
            color: var(--accent);
        }
        
        .stat-value.big {
            font-size: 2.5rem;
            color: var(--success);
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
        @media (max-width: 1200px) {
            .game-layout {
                grid-template-columns: 1fr;
            }
            
            .stats-box {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-item {
                margin-bottom: 0;
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
                <h1 style="font-size: 2rem; font-weight: 900; margin-top: 8px;">🐔 Chicken Road</h1>
            </div>
            <div class="balance-display">
                <div class="balance-label">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?>€</div>
            </div>
        </div>
        
        <!-- GAME LAYOUT -->
        <div class="game-layout">
            <!-- LEFT: CONTROLS -->
            <div class="controls-box">
                <div id="betControls">
                    <label class="control-label">💰 Einsatz</label>
                    <input type="number" id="betInput" class="bet-input" min="0.10" max="100" step="0.10" value="1.00">
                    
                    <div class="quick-bets">
                        <button class="quick-bet" onclick="setBet(1)">1€</button>
                        <button class="quick-bet" onclick="setBet(5)">5€</button>
                        <button class="quick-bet" onclick="setBet(10)">10€</button>
                        <button class="quick-bet" onclick="setBet(20)">20€</button>
                    </div>
                    
                    <button class="action-btn start-btn" onclick="startGame()">🐔 Start</button>
                </div>
                
                <button class="action-btn step-btn" id="stepBtn" onclick="takeStep()">
                    👣 Schritt machen
                </button>
                
                <button class="action-btn cashout-btn" id="cashoutBtn" onclick="cashout()">
                    💰 Auszahlen: <span id="cashoutAmount">0.00€</span>
                </button>
            </div>
            
            <!-- CENTER: GAME BOARD -->
            <div class="game-board">
                <div class="road-canvas" id="roadCanvas">
                    <div class="chicken" id="chicken">🐔</div>
                </div>
            </div>
            
            <!-- RIGHT: STATS -->
            <div class="stats-box">
                <div class="stat-item">
                    <div class="stat-label">🎯 Einsatz</div>
                    <div class="stat-value" id="currentBet">0.00€</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">👣 Schritte</div>
                    <div class="stat-value" id="steps">0</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">📊 Multi</div>
                    <div class="stat-value" id="multiplier">1.00x</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">💰 Gewinn</div>
                    <div class="stat-value big" id="potentialWin">0.00€</div>
                </div>
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
        let steps = 0;
        let traps = [];
        const MAX_STEPS = 10;
        const BASE_MULTIPLIER = 1.4;
        
        function setBet(amount) {
            document.getElementById('betInput').value = amount.toFixed(2);
        }
        
        function updateBalance() {
            document.getElementById('balance').textContent = balance.toFixed(2).replace('.', ',') + '€';
        }
        
        function createRoad() {
            const canvas = document.getElementById('roadCanvas');
            // Clear existing lanes
            canvas.querySelectorAll('.road-lane').forEach(lane => lane.remove());
            
            // Create 10 lanes
            for (let i = 0; i < 10; i++) {
                const lane = document.createElement('div');
                lane.className = 'road-lane';
                lane.style.top = `${120 + (i * 48)}px`;
                canvas.appendChild(lane);
                
                // Add multiple cars per lane for KRASS effect
                const carsInLane = 2 + Math.floor(Math.random() * 2); // 2-3 cars per lane
                
                for (let j = 0; j < carsInLane; j++) {
                    const obstacle = document.createElement('div');
                    obstacle.className = 'obstacle';
                    
                    // More car types for variety
                    const carTypes = ['🚗', '🚙', '🚕', '🚌', '🚑', '🚓', '🚐', '🏎️', '🚚', '🚛'];
                    obstacle.textContent = carTypes[Math.floor(Math.random() * carTypes.length)];
                    
                    // Random speed classes
                    const speedClass = Math.random() > 0.5 ? 'fast' : 'slow';
                    obstacle.classList.add(speedClass);
                    
                    // Stagger start positions for continuous traffic
                    obstacle.style.animationDelay = `${-j * 1.5 - Math.random()}s`;
                    
                    lane.appendChild(obstacle);
                }
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
                    steps = 0;
                    balance -= bet;
                    updateBalance();
                    
                    // Generate trap positions (provably fair)
                    traps = [];
                    for (let i = 0; i < MAX_STEPS; i++) {
                        traps.push(Math.random() < 0.3); // 30% chance of trap
                    }
                    
                    document.getElementById('betControls').style.display = 'none';
                    document.getElementById('stepBtn').classList.add('active');
                    document.getElementById('currentBet').textContent = bet.toFixed(2) + '€';
                    
                    // Reset chicken position
                    const chicken = document.getElementById('chicken');
                    chicken.style.bottom = '20px';
                    chicken.classList.remove('dead');
                    chicken.textContent = '🐔';
                    
                    createRoad();
                    updateStats();
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }
        
        async function takeStep() {
            if (!gameActive) return;
            
            try {
                const response = await fetch('/api/casino/chicken_cross.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cross', road: steps })
                });
                const data = await response.json();
                
                // Check if trap
                const hitTrap = traps[steps];
                
                // Move chicken up
                const chicken = document.getElementById('chicken');
                chicken.style.bottom = `${20 + ((steps + 1) * 58)}px`;
                
                await new Promise(resolve => setTimeout(resolve, 500));
                
                if (hitTrap || data.status === 'hit') {
                    // CRASH!
                    chicken.classList.add('dead');
                    chicken.textContent = '💥';
                    gameActive = false;
                    
                    document.getElementById('stepBtn').classList.remove('active');
                    document.getElementById('cashoutBtn').classList.remove('active');
                    
                    setTimeout(() => gameOver(false), 1000);
                } else {
                    // SAFE!
                    steps++;
                    updateStats();
                    
                    if (steps > 0) {
                        document.getElementById('cashoutBtn').classList.add('active');
                        const multiplier = Math.pow(BASE_MULTIPLIER, steps);
                        const potential = currentBet * multiplier;
                        document.getElementById('cashoutAmount').textContent = potential.toFixed(2) + '€';
                    }
                    
                    if (steps >= MAX_STEPS) {
                        // Max steps reached - auto cashout
                        setTimeout(() => cashout(), 1000);
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
                    
                    document.getElementById('stepBtn').classList.remove('active');
                    document.getElementById('cashoutBtn').classList.remove('active');
                    
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
            document.getElementById('steps').textContent = steps + '/' + MAX_STEPS;
            
            const multiplier = Math.pow(BASE_MULTIPLIER, steps);
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
                title.textContent = 'Crashed!';
                title.style.color = 'var(--error)';
                amountEl.textContent = '-' + currentBet.toFixed(2) + '€';
                amountEl.style.color = 'var(--error)';
            }
            
            modal.classList.add('active');
        }
        
        function resetGame() {
            document.getElementById('resultModal').classList.remove('active');
            document.getElementById('betControls').style.display = 'block';
            document.getElementById('stepBtn').classList.remove('active');
            document.getElementById('cashoutBtn').classList.remove('active');
            
            gameActive = false;
            steps = 0;
            traps = [];
            
            const chicken = document.getElementById('chicken');
            chicken.style.bottom = '20px';
            chicken.classList.remove('dead');
            chicken.textContent = '🐔';
            
            createRoad();
        }
        
        // Initialize
        createRoad();
    </script>
</body>
</html>

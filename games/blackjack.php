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
        .cards { display: flex; gap: 12px; flex-wrap: wrap; min-height: 120px; }
        .card { 
            width: 80px; height: 120px; 
            background: white; border-radius: 8px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.5rem; font-weight: 800; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            position: relative;
            user-select: none;
            transform-origin: center;
            opacity: 0; /* Start invisible for animation */
            animation: dealCard 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        
        @keyframes dealCard {
            0% { 
                transform: translateY(-200px) translateX(100px) rotate(45deg) scale(0.5); 
                opacity: 0; 
            }
            100% { 
                transform: translateY(0) translateX(0) rotate(0) scale(1); 
                opacity: 1; 
            }
        }

        .card.red { color: #ef4444; }
        .card.black { color: #1e293b; }
        
        .card::before {
            content: '';
            position: absolute;
            top: 4px; left: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .card::after {
            content: '';
            position: absolute;
            bottom: 4px; right: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            transform: rotate(180deg);
        }
        .controls { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); padding: 24px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin-top: 20px; }
        
        .input-group { margin-bottom: 20px; text-align: center; }
        .bet-input { 
            background: rgba(15, 23, 42, 0.6); 
            border: 2px solid var(--accent); 
            color: white; 
            padding: 12px 24px; 
            border-radius: 12px; 
            font-size: 1.5rem; 
            font-weight: 800; 
            width: 150px; 
            text-align: center;
            outline: none;
            transition: all 0.3s ease;
        }
        .bet-input:focus { box-shadow: 0 0 20px rgba(139, 92, 246, 0.3); transform: scale(1.05); }

        .action-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .full-width { grid-column: span 2; }
        
        .btn-game {
            padding: 20px;
            border: none;
            border-radius: 16px;
            font-weight: 900;
            font-size: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .btn-game:active { transform: scale(0.95); }
        .btn-game::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(rgba(255,255,255,0.2), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .btn-game:hover::after { opacity: 1; }

        .btn-start { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4); }
        .btn-hit { background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4); }
        .btn-stand { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4); }
        .btn-double { background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4); }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.875rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-dealer { background: rgba(255,255,255,0.1); color: #94a3b8; }
        .status-player { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        
        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
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
                <div class="status-badge status-dealer">Dealer</div>
                <div class="hand-label"><span id="dealerScore">?</span></div>
                <div class="cards" id="dealerCards"></div>
            </div>
            <div class="hand">
                <div class="status-badge status-player">Du</div>
                <div class="hand-label"><span id="playerScore">0</span></div>
                <div class="cards" id="playerCards"></div>
            </div>
        </div>

        <div class="controls">
            <div id="startControls">
                <div class="input-group">
                    <label style="display: block; margin-bottom: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px;">Einsatz</label>
                    <input type="number" id="betAmount" class="bet-input" min="0.10" max="100" step="0.10" value="1.00">
                </div>
                <button class="btn-game btn-start full-width" onclick="deal()">START GAME</button>
            </div>
            
            <div id="gameControls" class="action-grid" style="display: none;">
                <button class="btn-game btn-hit" onclick="hit()">HIT</button>
                <button class="btn-game btn-stand" onclick="stand()">STAND</button>
                <button class="btn-game btn-double full-width" onclick="double()">DOUBLE DOWN (2x)</button>
            </div>
        </div>

        <div id="resultBox" style="margin-top: 20px;"></div>
    </div>

    <script>
        let balance = <?= $casino_available_balance ?>;
        let gameActive = false;
        let displayedPlayerCards = 0;
        let displayedDealerCards = 0;

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
                    
                    // Reset for new game
                    document.getElementById('playerCards').innerHTML = '';
                    document.getElementById('dealerCards').innerHTML = '';
                    displayedPlayerCards = 0;
                    displayedDealerCards = 0;
                    
                    renderGame(data);
                    document.getElementById('startControls').style.display = 'none';
                    document.getElementById('gameControls').style.display = 'grid';
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
        
        async function double() {
            try {
                const response = await fetch('/api/casino/play_blackjack.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'double' })
                });
                const data = await response.json();
                if (data.status === 'error') {
                    alert(data.error);
                    return;
                }
                renderGame(data);
                endGame(data);
            } catch (error) {
                alert('Fehler: ' + error.message);
            }
        }

        function renderGame(data) {
            const playerContainer = document.getElementById('playerCards');
            const dealerContainer = document.getElementById('dealerCards');

            // Render Player Cards
            data.player_hand.forEach((card, index) => {
                if (index >= displayedPlayerCards) {
                    const cardEl = createCardElement(card);
                    // Add delay for initial deal
                    if (displayedPlayerCards < 2) {
                        cardEl.style.animationDelay = `${index * 0.2}s`;
                    }
                    playerContainer.appendChild(cardEl);
                    displayedPlayerCards++;
                }
            });

            // Render Dealer Cards
            data.dealer_hand.forEach((card, index) => {
                if (index >= displayedDealerCards) {
                    const cardEl = createCardElement(card);
                    // Add delay for initial deal (after player cards)
                    if (displayedDealerCards < 2) {
                        cardEl.style.animationDelay = `${(index + 2) * 0.2}s`;
                    }
                    dealerContainer.appendChild(cardEl);
                    displayedDealerCards++;
                }
            });

            document.getElementById('playerScore').textContent = data.player_score;
            document.getElementById('dealerScore').textContent = data.dealer_visible ? data.dealer_score : '?';
        }

        function createCardElement(card) {
            const el = document.createElement('div');
            const isRed = ['♥','♦'].includes(card.suit);
            el.className = `card ${isRed ? 'red' : 'black'}`;
            el.textContent = `${card.rank}${card.suit}`;
            return el;
        }

        function endGame(data) {
            gameActive = false;
            balance = data.new_balance;
            updateBalance();
            
            document.getElementById('startControls').style.display = 'block';
            document.getElementById('gameControls').style.display = 'none';
            
            // Change button text to "New Game"
            document.querySelector('.btn-start').textContent = 'NEUES SPIEL';

            const resultBox = document.getElementById('resultBox');
            let html = '';
            
            if (data.profit > 0) {
                html = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 2px solid var(--success); border-radius: 12px; text-align: center; color: var(--success); font-weight: 800; font-size: 1.5rem; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                            🎉 GEWINN! +${data.profit.toFixed(2)}€
                        </div>`;
            } else if (data.profit === 0) {
                 html = `<div style="padding: 20px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 12px; text-align: center; color: var(--text-primary); font-weight: 800; font-size: 1.5rem; animation: popIn 0.5s;">
                            🤝 Unentschieden (+/- 0,00€)
                        </div>`;
            } else {
                // Loss (data.profit is negative, so it will show e.g. -10.00€)
                 html = `<div style="padding: 20px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); border: 2px solid var(--error); border-radius: 12px; text-align: center; color: var(--error); font-weight: 800; font-size: 1.5rem; animation: popIn 0.5s;">
                            ❌ VERLOREN ${data.profit.toFixed(2)}€
                        </div>`;
            }
            
            resultBox.innerHTML = html;
        }
    </script>
</body>
</html>

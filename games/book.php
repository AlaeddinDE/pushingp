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
    <title>📖 Book of P – PUSHING P Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { 
            background: linear-gradient(135deg, #1a0f0a 0%, #2d1810 100%); 
            height: 100vh; 
            margin: 0;
            padding: 10px; 
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .game-container { 
            max-width: 1100px; 
            width: 100%;
            background: linear-gradient(180deg, #1a0808 0%, #0a0404 100%);
            border-radius: 20px; 
            padding: 20px; 
            box-shadow: 
                0 20px 60px rgba(0,0,0,0.9),
                0 0 100px rgba(255,215,0,0.2);
            display: flex;
            flex-direction: column;
            max-height: 98vh;
            border: 3px solid rgba(255,215,0,0.3);
        }
        .game-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
            gap: 16px; 
        }
        .balance-display { 
            background: rgba(255,215,0,0.1);
            padding: 10px 20px; 
            border-radius: 12px; 
            text-align: center;
            border: 2px solid rgba(255,215,0,0.3);
        }
        .balance-value { font-size: 1.3rem; font-weight: 800; color: #FFD700; }
        
        /* Book of Ra Style Machine */
        .book-machine {
            position: relative;
            background: 
                linear-gradient(180deg, #2d1810 0%, #1a0f0a 50%, #2d1810 100%);
            border-radius: 20px;
            padding: 30px 20px 20px;
            box-shadow: 
                inset 0 0 80px rgba(0,0,0,0.9),
                0 0 60px rgba(255,215,0,0.3);
            border: 4px solid rgba(255,215,0,0.4);
            margin: 10px auto;
            max-width: 900px;
        }
        
        .book-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #FFD700, #FFA500, #FFD700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            text-shadow: 0 0 30px rgba(255,215,0,0.5);
            letter-spacing: 3px;
        }
        
        .book-reels {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            padding: 20px;
            background: rgba(0,0,0,0.5);
            border-radius: 15px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .book-reel {
            background: linear-gradient(180deg, #1a0f0a, #0a0404);
            border: 3px solid rgba(255,215,0,0.4);
            border-radius: 12px;
            height: 360px;
            overflow: hidden;
            position: relative;
            box-shadow: 
                inset 0 0 30px rgba(0,0,0,0.9),
                0 0 20px rgba(255,215,0,0.2);
        }
        
        .book-reel-strip {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            display: flex;
            flex-direction: column;
            transition: top 0.1s linear;
        }
        
        .book-symbol {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            flex-shrink: 0;
            background: radial-gradient(circle, rgba(255,215,0,0.1), transparent);
            border-bottom: 1px solid rgba(255,215,0,0.1);
        }
        
        .book-symbol.winning {
            animation: bookWin 0.5s ease-in-out infinite;
            filter: brightness(1.5) drop-shadow(0 0 20px rgba(255,215,0,1));
        }
        
        @keyframes bookWin {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .controls {
            background: rgba(255,215,0,0.05);
            padding: 15px;
            border-radius: 16px;
            margin-top: 15px;
            border: 2px solid rgba(255,215,0,0.2);
        }
        
        .bet-input {
            width: 100%;
            padding: 12px;
            background: rgba(0,0,0,0.5);
            border: 2px solid rgba(255,215,0,0.3);
            border-radius: 12px;
            color: #FFD700;
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 12px;
        }
        
        .spin-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #FFD700 100%);
            border: 4px solid #FFB800;
            color: #1a0f0a;
            font-weight: 900;
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 16px;
            text-transform: uppercase;
            letter-spacing: 3px;
            box-shadow: 
                0 10px 30px rgba(255,215,0,0.5),
                inset 0 2px 10px rgba(255,255,255,0.5);
        }
        
        .spin-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(255,215,0,0.8);
        }
        
        .spin-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .result-box {
            margin-top: 10px;
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 800;
        }
        
        .result-box.win {
            background: linear-gradient(135deg, rgba(255,215,0,0.2), rgba(255,165,0,0.2));
            border: 2px solid #FFD700;
            color: #FFD700;
        }
        
        .result-box.loss {
            background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(185,28,28,0.2));
            border: 2px solid #EF4444;
            color: #EF4444;
        }
        
        .back-btn {
            display: inline-block;
            padding: 12px 24px;
            background: rgba(255,215,0,0.1);
            border-radius: 12px;
            color: #FFD700;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border: 2px solid rgba(255,215,0,0.3);
        }
        
        .back-btn:hover {
            background: rgba(255,215,0,0.2);
            transform: translateY(-2px);
        }
        
        .payouts {
            margin-top: 12px;
            padding: 10px;
            background: rgba(255,215,0,0.05);
            border-radius: 12px;
            font-size: 0.75rem;
            color: rgba(255,215,0,0.7);
            border: 2px solid rgba(255,215,0,0.2);
        }
        
        .payouts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 1.8rem; margin: 8px 0 4px 0; color: #FFD700;">📖 Book of P</h1>
                <p style="color: rgba(255,215,0,0.7); margin: 0; font-size: 0.85rem;">5 Reels - 3 Symbole gewinnen!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.75rem; color: rgba(255,215,0,0.7); margin-bottom: 2px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div class="book-machine">
            <div class="book-title">📖 BOOK OF P 📖</div>
            
            <div class="book-reels">
                <div class="book-reel" id="reel1"><div class="book-reel-strip"></div></div>
                <div class="book-reel" id="reel2"><div class="book-reel-strip"></div></div>
                <div class="book-reel" id="reel3"><div class="book-reel-strip"></div></div>
                <div class="book-reel" id="reel4"><div class="book-reel-strip"></div></div>
                <div class="book-reel" id="reel5"><div class="book-reel-strip"></div></div>
            </div>
        </div>

        <div class="controls">
            <label style="display: block; margin-bottom: 6px; font-weight: 700; color: #FFD700; text-align: center; font-size: 0.9rem;">💰 Einsatz</label>
            <input type="number" id="betAmount" class="bet-input" min="0.50" max="50" step="0.50" value="1.00" placeholder="Einsatz">
            <button id="spinBtn" class="spin-btn" onclick="spin()">📖 DREHEN</button>
        </div>

        <div id="resultBox"></div>

        <div class="payouts">
            <strong style="font-size: 0.85rem; color: #FFD700;">📖 Auszahlungen (3+ gleiche Symbole):</strong>
            <div class="payouts-grid">
                <div>📖📖📖 <span style="color: #FFD700; font-weight: 800;">100x</span></div>
                <div>👑👑👑 <span style="color: #FFD700; font-weight: 800;">50x</span></div>
                <div>🦅🦅🦅 <span style="color: #FFA500; font-weight: 800;">25x</span></div>
                <div>⚱️⚱️⚱️ <span style="color: #FFA500; font-weight: 700;">15x</span></div>
                <div>🔱🔱🔱 <span style="color: #FFA500; font-weight: 700;">10x</span></div>
                <div>💎💎💎 <span style="color: #FFA500; font-weight: 700;">8x</span></div>
                <div>🎴🎴🎴 <span style="color: #FFD700; font-weight: 700;">6x</span></div>
                <div>🃏🃏🃏 <span style="color: #FFD700; font-weight: 700;">4x</span></div>
                <div>🎯🎯🎯 <span style="color: #FFD700; font-weight: 700;">3x</span></div>
            </div>
            <div style="margin-top: 8px; font-size: 0.7rem; text-align: center;">
                📖 Book Symbol = Scatter & Wild!
            </div>
        </div>
    </div>

    <script>
        let balance = parseFloat(<?= $casino_available_balance ?>) || 0;
        let spinning = false;
        
        // Book of Ra Symbole (Ägyptisch)
        const symbols = ['📖', '👑', '🦅', '⚱️', '🔱', '💎', '🎴', '🃏', '🎯'];
        
        // Initialize reels
        function initializeReels() {
            for (let i = 1; i <= 5; i++) {
                const reel = document.querySelector(`#reel${i} .book-reel-strip`);
                reel.innerHTML = '';
                for (let j = 0; j < 30; j++) {
                    const symbol = document.createElement('div');
                    symbol.className = 'book-symbol';
                    symbol.textContent = symbols[Math.floor(Math.random() * symbols.length)];
                    reel.appendChild(symbol);
                }
            }
        }
        
        initializeReels();

        function updateBalance() {
            const balanceNum = parseFloat(balance) || 0;
            document.getElementById('balance').textContent = balanceNum.toFixed(2).replace('.', ',') + ' €';
        }
        
        function rollReel(reelElement, duration, finalSymbol) {
            return new Promise((resolve) => {
                const strip = reelElement.querySelector('.book-reel-strip');
                const symbolHeight = 120;
                let currentPos = 0;
                const totalSymbols = 30;
                const rollDistance = symbolHeight * totalSymbols;
                
                const finalSymbolEl = document.createElement('div');
                finalSymbolEl.className = 'book-symbol';
                finalSymbolEl.textContent = finalSymbol;
                strip.appendChild(finalSymbolEl);
                
                const startTime = Date.now();
                const interval = setInterval(() => {
                    const elapsed = Date.now() - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    currentPos = easeOut * rollDistance;
                    strip.style.top = `-${currentPos}px`;
                    
                    if (progress >= 1) {
                        clearInterval(interval);
                        strip.style.top = `-${symbolHeight * totalSymbols}px`;
                        resolve();
                    }
                }, 16);
            });
        }

        async function spin() {
            if (spinning) return;
            const bet = parseFloat(document.getElementById('betAmount').value);
            if (bet < 0.50 || bet > balance) {
                alert('Ungültiger Einsatz!');
                return;
            }

            spinning = true;
            document.getElementById('spinBtn').disabled = true;
            document.getElementById('resultBox').innerHTML = '';

            try {
                const response = await fetch('/api/casino/play_book.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bet })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    const totalBalance = parseFloat(data.new_balance) || 0;
                    balance = Math.max(0, totalBalance - 10.00);
                    
                    initializeReels();
                    
                    const reels = [
                        document.getElementById('reel1'),
                        document.getElementById('reel2'),
                        document.getElementById('reel3'),
                        document.getElementById('reel4'),
                        document.getElementById('reel5')
                    ];
                    
                    const rollPromises = reels.map((reel, i) => 
                        rollReel(reel, 2000 + (i * 200), data.result[i])
                    );
                    
                    await Promise.all(rollPromises);
                    
                    const resultBox = document.getElementById('resultBox');
                    if (data.win_amount > 0) {
                        resultBox.innerHTML = `<div class="result-box win">🎉 GEWINN! +${data.win_amount.toFixed(2)}€ (${data.multiplier}x)</div>`;
                    } else {
                        resultBox.innerHTML = `<div class="result-box loss">Verloren: -${bet.toFixed(2)}€</div>`;
                    }
                    
                    updateBalance();
                    spinning = false;
                    document.getElementById('spinBtn').disabled = false;
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

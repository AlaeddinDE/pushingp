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
    <title>🅿️ Book of P – PUSHING P Casino</title>
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
            max-width: 1200px; 
            width: 100%;
            max-height: 98vh;
            background: linear-gradient(180deg, #1a0808 0%, #0a0404 100%);
            border-radius: 12px; 
            padding: 8px; 
            box-shadow: 
                0 20px 60px rgba(0,0,0,0.9),
                0 0 100px rgba(255,215,0,0.2);
            display: flex;
            flex-direction: column;
            border: 2px solid rgba(255,215,0,0.3);
            overflow: hidden;
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
            border-radius: 12px;
            padding: 12px 20px 10px;
            box-shadow: 
                inset 0 0 80px rgba(0,0,0,0.9),
                0 0 60px rgba(255,215,0,0.3);
            border: 2px solid rgba(255,215,0,0.4);
            margin: 0 auto;
            width: 100%;
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
            gap: 12px;
            padding: 12px 15px;
            background: rgba(0,0,0,0.5);
            border-radius: 10px;
            margin-bottom: 8px;
            position: relative;
        }
        
        .book-reel {
            background: linear-gradient(180deg, #1a0f0a, #0a0404);
            border: 2px solid rgba(255,215,0,0.4);
            border-radius: 10px;
            height: 270px;
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
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.2rem;
            flex-shrink: 0;
            background: radial-gradient(circle, rgba(255,215,0,0.1), transparent);
            border-bottom: 1px solid rgba(255,215,0,0.1);
        }
        
        .book-symbol:has(:first-child:last-child) {
            animation: p-glow 2s ease-in-out infinite;
        }
        
        @keyframes p-glow {
            0%, 100% {
                filter: drop-shadow(0 0 8px rgba(255,215,0,0.6)) 
                        drop-shadow(0 0 15px rgba(255,165,0,0.4));
            }
            50% {
                filter: drop-shadow(0 0 15px rgba(255,215,0,0.9)) 
                        drop-shadow(0 0 25px rgba(255,165,0,0.6));
            }
        }
        
        .book-symbol.scatter-trigger {
            animation: p-scatter 0.8s ease-in-out;
        }
        
        @keyframes p-scatter {
            0%, 100% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.2) rotate(-8deg); }
            50% { transform: scale(1.3) rotate(8deg); }
            75% { transform: scale(1.2) rotate(-4deg); }
        }
        
        .book-symbol.winning {
            animation: bookWin 0.5s ease-in-out infinite;
            filter: brightness(1.5) drop-shadow(0 0 20px rgba(255,215,0,1));
        }
        
        .book-symbol.expanding {
            animation: expandSymbol 1s ease-in-out;
            filter: brightness(2) drop-shadow(0 0 30px rgba(255,215,0,1));
            z-index: 10;
        }
        
        @keyframes bookWin {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        @keyframes expandSymbol {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.5); opacity: 0.8; }
            100% { transform: scale(3); opacity: 0; }
        }
        
        .freespins-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.5s;
        }
        
        .freespins-content {
            text-align: center;
            color: #FFD700;
        }
        
        .freespins-title {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 20px;
            text-shadow: 0 0 40px rgba(255,215,0,1);
            animation: pulse 1s infinite;
        }
        
        .freespins-count {
            font-size: 2rem;
            margin: 20px 0;
        }
        
        .freespins-info {
            background: rgba(255,215,0,0.1);
            padding: 15px 30px;
            border-radius: 12px;
            border: 2px solid rgba(255,215,0,0.3);
            margin-top: 20px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
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
            margin-top: 4px;
            padding: 6px;
            background: rgba(255,215,0,0.05);
            border-radius: 8px;
            font-size: 0.6rem;
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
                <h1 style="font-size: 1.8rem; margin: 8px 0 4px 0; color: #FFD700;">🅿️ Book of P</h1>
                <p style="color: rgba(255,215,0,0.7); margin: 0; font-size: 0.85rem;">5 Reels - 3 Symbole gewinnen!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.75rem; color: rgba(255,215,0,0.7); margin-bottom: 2px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div class="book-machine">
            <div class="book-title">🅿️ BOOK OF P 🅿️</div>
            
            <!-- Freespins Counter -->
            <div id="freespinsCounter" style="display: none; text-align: center; margin-bottom: 6px; padding: 6px; background: rgba(255,215,0,0.2); border-radius: 8px; border: 2px solid #FFD700;">
                <div style="font-size: 0.9rem; font-weight: 800; color: #FFD700;">
                    🎰 FREISPIELE AKTIV 🎰
                </div>
                <div style="font-size: 0.75rem; margin-top: 3px; color: #FFA500;">
                    <span id="freespinsRemaining">0</span> Freispiele übrig | Expanding Symbol: <span id="expandingSymbol"></span>
                </div>
                <div style="font-size: 0.7rem; margin-top: 3px; color: rgba(255,215,0,0.8);">
                    Gesamtgewinn: <span id="freespinsTotalWin">0.00</span>€
                </div>
            </div>
            
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
            <button id="spinBtn" class="spin-btn" onclick="spin()">🅿️ DREHEN</button>
        </div>

        <div id="resultBox"></div>

        <div class="payouts">
            <strong style="font-size: 0.85rem; color: #FFD700;">📖 Auszahlungen (3+ gleiche Symbole):</strong>
            <div class="payouts-grid">
                <div>🅿️🅿️🅿️ <span style="color: #FFD700; font-weight: 800;">100x</span></div>
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
                🅿️ P Symbol = Scatter & Wild!<br>
                🅿️🅿️🅿️ = 10 Freispiele mit expanding Symbol!
            </div>
        </div>
    </div>

    <!-- Freespins Trigger Overlay -->
    <div id="freespinsOverlay" style="display: none;"></div>

    <script>
        let balance = parseFloat(<?= $casino_available_balance ?>) || 0;
        let spinning = false;
        let freespinsActive = false;
        let freespinsRemaining = 0;
        let expandingSymbol = null;
        let freespinsTotalWin = 0;
        let currentBet = 0;
        
        // Book of Ra Symbole (Ägyptisch)
        const symbols = ['🅿️', '👑', '🦅', '⚱️', '🔱', '💎', '🎴', '🃏', '🎯'];
        
        // Initialize reels
        function initializeReels() {
            for (let i = 1; i <= 5; i++) {
                const reel = document.querySelector(`#reel${i} .book-reel-strip`);
                reel.innerHTML = '';
                for (let j = 0; j < 30; j++) {
                    const symbol = document.createElement('div');
                    symbol.className = 'book-symbol';
                    const randomSymbol = symbols[Math.floor(Math.random() * symbols.length)];
                    symbol.textContent = randomSymbol;
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
                const symbolHeight = 90;
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

        function showFreespinsOverlay(count, symbol) {
            const overlay = document.getElementById('freespinsOverlay');
            overlay.innerHTML = `
                <div class="freespins-overlay">
                    <div class="freespins-content">
                        <div class="freespins-title">🅿️ FREISPIELE! 🅿️</div>
                        <div class="freespins-count">${count} Freispiele gewonnen!</div>
                        <div style="font-size: 3rem; margin: 20px 0;">${symbol}</div>
                        <div class="freespins-info">
                            <div style="font-size: 1.2rem; font-weight: 700;">Expanding Symbol: ${symbol}</div>
                            <div style="margin-top: 10px; font-size: 0.9rem;">
                                Das Symbol expandiert über alle 3 Positionen!
                            </div>
                        </div>
                    </div>
                </div>
            `;
            overlay.style.display = 'block';
            
            playSound('p-expand');
            setTimeout(() => {
                overlay.style.display = 'none';
                startFreespins(count, symbol);
            }, 3000);
        }
        
        function startFreespins(count, symbol) {
            freespinsActive = true;
            freespinsRemaining = count;
            expandingSymbol = symbol;
            freespinsTotalWin = 0;
            
            document.getElementById('freespinsCounter').style.display = 'block';
            document.getElementById('betAmount').disabled = true;
            updateFreespinsDisplay();
            
            setTimeout(() => spin(), 1000);
        }
        
        function updateFreespinsDisplay() {
            document.getElementById('freespinsRemaining').textContent = freespinsRemaining;
            document.getElementById('expandingSymbol').textContent = expandingSymbol;
            document.getElementById('freespinsTotalWin').textContent = freespinsTotalWin.toFixed(2);
        }
        
        function endFreespins() {
            freespinsActive = false;
            document.getElementById('freespinsCounter').style.display = 'none';
            document.getElementById('betAmount').disabled = false;
            
            const resultBox = document.getElementById('resultBox');
            resultBox.innerHTML = `<div class="result-box win" style="font-size: 1.3rem;">
                🎉 FREISPIELE BEENDET! 🎉<br>
                Gesamtgewinn: ${freespinsTotalWin.toFixed(2)}€
            </div>`;
        }

        // Sound Effects
        function playSound(type) {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            switch(type) {
                case 'p-scatter':
                    // Epic P Symbol Sound
                    oscillator.frequency.setValueAtTime(440, audioContext.currentTime);
                    oscillator.frequency.exponentialRampToValueAtTime(880, audioContext.currentTime + 0.2);
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.5);
                    break;
                case 'p-trigger':
                    // Freispiel Trigger Sound
                    for(let i = 0; i < 3; i++) {
                        const osc = audioContext.createOscillator();
                        const gain = audioContext.createGain();
                        osc.connect(gain);
                        gain.connect(audioContext.destination);
                        osc.frequency.setValueAtTime(523 + (i * 100), audioContext.currentTime + (i * 0.1));
                        gain.gain.setValueAtTime(0.2, audioContext.currentTime + (i * 0.1));
                        gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + (i * 0.1) + 0.3);
                        osc.start(audioContext.currentTime + (i * 0.1));
                        osc.stop(audioContext.currentTime + (i * 0.1) + 0.3);
                    }
                    break;
                case 'p-expand':
                    // Expanding P Sound
                    oscillator.frequency.setValueAtTime(200, audioContext.currentTime);
                    oscillator.frequency.exponentialRampToValueAtTime(600, audioContext.currentTime + 0.4);
                    gainNode.gain.setValueAtTime(0.25, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.4);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.4);
                    break;
            }
        }

        async function spin() {
            if (spinning) return;
            
            let bet = currentBet;
            if (!freespinsActive) {
                bet = parseFloat(document.getElementById('betAmount').value);
                if (bet < 0.50 || bet > balance) {
                    alert('Ungültiger Einsatz!');
                    return;
                }
                currentBet = bet;
            }

            spinning = true;
            document.getElementById('spinBtn').disabled = true;
            document.getElementById('resultBox').innerHTML = '';

            try {
                const response = await fetch('/api/casino/play_book.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        bet: bet,
                        freespin: freespinsActive,
                        expanding_symbol: expandingSymbol
                    })
                });
                
                console.log('Response status:', response.status);
                const responseText = await response.text();
                console.log('Response text:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response was:', responseText);
                    throw new Error('Server returned invalid JSON');
                }
                
                console.log('Parsed data:', data);

                if (data.status === 'success') {
                    if (!freespinsActive) {
                        const totalBalance = parseFloat(data.new_balance) || 0;
                        balance = Math.max(0, totalBalance - 10.00);
                    }
                    
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
                    
                    // Check for freespin trigger (3+ P symbols)
                    if (!freespinsActive && data.freespins_triggered) {
                        playSound('p-trigger');
                        // Add scatter animation to P symbols (🅿️)
                        document.querySelectorAll('.book-symbol').forEach(sym => {
                            if (sym.textContent === '🅿️') {
                                sym.classList.add('scatter-trigger');
                            }
                        });
                        showFreespinsOverlay(data.freespins_count, data.expanding_symbol);
                        spinning = false;
                        document.getElementById('spinBtn').disabled = false;
                        return;
                    }
                    
                    const resultBox = document.getElementById('resultBox');
                    if (data.win_amount > 0) {
                        if (freespinsActive) {
                            freespinsTotalWin += data.win_amount;
                            resultBox.innerHTML = `<div class="result-box win">🎉 GEWINN! +${data.win_amount.toFixed(2)}€ (${data.multiplier}x)</div>`;
                        } else {
                            resultBox.innerHTML = `<div class="result-box win">🎉 GEWINN! +${data.win_amount.toFixed(2)}€ (${data.multiplier}x)</div>`;
                        }
                    } else {
                        if (!freespinsActive) {
                            resultBox.innerHTML = `<div class="result-box loss">Verloren: -${bet.toFixed(2)}€</div>`;
                        }
                    }
                    
                    if (freespinsActive) {
                        freespinsRemaining--;
                        updateFreespinsDisplay();
                        
                        if (freespinsRemaining > 0) {
                            setTimeout(() => {
                                spinning = false;
                                document.getElementById('spinBtn').disabled = false;
                            }, 2000);
                        } else {
                            const totalBalance = parseFloat(data.new_balance) || 0;
                            balance = Math.max(0, totalBalance - 10.00);
                            endFreespins();
                            spinning = false;
                            document.getElementById('spinBtn').disabled = false;
                        }
                    } else {
                        updateBalance();
                        spinning = false;
                        document.getElementById('spinBtn').disabled = false;
                    }
                } else {
                    console.error('Game failed:', data.error);
                    alert('Spiel fehlgeschlagen: ' + data.error);
                    spinning = false;
                    document.getElementById('spinBtn').disabled = false;
                }
            } catch (error) {
                console.error('Catch block error:', error);
                alert('Fehler: ' + error.message);
                spinning = false;
                document.getElementById('spinBtn').disabled = false;
            }
        }
    </script>
</body>
</html>

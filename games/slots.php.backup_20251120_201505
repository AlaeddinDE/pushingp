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
        
        /* === LAS VEGAS SLOT MACHINE STYLE === */
        .slots-machine {
            position: relative;
            background: linear-gradient(180deg, #1a1a1a 0%, #0a0a0a 50%, #1a1a1a 100%);
            border-radius: 40px;
            padding: 60px 40px 40px;
            box-shadow: 
                0 30px 80px rgba(0,0,0,0.9),
                inset 0 2px 4px rgba(255,255,255,0.1),
                inset 0 -2px 4px rgba(0,0,0,0.5);
            border: 4px solid #2a2a2a;
            margin: 20px auto;
            max-width: 700px;
        }
        
        .slots-crown {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #FFD700, #FFA500, #FFD700);
            padding: 15px 60px;
            border-radius: 20px 20px 0 0;
            font-size: 1.5rem;
            font-weight: 900;
            color: #000;
            text-shadow: 0 2px 4px rgba(255,255,255,0.5);
            box-shadow: 
                0 0 30px rgba(255,215,0,0.8),
                0 0 60px rgba(255,165,0,0.6),
                inset 0 2px 8px rgba(255,255,255,0.4);
            letter-spacing: 4px;
            animation: crownPulse 2s ease-in-out infinite;
        }
        
        @keyframes crownPulse {
            0%, 100% { 
                box-shadow: 0 0 30px rgba(255,215,0,0.8), 0 0 60px rgba(255,165,0,0.6), inset 0 2px 8px rgba(255,255,255,0.4);
            }
            50% { 
                box-shadow: 0 0 50px rgba(255,215,0,1), 0 0 100px rgba(255,165,0,0.9), inset 0 2px 8px rgba(255,255,255,0.6);
            }
        }
        
        .slots-lights {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 3px solid transparent;
            border-radius: 36px;
            pointer-events: none;
            background: linear-gradient(90deg, 
                #FF0000 0%, #FF7F00 12.5%, #FFFF00 25%, 
                #00FF00 37.5%, #0000FF 50%, #4B0082 62.5%, 
                #9400D3 75%, #FF0000 87.5%, #FF0000 100%
            );
            background-size: 400% 100%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            padding: 3px;
            animation: ledChase 3s linear infinite;
        }
        
        @keyframes ledChase {
            0% { background-position: 0% 0%; }
            100% { background-position: 400% 0%; }
        }
        
        .slots-reels {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 30px 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.6) 100%);
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: inset 0 10px 30px rgba(0,0,0,0.8);
        }
        
        .slot-reel-frame {
            position: relative;
            padding: 8px;
            background: linear-gradient(145deg, #c0c0c0, #808080, #c0c0c0);
            border-radius: 28px;
            box-shadow: 
                0 8px 24px rgba(0,0,0,0.8),
                inset 0 2px 6px rgba(255,255,255,0.3),
                inset 0 -2px 6px rgba(0,0,0,0.5);
        }
        
        .slot-reel {
            width: 140px;
            height: 160px;
            background: linear-gradient(145deg, #0a0015, #1a0033, #0a0015);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            border: 3px solid #1a1a2a;
            position: relative;
            overflow: hidden;
            box-shadow: 
                inset 0 0 50px rgba(0,0,0,0.9),
                inset 0 4px 12px rgba(139, 92, 246, 0.2),
                0 0 40px rgba(139, 92, 246, 0.3);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            animation: reelIdle 3s ease-in-out infinite;
        }
        
        .slot-reel::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, 
                rgba(255,255,255,0.1) 0%, 
                transparent 20%,
                transparent 80%,
                rgba(0,0,0,0.4) 100%
            );
            border-radius: 18px;
            pointer-events: none;
        }
        
        .slot-reel::before {
            content: '';
            position: absolute;
            top: -100%;
            left: 0;
            width: 100%;
            height: 40px;
            background: linear-gradient(180deg, transparent, rgba(255,255,255,0.15), transparent);
            animation: scanLine 4s ease-in-out infinite;
            pointer-events: none;
        }
        
        @keyframes scanLine {
            0% { top: -100%; }
            50% { top: 100%; }
            100% { top: -100%; }
        }
        
        @keyframes reelIdle {
            0%, 100% { 
                transform: translateY(0) scale(1);
                box-shadow: inset 0 0 50px rgba(0,0,0,0.9), inset 0 4px 12px rgba(139, 92, 246, 0.2), 0 0 40px rgba(139, 92, 246, 0.3);
            }
            50% { 
                transform: translateY(-2px) scale(1.01);
                box-shadow: inset 0 0 50px rgba(0,0,0,0.9), inset 0 4px 12px rgba(139, 92, 246, 0.4), 0 0 60px rgba(139, 92, 246, 0.5);
            }
        }
        
        .slot-reel.spinning {
            animation: slotSpin 0.05s linear infinite, slotShake 0.12s ease-in-out infinite, slotGlow 0.15s ease-in-out infinite;
            filter: brightness(1.4) saturate(1.3) blur(1px);
        }
        
        .slot-reel.spinning::before {
            animation: none;
            background: linear-gradient(180deg, rgba(255,215,0,0.3), rgba(255,165,0,0.3), rgba(255,215,0,0.3));
        }
        
        .slot-reel.winning {
            animation: slotWin 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite, rainbowGlow 1s linear infinite;
        }
        
        .slot-reel.winning::before {
            animation: none;
            background: linear-gradient(180deg, rgba(255,215,0,0.6), rgba(255,255,255,0.4), rgba(255,215,0,0.6));
        }
        
        @keyframes slotSpin { 0% { transform: translateY(0); } 100% { transform: translateY(-20px); } }
        
        @keyframes slotShake {
            0%, 100% { transform: translateX(0) scale(1) rotate(0deg); }
            25% { transform: translateX(-5px) scale(1.03) rotate(-2deg); }
            75% { transform: translateX(5px) scale(1.03) rotate(2deg); }
        }
        
        @keyframes slotGlow {
            0%, 100% { box-shadow: inset 0 0 40px rgba(0,0,0,0.7), 0 0 60px rgba(139, 92, 246, 0.6); }
            50% { box-shadow: inset 0 0 40px rgba(0,0,0,0.7), 0 0 100px rgba(16, 185, 129, 0.9); }
        }
        
        @keyframes slotWin {
            0%, 100% { 
                transform: scale(1) rotate(0deg);
                box-shadow: 0 0 60px rgba(245, 158, 11, 0.8);
            }
            25% { 
                transform: scale(1.15) rotate(-5deg);
                box-shadow: 0 0 100px rgba(245, 158, 11, 1);
            }
            50% { 
                transform: scale(1.1) rotate(5deg);
                box-shadow: 0 0 80px rgba(16, 185, 129, 1);
            }
            75% { 
                transform: scale(1.15) rotate(-5deg);
                box-shadow: 0 0 100px rgba(139, 92, 246, 1);
            }
        }
        
        @keyframes rainbowGlow {
            0% { filter: hue-rotate(0deg) brightness(1.3); }
            100% { filter: hue-rotate(360deg) brightness(1.3); }
        }
        
        .controls { background: var(--bg-secondary); padding: 24px; border-radius: 16px; margin-top: 24px; }
        .bet-input { width: 100%; padding: 16px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.25rem; font-weight: 700; text-align: center; margin-bottom: 16px; }
        .spin-btn { width: 100%; padding: 20px; background: linear-gradient(135deg, #f59e0b 0%, #ef4444 50%, #f59e0b 100%); border: 5px solid #fbbf24; color: white; font-weight: 900; font-size: 1.5rem; cursor: pointer; transition: all 0.3s; border-radius: 16px; text-transform: uppercase; letter-spacing: 3px; box-shadow: 0 10px 50px rgba(245, 158, 11, 0.8); animation: spinBtnPulse 1.5s ease-in-out infinite; }
        .spin-btn:hover { transform: scale(1.05); box-shadow: 0 15px 60px rgba(245, 158, 11, 1); }
        .spin-btn:disabled { opacity: 0.5; cursor: not-allowed; animation: none; }
        
        @keyframes spinBtnPulse {
            0%, 100% { box-shadow: 0 10px 50px rgba(245, 158, 11, 0.8); }
            50% { box-shadow: 0 15px 70px rgba(245, 158, 11, 1); }
        }
        
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

        <div class="slots-machine">
            <div class="slots-crown">⭐ JACKPOT ⭐</div>
            <div class="slots-lights"></div>
            
            <div class="slots-reels">
                <div class="slot-reel-frame">
                    <div class="slot-reel" id="reel1">🍒</div>
                </div>
                <div class="slot-reel-frame">
                    <div class="slot-reel" id="reel2">🍋</div>
                </div>
                <div class="slot-reel-frame">
                    <div class="slot-reel" id="reel3">⭐</div>
                </div>
            </div>
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
                            r.textContent = data.result[i];
                            
                            // Add winning animation if it's a win
                            if (data.win_amount > 0) {
                                setTimeout(() => {
                                    r.classList.add('winning');
                                }, (i + 1) * 200);
                            }
                        });

                        const resultBox = document.getElementById('resultBox');
                        if (data.win_amount > 0) {
                            resultBox.innerHTML = `<div class="result-box win">🎉 GEWINN! +${data.win_amount.toFixed(2)}€ (${data.multiplier}x)</div>`;
                            
                            // Remove winning class after animation
                            setTimeout(() => {
                                reels.forEach(r => r.classList.remove('winning'));
                            }, 3000);
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

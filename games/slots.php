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
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); 
            height: 100vh; 
            margin: 0;
            padding: 10px; 
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .game-container { 
            max-width: 1000px; 
            width: 100%;
            background: var(--bg-primary); 
            border-radius: 20px; 
            padding: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            max-height: 98vh;
        }
        .game-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
            gap: 16px; 
        }
        .balance-display { 
            background: var(--bg-secondary); 
            padding: 10px 20px; 
            border-radius: 12px; 
            text-align: center; 
        }
        .balance-value { font-size: 1.3rem; font-weight: 800; color: var(--success); }
        
        /* === ULTRA KRASSE LAS VEGAS SLOT MACHINE === */
        .slots-machine {
            position: relative;
            background: 
                radial-gradient(circle at 30% 30%, rgba(255,215,0,0.15), transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(255,0,150,0.15), transparent 50%),
                linear-gradient(180deg, #1a1a1a 0%, #000000 50%, #1a1a1a 100%);
            border-radius: 30px;
            padding: 45px 30px 25px;
            box-shadow: 
                0 40px 120px rgba(0,0,0,1),
                0 0 100px rgba(255,215,0,0.4),
                0 0 150px rgba(255,0,150,0.3),
                inset 0 3px 12px rgba(255,255,255,0.15),
                inset 0 -3px 12px rgba(0,0,0,0.8);
            border: 6px solid transparent;
            background-clip: padding-box;
            margin: 10px auto;
            max-width: 650px;
            animation: machineGlow 3s ease-in-out infinite;
        }
        
        .slots-machine::before {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 30px;
            background: linear-gradient(45deg, 
                #FFD700, #FF00FF, #00FFFF, #FFD700, 
                #FF00FF, #00FFFF, #FFD700);
            background-size: 400% 400%;
            animation: borderFlow 4s linear infinite;
            z-index: -1;
        }
        
        @keyframes machineGlow {
            0%, 100% {
                box-shadow: 
                    0 40px 120px rgba(0,0,0,1),
                    0 0 60px rgba(255,215,0,0.2),
                    0 0 80px rgba(255,0,150,0.15),
                    inset 0 3px 12px rgba(255,255,255,0.1),
                    inset 0 -3px 12px rgba(0,0,0,0.8);
            }
            50% {
                box-shadow: 
                    0 40px 120px rgba(0,0,0,1),
                    0 0 80px rgba(255,215,0,0.3),
                    0 0 100px rgba(255,0,150,0.2),
                    inset 0 3px 12px rgba(255,255,255,0.15),
                    inset 0 -3px 12px rgba(0,0,0,0.8);
            }
        }
        
        @keyframes borderFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .slots-crown {
            position: absolute;
            top: -52px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #FFD700, #FFA500, #FF00FF, #FFD700);
            background-size: 300% 300%;
            padding: 8px 40px;
            border-radius: 20px 20px 0 0;
            font-size: 1.1rem;
            font-weight: 900;
            color: #000;
            text-align: center;
            text-shadow: 
                0 0 10px rgba(255,255,255,1),
                0 0 20px rgba(255,215,0,1),
                0 2px 4px rgba(255,255,255,0.5);
            box-shadow: 
                0 0 50px rgba(255,215,0,1),
                0 0 100px rgba(255,0,255,0.8),
                0 0 150px rgba(255,165,0,0.6),
                inset 0 4px 16px rgba(255,255,255,0.6),
                inset 0 -4px 16px rgba(0,0,0,0.3);
            letter-spacing: 3px;
            animation: crownPulse 1.5s ease-in-out infinite, crownBg 3s linear infinite;
            border: 3px solid rgba(255,255,255,0.8);
            overflow: hidden;
            white-space: nowrap;
            z-index: 10;
        }
        
        .slots-crown::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.8), transparent);
            animation: crownShine 2s linear infinite;
        }
        
        @keyframes crownShine {
            0% { transform: rotate(0deg) translateX(-100%); }
            100% { transform: rotate(0deg) translateX(100%); }
        }
        
        @keyframes crownBg {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes crownPulse {
            0%, 100% { 
                transform: translateX(-50%) scale(1);
                box-shadow: 
                    0 0 30px rgba(255,215,0,0.8),
                    0 0 50px rgba(255,0,255,0.4),
                    inset 0 4px 16px rgba(255,255,255,0.5);
            }
            50% { 
                transform: translateX(-50%) scale(1.02);
                box-shadow: 
                    0 0 40px rgba(255,215,0,0.9),
                    0 0 70px rgba(255,0,255,0.5),
                    inset 0 5px 18px rgba(255,255,255,0.6);
            }
        }
        
        .slots-lights {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 5px solid transparent;
            border-radius: 36px;
            pointer-events: none;
            background: linear-gradient(90deg, 
                #FF0000 0%, #FF7F00 8%, #FFFF00 16%, 
                #7FFF00 24%, #00FF00 32%, #00FF7F 40%,
                #00FFFF 48%, #007FFF 56%, #0000FF 64%, 
                #7F00FF 72%, #FF00FF 80%, #FF007F 88%,
                #FF0000 100%
            );
            background-size: 600% 100%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            padding: 5px;
            animation: ledChase 2s linear infinite;
            filter: drop-shadow(0 0 10px currentColor) drop-shadow(0 0 20px currentColor);
        }
        
        .slots-lights::before {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 30px;
            background: inherit;
            opacity: 0.5;
            filter: blur(15px);
            animation: ledChase 2s linear infinite;
        }
        
        @keyframes ledChase {
            0% { background-position: 0% 0%; }
            100% { background-position: 600% 0%; }
        }
        
        .slots-reels {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 15px 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.6) 100%);
            padding: 20px 15px;
            border-radius: 20px;
            box-shadow: inset 0 10px 30px rgba(0,0,0,0.8);
            flex-wrap: nowrap;
        }
        
        .slot-reel-frame {
            position: relative;
            padding: 10px;
            background: linear-gradient(145deg, 
                #FFD700 0%, #FFA500 25%, #FFD700 50%, 
                #FFA500 75%, #FFD700 100%
            );
            border-radius: 32px;
            box-shadow: 
                0 0 30px rgba(255,215,0,0.8),
                0 0 60px rgba(255,165,0,0.6),
                0 12px 32px rgba(0,0,0,0.9),
                inset 0 3px 10px rgba(255,255,255,0.5),
                inset 0 -3px 10px rgba(0,0,0,0.7);
            animation: frameGlow 2s ease-in-out infinite;
        }
        
        @keyframes frameGlow {
            0%, 100% {
                box-shadow: 
                    0 0 20px rgba(255,215,0,0.5),
                    0 0 40px rgba(255,165,0,0.3),
                    0 12px 32px rgba(0,0,0,0.9),
                    inset 0 3px 10px rgba(255,255,255,0.4),
                    inset 0 -3px 10px rgba(0,0,0,0.7);
            }
            50% {
                box-shadow: 
                    0 0 30px rgba(255,215,0,0.7),
                    0 0 60px rgba(255,165,0,0.5),
                    0 12px 32px rgba(0,0,0,0.9),
                    inset 0 4px 12px rgba(255,255,255,0.5),
                    inset 0 -4px 12px rgba(0,0,0,0.7);
            }
        }
        
        .slot-reel {
            width: 130px;
            height: 170px;
            background: 
                radial-gradient(circle at center, rgba(139, 92, 246, 0.2), transparent 70%),
                radial-gradient(circle at 30% 30%, rgba(236, 72, 153, 0.15), transparent 60%),
                linear-gradient(145deg, #0a0015, #1a0033, #2d0052, #0a0015);
            border-radius: 24px;
            position: relative;
            overflow: hidden;
            border: 4px solid rgba(139, 92, 246, 0.5);
            box-shadow: 
                inset 0 0 60px rgba(0,0,0,1),
                inset 0 0 30px rgba(139, 92, 246, 0.3),
                inset 0 6px 20px rgba(139, 92, 246, 0.4),
                0 0 50px rgba(139, 92, 246, 0.5),
                0 0 100px rgba(236, 72, 153, 0.3);
            animation: reelIdle 2.5s ease-in-out infinite;
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        
        .reel-strip {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: top 0.1s linear;
        }
        
        .reel-symbol {
            width: 100%;
            height: 170px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            flex-shrink: 0;
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
            top: -150%;
            left: 0;
            width: 100%;
            height: 60px;
            background: linear-gradient(180deg, 
                rgba(255,215,0,0.6), 
                rgba(255,255,255,0.4), 
                rgba(0,255,255,0.4),
                transparent
            );
            animation: scanLine 3s ease-in-out infinite;
            pointer-events: none;
            filter: blur(2px);
        }
        
        @keyframes scanLine {
            0% { top: -150%; opacity: 0; }
            10% { opacity: 1; }
            50% { top: 100%; opacity: 1; }
            60% { opacity: 0; }
            100% { top: -150%; opacity: 0; }
        }
        
        @keyframes reelIdle {
            0%, 100% { 
                transform: translateY(0) scale(1);
                box-shadow: 
                    inset 0 0 60px rgba(0,0,0,1),
                    inset 0 0 20px rgba(139, 92, 246, 0.2),
                    0 0 30px rgba(139, 92, 246, 0.3),
                    0 0 50px rgba(236, 72, 153, 0.2);
            }
            50% { 
                transform: translateY(-2px) scale(1.01);
                box-shadow: 
                    inset 0 0 60px rgba(0,0,0,1),
                    inset 0 0 25px rgba(139, 92, 246, 0.3),
                    0 0 40px rgba(139, 92, 246, 0.4),
                    0 0 70px rgba(236, 72, 153, 0.3);
            }
        }
        

        
        .slot-reel-frame.winning {
            animation: 
                frameWin 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite, 
                rainbowGlow 0.8s linear infinite;
            z-index: 100;
        }
        
        @keyframes frameWin {
            0%, 100% { 
                transform: scale(1) rotate(0deg);
                box-shadow: 
                    0 0 50px rgba(255,215,0,1),
                    0 0 100px rgba(255,0,255,1),
                    0 12px 32px rgba(0,0,0,0.9),
                    inset 0 4px 12px rgba(255,255,255,0.7);
            }
            50% { 
                transform: scale(1.1) rotate(2deg);
                box-shadow: 
                    0 0 80px rgba(255,215,0,1),
                    0 0 150px rgba(0,255,255,1),
                    0 12px 32px rgba(0,0,0,0.9),
                    inset 0 6px 16px rgba(255,255,255,0.9);
            }
        }
        
        @keyframes slotSpin { 0% { transform: translateY(0); } 100% { transform: translateY(-20px); } }
        
        @keyframes slotShake {
            0%, 100% { transform: translateX(0) scale(1.05) rotate(0deg) rotateY(0deg); }
            25% { transform: translateX(-8px) scale(1.08) rotate(-3deg) rotateY(-5deg); }
            75% { transform: translateX(8px) scale(1.08) rotate(3deg) rotateY(5deg); }
        }
        
        @keyframes slotGlow {
            0% { 
                box-shadow: 
                    inset 0 0 60px rgba(0,0,0,0.9), 
                    0 0 80px rgba(139, 92, 246, 1),
                    0 0 150px rgba(236, 72, 153, 0.8); 
            }
            25% { 
                box-shadow: 
                    inset 0 0 60px rgba(0,0,0,0.9), 
                    0 0 100px rgba(16, 185, 129, 1),
                    0 0 180px rgba(245, 158, 11, 1); 
            }
            50% { 
                box-shadow: 
                    inset 0 0 60px rgba(0,0,0,0.9), 
                    0 0 120px rgba(236, 72, 153, 1),
                    0 0 200px rgba(139, 92, 246, 1); 
            }
            75% { 
                box-shadow: 
                    inset 0 0 60px rgba(0,0,0,0.9), 
                    0 0 100px rgba(245, 158, 11, 1),
                    0 0 180px rgba(16, 185, 129, 1); 
            }
            100% { 
                box-shadow: 
                    inset 0 0 60px rgba(0,0,0,0.9), 
                    0 0 80px rgba(139, 92, 246, 1),
                    0 0 150px rgba(236, 72, 153, 0.8); 
            }
        }
        
        @keyframes slotWin {
            0%, 100% { 
                transform: scale(1) rotate(0deg) rotateY(0deg) rotateX(0deg);
                box-shadow: 
                    0 0 80px rgba(255,215,0,1),
                    0 0 150px rgba(255,0,255,0.8),
                    0 0 220px rgba(0,255,255,0.6);
            }
            12.5% { 
                transform: scale(1.25) rotate(-10deg) rotateY(-10deg) rotateX(5deg);
                box-shadow: 
                    0 0 120px rgba(255,0,0,1),
                    0 0 200px rgba(255,215,0,1);
            }
            25% { 
                transform: scale(1.2) rotate(10deg) rotateY(10deg) rotateX(-5deg);
                box-shadow: 
                    0 0 120px rgba(255,165,0,1),
                    0 0 200px rgba(255,0,255,1);
            }
            37.5% { 
                transform: scale(1.25) rotate(-10deg) rotateY(-10deg) rotateX(5deg);
                box-shadow: 
                    0 0 120px rgba(0,255,0,1),
                    0 0 200px rgba(0,255,255,1);
            }
            50% { 
                transform: scale(1.2) rotate(10deg) rotateY(10deg) rotateX(-5deg);
                box-shadow: 
                    0 0 120px rgba(0,0,255,1),
                    0 0 200px rgba(139,92,246,1);
            }
            62.5% { 
                transform: scale(1.25) rotate(-10deg) rotateY(-10deg) rotateX(5deg);
                box-shadow: 
                    0 0 120px rgba(139,92,246,1),
                    0 0 200px rgba(236,72,153,1);
            }
            75% { 
                transform: scale(1.2) rotate(10deg) rotateY(10deg) rotateX(-5deg);
                box-shadow: 
                    0 0 120px rgba(236,72,153,1),
                    0 0 200px rgba(245,158,11,1);
            }
            87.5% { 
                transform: scale(1.25) rotate(-10deg) rotateY(-10deg) rotateX(5deg);
                box-shadow: 
                    0 0 120px rgba(16,185,129,1),
                    0 0 200px rgba(255,215,0,1);
            }
        }
        
        @keyframes rainbowGlow {
            0% { filter: hue-rotate(0deg) brightness(1.2) saturate(1.3); }
            100% { filter: hue-rotate(360deg) brightness(1.2) saturate(1.3); }
        }
        
        .controls { background: var(--bg-secondary); padding: 15px; border-radius: 16px; margin-top: 15px; }
        .bet-input { width: 100%; padding: 12px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.1rem; font-weight: 700; text-align: center; margin-bottom: 12px; }
        .spin-btn { width: 100%; padding: 15px; background: linear-gradient(135deg, #f59e0b 0%, #ef4444 50%, #f59e0b 100%); border: 4px solid #fbbf24; color: white; font-weight: 900; font-size: 1.3rem; cursor: pointer; transition: all 0.3s; border-radius: 16px; text-transform: uppercase; letter-spacing: 3px; box-shadow: 0 10px 50px rgba(245, 158, 11, 0.8); animation: spinBtnPulse 1.5s ease-in-out infinite; }
        .spin-btn:hover { transform: scale(1.05); box-shadow: 0 15px 60px rgba(245, 158, 11, 1); }
        .spin-btn:disabled { opacity: 0.5; cursor: not-allowed; animation: none; }
        
        @keyframes spinBtnPulse {
            0%, 100% { box-shadow: 0 10px 50px rgba(245, 158, 11, 0.8); }
            50% { box-shadow: 0 15px 70px rgba(245, 158, 11, 1); }
        }
        
        .result-box { margin-top: 10px; padding: 12px; border-radius: 12px; text-align: center; font-size: 1.1rem; font-weight: 800; }
        .result-box.win { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 2px solid var(--success); color: var(--success); }
        .result-box.loss { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); border: 2px solid var(--error); color: var(--error); }
        .back-btn { display: inline-block; padding: 12px 24px; background: var(--bg-secondary); border-radius: 12px; color: var(--text-primary); text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .back-btn:hover { background: var(--bg-tertiary); transform: translateY(-2px); }
        
        /* === ULTRA KRASSE PARTIKEL & COIN RAIN === */
        .coin-particle {
            position: fixed;
            font-size: 2rem;
            pointer-events: none;
            z-index: 99999;
            animation: coinRain 2s ease-in forwards;
            filter: drop-shadow(0 0 8px gold);
            text-shadow: 0 0 10px rgba(255,215,0,0.8);
        }
        
        @keyframes coinRain {
            0% {
                transform: translateY(-150px) rotate(0deg) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
                transform: translateY(0) rotate(180deg) scale(1.2);
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(120vh) rotate(1080deg) scale(0.3);
                opacity: 0;
            }
        }
        
        .explosion-particle {
            position: fixed;
            font-size: 1.5rem;
            pointer-events: none;
            z-index: 99998;
            animation: explode 1.2s ease-out forwards;
            filter: drop-shadow(0 0 5px currentColor);
        }
        
        @keyframes explode {
            0% {
                transform: translate(0, 0) scale(1) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translate(var(--tx), var(--ty)) scale(0) rotate(720deg);
                opacity: 0;
            }
        }
        

    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <a href="/casino.php" class="back-btn">← Zurück</a>
                <h1 style="font-size: 1.8rem; margin: 8px 0 4px 0;">🎰 Slot Machine</h1>
                <p style="color: var(--text-secondary); margin: 0; font-size: 0.85rem;">3 gleiche Symbole = Gewinn!</p>
            </div>
            <div class="balance-display">
                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px;">Guthaben</div>
                <div class="balance-value" id="balance"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
            </div>
        </div>

        <div class="slots-machine">
            <div class="slots-crown">⭐ JACKPOT ⭐</div>
            <div class="slots-lights"></div>
            
            <div class="slots-reels">
                <div class="slot-reel-frame">
                    <div class="slot-reel" id="reel1">
                        <div class="reel-strip"></div>
                    </div>
                </div>
                <div class="slot-reel-frame">
                    <div class="slot-reel" id="reel2">
                        <div class="reel-strip"></div>
                    </div>
                </div>
                <div class="slot-reel-frame">
                    <div class="slot-reel" id="reel3">
                        <div class="reel-strip"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="controls">
            <label style="display: block; margin-bottom: 6px; font-weight: 700; color: white; text-align: center; font-size: 0.9rem;">💰 Einsatz</label>
            <input type="number" id="betAmount" class="bet-input" min="0.10" max="100" step="0.10" value="1.00" placeholder="Einsatz">
            <button id="spinBtn" class="spin-btn" onclick="spin()">🎰 DREHEN</button>
        </div>

        <div id="resultBox"></div>

        <div style="margin-top: 12px; padding: 10px; background: var(--bg-secondary); border-radius: 12px; font-size: 0.75rem; color: var(--text-secondary);">
            <strong style="font-size: 0.85rem; color: var(--text-primary);">🎰 Auszahlungen (3 Symbole):</strong>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-top: 8px;">
                <div>💎💎💎 <span style="color: #FFD700; font-weight: 800;">50x</span></div>
                <div>7️⃣7️⃣7️⃣ <span style="color: #FF00FF; font-weight: 800;">25x</span></div>
                <div>🔔🔔🔔 <span style="color: #00FFFF; font-weight: 800;">15x</span></div>
                <div>⭐⭐⭐ <span style="color: #FFA500; font-weight: 800;">10x</span></div>
                <div>🎰🎰🎰 <span style="color: #FF0080; font-weight: 700;">8x</span></div>
                <div>🍇🍇🍇 <span style="color: #9F7AEA; font-weight: 700;">7x</span></div>
                <div>🍉🍉🍉 <span style="color: #10B981; font-weight: 700;">6x</span></div>
                <div>🍊🍊🍊 <span style="color: #F97316; font-weight: 700;">5x</span></div>
                <div>🍋🍋🍋 <span style="color: #EAB308; font-weight: 700;">4x</span></div>
                <div>🍒🍒🍒 <span style="color: #EF4444; font-weight: 700;">3x</span></div>
            </div>
        </div>
    </div>

    <script>
        let balance = parseFloat(<?= $casino_available_balance ?>) || 0;
        let spinning = false;
        
        // Audio Context für realistische Sounds
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        // Sound-Funktionen
        function playSpinSound() {
            // Verbessertes mechanisches Rollen mit mehreren Layern
            const baseFreq = 60;
            const duration = 0.05;
            
            // Layer 1: Tiefes Brummen (Motor)
            for (let i = 0; i < 40; i++) {
                setTimeout(() => {
                    const osc = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    const filter = audioContext.createBiquadFilter();
                    
                    osc.connect(filter);
                    filter.connect(gain);
                    gain.connect(audioContext.destination);
                    
                    filter.type = 'lowpass';
                    filter.frequency.value = 200;
                    
                    osc.frequency.value = baseFreq + Math.random() * 20;
                    osc.type = 'sawtooth';
                    
                    gain.gain.setValueAtTime(0.03, audioContext.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + duration);
                    
                    osc.start(audioContext.currentTime);
                    osc.stop(audioContext.currentTime + duration);
                }, i * 25);
            }
            
            // Layer 2: Klick-Geräusche (Mechanik)
            for (let i = 0; i < 30; i++) {
                setTimeout(() => {
                    const osc = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    const filter = audioContext.createBiquadFilter();
                    
                    osc.connect(filter);
                    filter.connect(gain);
                    gain.connect(audioContext.destination);
                    
                    filter.type = 'bandpass';
                    filter.frequency.value = 800 + Math.random() * 400;
                    filter.Q.value = 10;
                    
                    osc.frequency.value = 100 + Math.random() * 100;
                    osc.type = 'square';
                    
                    gain.gain.setValueAtTime(0.08, audioContext.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.03);
                    
                    osc.start(audioContext.currentTime);
                    osc.stop(audioContext.currentTime + 0.03);
                }, i * 35);
            }
            
            // Layer 3: High-Freq Rauschen (Metall)
            for (let i = 0; i < 20; i++) {
                setTimeout(() => {
                    const osc = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    const filter = audioContext.createBiquadFilter();
                    
                    osc.connect(filter);
                    filter.connect(gain);
                    gain.connect(audioContext.destination);
                    
                    filter.type = 'highpass';
                    filter.frequency.value = 2000;
                    
                    osc.frequency.value = 3000 + Math.random() * 2000;
                    osc.type = 'square';
                    
                    gain.gain.setValueAtTime(0.02, audioContext.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.02);
                    
                    osc.start(audioContext.currentTime);
                    osc.stop(audioContext.currentTime + 0.02);
                }, i * 50);
            }
        }
        
        function playStopSound(index) {
            // Verbessertes Stopp-Geräusch (CHUNK!)
            const time = audioContext.currentTime;
            
            // Impact Sound (tiefer Schlag)
            const osc1 = audioContext.createOscillator();
            const gain1 = audioContext.createGain();
            const filter1 = audioContext.createBiquadFilter();
            
            osc1.connect(filter1);
            filter1.connect(gain1);
            gain1.connect(audioContext.destination);
            
            filter1.type = 'lowpass';
            filter1.frequency.value = 300;
            
            osc1.frequency.setValueAtTime(120 - (index * 15), time);
            osc1.frequency.exponentialRampToValueAtTime(40, time + 0.08);
            osc1.type = 'sine';
            
            gain1.gain.setValueAtTime(0.3, time);
            gain1.gain.exponentialRampToValueAtTime(0.001, time + 0.08);
            
            osc1.start(time);
            osc1.stop(time + 0.08);
            
            // Click Sound (hoher Klick)
            const osc2 = audioContext.createOscillator();
            const gain2 = audioContext.createGain();
            
            osc2.connect(gain2);
            gain2.connect(audioContext.destination);
            
            osc2.frequency.value = 800 + (index * 100);
            osc2.type = 'square';
            
            gain2.gain.setValueAtTime(0.2, time);
            gain2.gain.exponentialRampToValueAtTime(0.001, time + 0.03);
            
            osc2.start(time);
            osc2.stop(time + 0.03);
            
            // Metall-Resonanz
            const osc3 = audioContext.createOscillator();
            const gain3 = audioContext.createGain();
            const filter3 = audioContext.createBiquadFilter();
            
            osc3.connect(filter3);
            filter3.connect(gain3);
            gain3.connect(audioContext.destination);
            
            filter3.type = 'bandpass';
            filter3.frequency.value = 2000;
            filter3.Q.value = 20;
            
            osc3.frequency.value = 2000;
            osc3.type = 'sine';
            
            gain3.gain.setValueAtTime(0.15, time + 0.01);
            gain3.gain.exponentialRampToValueAtTime(0.001, time + 0.15);
            
            osc3.start(time + 0.01);
            osc3.stop(time + 0.15);
        }
        
        function playWinSound(multiplier) {
            // Verbesserte Gewinn-Melodie mit Harmonie
            const notes = multiplier >= 25 ? [523, 659, 784, 1047, 1319] : 
                         multiplier >= 10 ? [523, 659, 784, 1047] : 
                         [523, 659, 784];
            
            notes.forEach((freq, i) => {
                setTimeout(() => {
                    // Hauptton
                    const osc1 = audioContext.createOscillator();
                    const gain1 = audioContext.createGain();
                    const filter1 = audioContext.createBiquadFilter();
                    
                    osc1.connect(filter1);
                    filter1.connect(gain1);
                    gain1.connect(audioContext.destination);
                    
                    filter1.type = 'lowpass';
                    filter1.frequency.value = 3000;
                    
                    osc1.frequency.value = freq;
                    osc1.type = 'triangle';
                    
                    gain1.gain.setValueAtTime(0.25, audioContext.currentTime);
                    gain1.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.4);
                    
                    osc1.start(audioContext.currentTime);
                    osc1.stop(audioContext.currentTime + 0.4);
                    
                    // Harmonie (Quinte)
                    const osc2 = audioContext.createOscillator();
                    const gain2 = audioContext.createGain();
                    
                    osc2.connect(gain2);
                    gain2.connect(audioContext.destination);
                    
                    osc2.frequency.value = freq * 1.5;
                    osc2.type = 'sine';
                    
                    gain2.gain.setValueAtTime(0.12, audioContext.currentTime);
                    gain2.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.4);
                    
                    osc2.start(audioContext.currentTime);
                    osc2.stop(audioContext.currentTime + 0.4);
                }, i * 120);
            });
            
            // Verbesserte Münzen-Sounds
            const coinCount = multiplier >= 25 ? 20 : multiplier >= 10 ? 12 : 8;
            for (let i = 0; i < coinCount; i++) {
                setTimeout(() => {
                    const time = audioContext.currentTime;
                    
                    // Münz-Klang mit Obertönen
                    for (let harmonic = 1; harmonic <= 3; harmonic++) {
                        const osc = audioContext.createOscillator();
                        const gain = audioContext.createGain();
                        const filter = audioContext.createBiquadFilter();
                        
                        osc.connect(filter);
                        filter.connect(gain);
                        gain.connect(audioContext.destination);
                        
                        filter.type = 'bandpass';
                        filter.frequency.value = 1500 * harmonic;
                        filter.Q.value = 5;
                        
                        osc.frequency.value = (800 + Math.random() * 600) * harmonic;
                        osc.type = 'sine';
                        
                        const volume = 0.08 / harmonic;
                        gain.gain.setValueAtTime(volume, time);
                        gain.gain.exponentialRampToValueAtTime(0.001, time + 0.15);
                        
                        osc.start(time);
                        osc.stop(time + 0.15);
                    }
                }, notes.length * 120 + i * 60);
            }
            
            // Jackpot-Fanfare bei großen Gewinnen
            if (multiplier >= 25) {
                setTimeout(() => {
                    const fanfare = [1047, 1319, 1568, 2093];
                    fanfare.forEach((freq, i) => {
                        setTimeout(() => {
                            const osc = audioContext.createOscillator();
                            const gain = audioContext.createGain();
                            
                            osc.connect(gain);
                            gain.connect(audioContext.destination);
                            
                            osc.frequency.value = freq;
                            osc.type = 'sawtooth';
                            
                            gain.gain.setValueAtTime(0.15, audioContext.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.6);
                            
                            osc.start(audioContext.currentTime);
                            osc.stop(audioContext.currentTime + 0.6);
                        }, i * 100);
                    });
                }, notes.length * 120 + 200);
            }
        }
        
        function playLoseSound() {
            // Verbesserter trauriger Sound mit Dissonanz
            const time = audioContext.currentTime;
            
            // Hauptton (absteigend)
            const osc1 = audioContext.createOscillator();
            const gain1 = audioContext.createGain();
            const filter1 = audioContext.createBiquadFilter();
            
            osc1.connect(filter1);
            filter1.connect(gain1);
            gain1.connect(audioContext.destination);
            
            filter1.type = 'lowpass';
            filter1.frequency.setValueAtTime(800, time);
            filter1.frequency.exponentialRampToValueAtTime(200, time + 0.6);
            
            osc1.frequency.setValueAtTime(350, time);
            osc1.frequency.exponentialRampToValueAtTime(180, time + 0.6);
            osc1.type = 'triangle';
            
            gain1.gain.setValueAtTime(0.12, time);
            gain1.gain.exponentialRampToValueAtTime(0.001, time + 0.6);
            
            osc1.start(time);
            osc1.stop(time + 0.6);
            
            // Dissonanter Ton (verstimmt)
            const osc2 = audioContext.createOscillator();
            const gain2 = audioContext.createGain();
            
            osc2.connect(gain2);
            gain2.connect(audioContext.destination);
            
            osc2.frequency.setValueAtTime(365, time);
            osc2.frequency.exponentialRampToValueAtTime(190, time + 0.6);
            osc2.type = 'sine';
            
            gain2.gain.setValueAtTime(0.08, time);
            gain2.gain.exponentialRampToValueAtTime(0.001, time + 0.6);
            
            osc2.start(time);
            osc2.stop(time + 0.6);
            
            // Dumpfer Bass
            const osc3 = audioContext.createOscillator();
            const gain3 = audioContext.createGain();
            
            osc3.connect(gain3);
            gain3.connect(audioContext.destination);
            
            osc3.frequency.setValueAtTime(100, time);
            osc3.frequency.exponentialRampToValueAtTime(50, time + 0.7);
            osc3.type = 'sine';
            
            gain3.gain.setValueAtTime(0.15, time);
            gain3.gain.exponentialRampToValueAtTime(0.001, time + 0.7);
            
            osc3.start(time);
            osc3.stop(time + 0.7);
        }
        
        // Realistische Slot-Symbole
        const symbols = ['🍒', '🍋', '🍊', '🍉', '🍇', '🔔', '⭐', '7️⃣', '💎', '🎰'];
        const payouts = { 
            '💎💎💎💎': 500, 
            '7️⃣7️⃣7️⃣7️⃣': 200, 
            '🔔🔔🔔🔔': 100,
            '⭐⭐⭐⭐': 50,
            'BARBARBARBAR': 40,
            '🍇🍇🍇🍇': 30,
            '🍉🍉🍉🍉': 25,
            '🍊🍊🍊🍊': 20,
            '��🍋🍋🍋': 15,
            '🍒🍒🍒🍒': 10
        };
        
        // Initialize reels with symbols
        function initializeReels() {
            for (let i = 1; i <= 3; i++) {
                const reel = document.querySelector(`#reel${i} .reel-strip`);
                reel.innerHTML = '';
                // Create long strip of random symbols for realistic rolling
                for (let j = 0; j < 30; j++) {
                    const symbol = document.createElement('div');
                    symbol.className = 'reel-symbol';
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
        
        // Realistische Roll-Animation
        function rollReel(reelElement, duration, finalSymbol) {
            return new Promise((resolve) => {
                const strip = reelElement.querySelector('.reel-strip');
                const symbolHeight = 170;
                let currentPos = 0;
                const totalSymbols = 30;
                const rollDistance = symbolHeight * totalSymbols;
                
                // Add winning symbol at end
                const finalSymbolEl = document.createElement('div');
                finalSymbolEl.className = 'reel-symbol';
                finalSymbolEl.textContent = finalSymbol;
                strip.appendChild(finalSymbolEl);
                
                const startTime = Date.now();
                const interval = setInterval(() => {
                    const elapsed = Date.now() - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    
                    // Ease out effect
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    currentPos = easeOut * rollDistance;
                    
                    strip.style.top = `-${currentPos}px`;
                    
                    if (progress >= 1) {
                        clearInterval(interval);
                        // Position exactly on final symbol
                        strip.style.top = `-${symbolHeight * totalSymbols}px`;
                        resolve();
                    }
                }, 16);
            });
        }

        async function spin() {
            if (spinning) return;
            const bet = parseFloat(document.getElementById('betAmount').value);
            if (bet < 0.10 || bet > balance) { alert('Ungültiger Einsatz!'); return; }

            spinning = true;
            document.getElementById('spinBtn').disabled = true;
            document.getElementById('resultBox').innerHTML = '';
            
            // Spin-Sound abspielen
            playSpinSound();

            try {
                const response = await fetch('/api/casino/play_slots.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bet })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    // Neue Balance berechnen: Verfügbar = Total - 10€ Reserve
                    const totalBalance = parseFloat(data.new_balance) || 0;
                    balance = Math.max(0, totalBalance - 10.00);
                    
                    // Reset reels
                    initializeReels();
                    
                    const results = data.result;
                    
                    // Start rolling all reels with staggered stop times
                    const reels = [
                        document.getElementById('reel1'),
                        document.getElementById('reel2'),
                        document.getElementById('reel3')
                    ];
                    
                    // Roll each reel with different durations for realistic effect
                    const rollPromises = reels.map((reel, i) => {
                        // Stop-Sound für jedes Reel
                        setTimeout(() => playStopSound(i), 2000 + (i * 300));
                        return rollReel(reel, 2000 + (i * 300), results[i]);
                    });
                    
                    await Promise.all(rollPromises);
                    
                    // Show results
                    const resultBox = document.getElementById('resultBox');
                    if (data.win_amount > 0) {
                        resultBox.innerHTML = `<div class="result-box win">🎉 GEWINN! +${data.win_amount.toFixed(2)}€ (${data.multiplier}x)</div>`;
                        
                        // Gewinn-Sound abspielen
                        playWinSound(data.multiplier);
                        
                        // Sanfte Gewinn-Effekte basierend auf Multiplikator
                        if (data.multiplier >= 25) {
                            // Große Gewinne (Diamond/7): mehr Effekte
                            createCoinRain(15);
                            createExplosion(reels[1]);
                        } else if (data.multiplier >= 10) {
                            // Mittlere Gewinne: moderate Effekte
                            createCoinRain(8);
                        } else {
                            // Kleine Gewinne: minimale Effekte
                            createCoinRain(5);
                        }
                    } else {
                        resultBox.innerHTML = `<div class="result-box loss">Verloren: -${bet.toFixed(2)}€</div>`;
                        
                        // Verlust-Sound abspielen
                        playLoseSound();
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
        
        // === ULTRA KRASSE PARTIKEL-EFFEKTE ===
        function createCoinRain(count) {
            for (let i = 0; i < count; i++) {
                setTimeout(() => {
                    const coin = document.createElement('div');
                    coin.className = 'coin-particle';
                    coin.textContent = ['💰', '💎', '🪙', '💵', '🏆'][Math.floor(Math.random() * 5)];
                    coin.style.left = Math.random() * window.innerWidth + 'px';
                    coin.style.animationDelay = Math.random() * 0.5 + 's';
                    coin.style.animationDuration = (1.5 + Math.random()) + 's';
                    document.body.appendChild(coin);
                    setTimeout(() => coin.remove(), 3000);
                }, i * 30);
            }
        }
        
        function createExplosion(centerElement) {
            const rect = centerElement.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const particles = ['✨', '⭐', '🌟', '💫'];
            
            for (let i = 0; i < 12; i++) {
                const particle = document.createElement('div');
                particle.className = 'explosion-particle';
                particle.textContent = particles[Math.floor(Math.random() * particles.length)];
                particle.style.left = centerX + 'px';
                particle.style.top = centerY + 'px';
                
                const angle = (Math.PI * 2 * i) / 12;
                const distance = 100 + Math.random() * 100;
                const tx = Math.cos(angle) * distance;
                const ty = Math.sin(angle) * distance;
                
                particle.style.setProperty('--tx', tx + 'px');
                particle.style.setProperty('--ty', ty + 'px');
                particle.style.animationDelay = Math.random() * 0.2 + 's';
                
                document.body.appendChild(particle);
                setTimeout(() => particle.remove(), 1500);
            }
        }
        

    </script>
</body>
</html>

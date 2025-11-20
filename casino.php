<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin_user = is_admin();
$page_title = 'Casino';

// Get user balance (MINIMUM 10€ RESERVE!)
$balance = 0.0;
$stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();
$balance = floatval($balance ?? 0);

// Casino nur ab 10€+ Guthaben zugänglich
$casino_available_balance = max(0, $balance - 10.00);
$casino_locked = ($balance < 10.00);

// Get casino stats
$total_wagered = 0;
$total_won = 0;
$total_lost = 0;
$games_played = 0;

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as games,
        IFNULL(SUM(bet_amount), 0) as wagered,
        IFNULL(SUM(win_amount), 0) as won,
        IFNULL(SUM(bet_amount - win_amount), 0) as lost
    FROM casino_history 
    WHERE user_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($games_played, $total_wagered, $total_won, $total_lost);
$stmt->fetch();
$stmt->close();

// Recent big wins (last 10)
$recent_wins = [];
$result = $conn->query("
    SELECT ch.*, u.name, u.username 
    FROM casino_history ch
    JOIN users u ON ch.user_id = u.id
    WHERE ch.win_amount > ch.bet_amount
    ORDER BY ch.created_at DESC 
    LIMIT 10
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_wins[] = $row;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
    <style>
        .casino-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 32px;
        }
        
        .game-card {
            background: var(--bg-tertiary);
            border-radius: 16px;
            padding: 32px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
            overflow-y: auto;
        }
        
        .game-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), #a855f7);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .game-card:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: 0 12px 40px rgba(139, 92, 246, 0.3);
        }
        
        .game-card:hover::before {
            opacity: 1;
        }
        
        .game-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            display: block;
        }
        
        .game-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .game-desc {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 16px;
        }
        
        .game-stats {
            display: flex;
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        
        .game-stat {
            flex: 1;
        }
        
        .game-stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .game-stat-value {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .game-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .game-modal.active {
            display: flex;
        }
        
        .game-modal-content {
            background: var(--bg-tertiary);
            border-radius: 24px;
            padding: 32px;
            max-width: 900px;
            width: 95%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .game-modal-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .game-modal-content::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }
        
        .game-modal-content::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }
        
        .game-modal-content::-webkit-scrollbar-thumb:hover {
            background: var(--success);
        }
        
        /* Crash Game specific adjustments */
        #crashModal .game-modal-content {
            max-height: 80vh;
            padding: 24px;
        }
        
        #crashModal .crash-graph {
            height: 400px;
            min-height: 400px;
        }
        
        #crashModal h2 {
            margin-bottom: 12px;
        }
        
        #crashModal p {
            margin-bottom: 12px;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--bg-secondary);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--text-primary);
            transition: all 0.2s;
            z-index: 10000;
        }
        
        .modal-close:hover {
            background: var(--error);
            transform: scale(1.1);
        }
        
        .balance-display {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 24px;
        }
        
        .balance-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .balance-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--success);
        }
        
        /* Quick Bet Buttons */
        .quick-bet-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .quick-bet-btn {
            padding: 10px 16px;
            background: var(--bg-secondary);
            border: 2px solid transparent;
            border-radius: 8px;
            color: var(--text-primary);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-bet-btn:hover {
            background: var(--bg-tertiary);
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .quick-bet-btn.active {
            background: linear-gradient(135deg, var(--accent), #a855f7);
            border-color: var(--accent);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        
        /* Slots Specific - ULTRA REALISTISCH LAS VEGAS STYLE */
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
            max-width: 800px;
        }
        
        /* Vegas Crown/Top Section */
        .slots-crown {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #FFD700, #FFA500, #FFD700);
            padding: 15px 80px;
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
                box-shadow: 
                    0 0 30px rgba(255,215,0,0.8),
                    0 0 60px rgba(255,165,0,0.6),
                    inset 0 2px 8px rgba(255,255,255,0.4);
            }
            50% { 
                box-shadow: 
                    0 0 50px rgba(255,215,0,1),
                    0 0 100px rgba(255,165,0,0.9),
                    inset 0 2px 8px rgba(255,255,255,0.6);
            }
        }
        
        /* LED Border Lights */
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
            perspective: 1500px;
            position: relative;
            flex-wrap: wrap;
            background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.6) 100%);
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: inset 0 10px 30px rgba(0,0,0,0.8);
        }
        
        /* Chrome/Metal Frame um jeden Reel */
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
            width: 160px;
            height: 180px;
            flex-shrink: 0;
            background: linear-gradient(145deg, #0a0015, #1a0033, #0a0015);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6rem;
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
        
        /* Glass/Screen Effect */
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
        
        @keyframes reelIdle {
            0%, 100% { 
                transform: translateY(0) scale(1);
                box-shadow: 
                    inset 0 0 50px rgba(0,0,0,0.9),
                    inset 0 4px 12px rgba(139, 92, 246, 0.2),
                    0 0 40px rgba(139, 92, 246, 0.3);
            }
            50% { 
                transform: translateY(-2px) scale(1.01);
                box-shadow: 
                    inset 0 0 50px rgba(0,0,0,0.9),
                    inset 0 4px 12px rgba(139, 92, 246, 0.4),
                    0 0 60px rgba(139, 92, 246, 0.5);
            }
        }
        
        /* Scanning Light Effect */
        .slot-reel::before {
            content: '';
            position: absolute;
            top: -100%;
            left: 0;
            width: 100%;
            height: 40px;
            background: linear-gradient(180deg, 
                transparent,
                rgba(255,255,255,0.15),
                transparent
            );
            animation: scanLine 4s ease-in-out infinite;
            pointer-events: none;
        }
        
        @keyframes scanLine {
            0% { top: -100%; }
            50% { top: 100%; }
            100% { top: -100%; }
        }
        
        .slot-reel.spinning {
            animation: 
                slotSpin 0.05s linear infinite,
                slotShake 0.12s ease-in-out infinite,
                slotGlow 0.15s ease-in-out infinite;
            filter: brightness(1.4) saturate(1.3) blur(1px);
        }
        
        .slot-reel.spinning::before {
            animation: none;
            background: linear-gradient(180deg, 
                rgba(255,215,0,0.3),
                rgba(255,165,0,0.3),
                rgba(255,215,0,0.3)
            );
        }
        
        .slot-reel.stopping {
            animation: 
                slotBounce 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards,
                slotFlash 0.4s ease-out,
                slotExplode 0.6s ease-out;
        }
        
        .slot-reel.winning {
            animation: 
                slotWin 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite,
                rainbowGlow 1s linear infinite;
        }
        
        .slot-reel.winning::before {
            animation: none;
            background: linear-gradient(180deg, 
                rgba(255,215,0,0.6),
                rgba(255,255,255,0.4),
                rgba(255,215,0,0.6)
            );
        }
        
        @keyframes slotSpin {
            0% { content: '🍒'; }
            14% { content: '🍋'; }
            28% { content: '⭐'; }
            42% { content: '7️⃣'; }
            57% { content: '💎'; }
            71% { content: '🔔'; }
            85% { content: '🍀'; }
            100% { content: '🍒'; }
        }
        
        @keyframes slotShake {
            0%, 100% { transform: translateX(0) scale(1) rotate(0deg); }
            25% { transform: translateX(-5px) scale(1.03) rotate(-2deg); }
            50% { transform: translateX(0) scale(0.97) rotate(0deg); }
            75% { transform: translateX(5px) scale(1.03) rotate(2deg); }
        }
        
        @keyframes slotGlow {
            0%, 100% {
                box-shadow: 
                    inset 0 0 40px rgba(0,0,0,0.7),
                    0 15px 40px rgba(0,0,0,0.6),
                    0 0 60px rgba(139, 92, 246, 0.6),
                    0 0 100px rgba(236, 72, 153, 0.3);
            }
            50% {
                box-shadow: 
                    inset 0 0 40px rgba(0,0,0,0.7),
                    0 15px 40px rgba(0,0,0,0.6),
                    0 0 100px rgba(16, 185, 129, 0.9),
                    0 0 150px rgba(236, 72, 153, 0.6);
            }
        }
        
        @keyframes slotBounce {
            0% { transform: scale(1) rotateY(0deg); }
            25% { transform: scale(1.25) rotateY(15deg); }
            40% { transform: scale(0.9) rotateY(-8deg); }
            60% { transform: scale(1.1) rotateY(5deg); }
            80% { transform: scale(0.95) rotateY(-2deg); }
            100% { transform: scale(1) rotateY(0deg); }
        }
        
        @keyframes slotFlash {
            0% { filter: brightness(1) hue-rotate(0deg); }
            25% { filter: brightness(1.8) hue-rotate(90deg); }
            50% { filter: brightness(1.3) hue-rotate(180deg); }
            75% { filter: brightness(1.6) hue-rotate(270deg); }
            100% { filter: brightness(1) hue-rotate(360deg); }
        }
        
        @keyframes slotExplode {
            0% { transform: scale(1); }
            10% { transform: scale(1.3); }
            20% { transform: scale(0.9); }
            30% { transform: scale(1.15); }
            100% { transform: scale(1); }
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
        
        @keyframes spin {
            0% { transform: translateY(0); }
            100% { transform: translateY(-100%); }
        }
        
        /* Vegas Coin Rain Effect */
        .vegas-coin {
            position: fixed;
            font-size: 2.5rem;
            pointer-events: none;
            z-index: 99999;
            animation: coinFall 2s ease-in forwards;
            filter: drop-shadow(0 0 10px gold);
        }
        
        @keyframes coinFall {
            0% {
                transform: translateY(-100px) rotate(0deg) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
                transform: translateY(0) rotate(180deg) scale(1);
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg) scale(0.5);
                opacity: 0;
            }
        }
        
        /* Lever Pull Animation */
        .slot-lever {
            position: absolute;
            right: -60px;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 200px;
            background: linear-gradient(90deg, #8B4513, #A0522D, #8B4513);
            border-radius: 20px;
            box-shadow: 
                inset 2px 0 8px rgba(0,0,0,0.6),
                inset -2px 0 8px rgba(255,255,255,0.2),
                0 10px 30px rgba(0,0,0,0.8);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .slot-lever::before {
            content: '🔴';
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 3rem;
            filter: drop-shadow(0 5px 15px rgba(255,0,0,0.8));
        }
        
        .slot-lever.pulled {
            animation: leverPull 1s ease-out;
        }
        
        @keyframes leverPull {
            0% { transform: translateY(-50%) rotate(0deg); }
            30% { transform: translateY(-30%) rotate(15deg); }
            100% { transform: translateY(-50%) rotate(0deg); }
        }
        
        /* Wheel Specific */
        .wheel-container-wrapper {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
        }
        
        .wheel-sparkles {
            position: absolute;
            width: min(500px, 90vw);
            height: min(500px, 90vw);
            pointer-events: none;
            z-index: 5;
        }
        
        .wheel-container {
            width: min(400px, 85vw);
            height: min(400px, 85vw);
            position: relative;
            filter: drop-shadow(0 10px 40px rgba(0,0,0,0.5));
        }
        
        #wheelCanvas {
            width: 100%;
            height: 100%;
            transition: transform 5s cubic-bezier(0.17, 0.67, 0.12, 0.99);
        }
        
        .wheel-pointer {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 3rem;
            z-index: 10;
            filter: drop-shadow(0 5px 15px rgba(139, 92, 246, 0.8));
            animation: pointerBounce 0.6s ease-in-out infinite;
        }
        
        @keyframes pointerBounce {
            0%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            50% {
                transform: translateX(-50%) translateY(-8px);
            }
        }
        
        .sparkle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
            animation: sparkleFloat 2s ease-in-out infinite;
        }
        
        @keyframes sparkleFloat {
            0%, 100% {
                transform: translateY(0) scale(0);
                opacity: 0;
            }
            50% {
                transform: translateY(-30px) scale(1);
                opacity: 1;
            }
        }
        
        /* Crash Specific */
        .crash-graph {
            width: 100%;
            height: clamp(400px, 60vh, 600px);
            background: var(--bg-secondary);
            border-radius: 12px;
            position: relative;
            overflow-y: auto;
            margin: 24px 0;
        }
        
        .crash-line {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 2px;
            height: 0;
            background: var(--success);
            transition: all 0.05s linear;
        }
        
        }

        /* Easter Eggs */
        .easter-egg {
            animation: easterEggFloat 3s ease-in-out infinite;
            filter: drop-shadow(0 0 10px rgba(255,255,255,0.8));
            z-index: 3;
        }
        
        @keyframes easterEggFloat {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.8;
            }
            25% {
                transform: translateY(-10px) rotate(5deg);
                opacity: 1;
            }
            50% {
                transform: translateY(-5px) rotate(-5deg);
                opacity: 0.9;
            }
            75% {
                transform: translateY(-15px) rotate(3deg);
                opacity: 1;
            }
        }
        
        #egg9 {
            animation: kingPulse 1s ease-in-out infinite;
        }
        
        @keyframes kingPulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1) rotate(0deg);
                filter: drop-shadow(0 0 20px gold);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.2) rotate(10deg);
                filter: drop-shadow(0 0 40px gold);
            }
        }
        
        /* Rocket Crash Game - Complete Overhaul */
        .crash-graph {
            position: relative;
            height: clamp(400px, 60vh, 600px);
            background: linear-gradient(180deg, 
                #87CEEB 0%,    /* Sky blue (earth) */
                #4A90E2 20%,   /* Deep blue */
                #2C5AA0 40%,   /* Darker blue */
                #1a1a2e 70%,   /* Dark space */
                #0a0a1e 100%   /* Deep space */
            );
            border-radius: 16px;
            margin-bottom: 24px;
            overflow-y: auto;
            box-shadow: inset 0 0 50px rgba(0,0,0,0.5);
            transition: background 0.5s ease;
        }
        
        /* Dynamic background based on altitude */
        .crash-graph.space-level-1 {
            background: linear-gradient(180deg, 
                #4A90E2 0%, #2C5AA0 30%, #1a1a2e 60%, #0a0a1e 100%
            );
        }
        
        .crash-graph.space-level-2 {
            background: linear-gradient(180deg, 
                #2C5AA0 0%, #1a1a2e 40%, #0a0a1e 70%, #000 100%
            );
        }
        
        .crash-graph.space-level-3 {
            background: linear-gradient(180deg, 
                #1a1a2e 0%, #0a0a1e 50%, #000 100%
            );
        }
        
        .crash-sky {
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        /* Earth ground */
        .ground {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 120px;
            background: linear-gradient(180deg, #228B22 0%, #1a5c1a 100%);
            transition: transform 0.1s linear;
            z-index: 1;
        }
        
        .ground::before {
            content: '🏙️🌳🏠🌲🏢🌴🏡🌲🏭🌳';
            position: absolute;
            top: -20px;
            left: 0;
            width: 100%;
            font-size: 2rem;
            text-align: center;
            letter-spacing: 10px;
        }
        
        /* ROCKET - centered, moves up */
        .airplane {
            position: absolute;
            left: 50%;
            bottom: 150px;
            transform: translateX(-50%) rotate(-45deg);
            font-size: 5rem;
            filter: drop-shadow(0 0 20px rgba(255,100,0,0.8)) 
                    drop-shadow(0 0 40px rgba(255,200,0,0.6));
            transition: all 0.05s linear;
            z-index: 10;
        }
        
        .airplane.flying {
            animation: rocketShake 0.1s ease-in-out infinite, rocketGlow 0.5s ease-in-out infinite;
        }
        
        .airplane.crashed {
            animation: rocketCrash 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }
        
        @keyframes rocketShake {
            0%, 100% { transform: translateX(-50%) rotate(-45deg); }
            25% { transform: translateX(-48%) rotate(-47deg); }
            75% { transform: translateX(-52%) rotate(-43deg); }
        }
        
        @keyframes rocketGlow {
            0%, 100% {
                filter: drop-shadow(0 0 20px rgba(255,100,0,0.8)) 
                        drop-shadow(0 0 40px rgba(255,200,0,0.6));
            }
            50% {
                filter: drop-shadow(0 0 30px rgba(255,100,0,1)) 
                        drop-shadow(0 0 60px rgba(255,200,0,1));
            }
        }
        
        @keyframes rocketCrash {
            0% {
                transform: translateX(-50%) rotate(0deg) scale(1);
                opacity: 1;
            }
            20% {
                transform: translateX(-40%) rotate(-30deg) scale(1.2);
            }
            40% {
                transform: translateX(-60%) rotate(30deg) scale(1.1);
            }
            60% {
                transform: translateX(-50%) rotate(-60deg) scale(0.9);
            }
            80% {
                transform: translateX(-30%) rotate(-45deg) scale(0.6);
            }
            100% {
                transform: translateX(-50%) rotate(180deg) scale(0.2);
                opacity: 0;
            }
        }
        
        /* Rocket flame */
            0%, 100% { 
                transform: translateX(-50%) rotate(-45deg) scaleY(1);
                filter: brightness(1);
            }
            50% { 
                transform: translateX(-50%) rotate(-45deg) scaleY(1.3);
                filter: brightness(1.5);
            }
        }
        
        /* Stars - more visible in space */
        .stars, .stars2 {
            position: absolute;
            width: 2px;
            height: 2px;
            background: transparent;
            opacity: 0;
            transition: opacity 1s ease;
            box-shadow: 
                50px 30px #FFF, 100px 50px #FFF, 150px 80px #FFF,
                200px 40px #FFF, 250px 90px #FFF, 300px 60px #FFF,
                350px 100px #FFF, 400px 20px #FFF, 450px 70px #FFF,
                500px 110px #FFF, 550px 45px #FFF, 600px 85px #FFF,
                80px 150px #FFF, 180px 200px #FFF, 280px 180px #FFF,
                380px 220px #FFF, 480px 160px #FFF, 580px 190px #FFF;
        }
        
        .crash-graph.space-level-1 .stars,
        .crash-graph.space-level-2 .stars,
        .crash-graph.space-level-3 .stars {
            opacity: 0.8;
        }
        
        .stars2 {
            box-shadow: 
                75px 45px #FFF, 125px 65px #FFF, 175px 25px #FFF,
                225px 95px #FFF, 275px 35px #FFF, 325px 75px #FFF,
                120px 180px #FFF, 220px 210px #FFF, 320px 195px #FFF;
        }
        
        /* Sun - disappears in space */
        .celestial-body {
            position: absolute;
            top: 10%;
            right: 10%;
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, #ffd700, #ffed4e);
            border-radius: 50%;
            box-shadow: 0 0 20px #ffd700, 0 0 40px #ffd700;
            animation: celestialPulse 4s ease-in-out infinite;
            transition: opacity 1s ease;
        }
        
        .crash-graph.space-level-2 .celestial-body,
        .crash-graph.space-level-3 .celestial-body {
            display: none;
        }
        
        @keyframes celestialPulse {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
        }
        
        /* Altitude meter */
        .altitude-meter {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(0,0,0,0.8);
            padding: 12px 20px;
            border-radius: 12px;
            border: 2px solid #10b981;
            min-width: 140px;
            z-index: 100;
        }
        
        .altitude-label {
            font-size: 0.75rem;
            color: #10b981;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .altitude-value {
            font-size: 1.8rem;
            color: #fff;
            font-weight: 900;
            margin-top: 4px;
        }
        
        /* Speed meter */
        .speed-meter {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            padding: 12px 20px;
            border-radius: 12px;
            border: 2px solid #8b5cf6;
            min-width: 140px;
            z-index: 100;
        }
        
        .speed-label {
            font-size: 0.75rem;
            color: #8b5cf6;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .speed-value {
            font-size: 1.8rem;
            color: #fff;
            font-weight: 900;
            margin-top: 4px;
        }
        
        /* Crash multiplier */
        .crash-multiplier {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 5rem;
            font-weight: 900;
            color: var(--success);
            text-shadow: 0 0 30px rgba(16, 185, 129, 0.8);
            z-index: 5;
            pointer-events: none;
        }
        
        /* Explosion */
        .explosion {
            position: absolute;
            font-size: 15rem;
            z-index: 20;
            animation: explosionBurst 1s ease-out forwards;
            filter: drop-shadow(0 0 40px rgba(255, 100, 0, 1));
        }
        
        @keyframes explosionBurst {
            0% {
                transform: scale(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                transform: scale(0.3) rotate(20deg);
                opacity: 1;
            }
            30% {
                transform: scale(1.8) rotate(-15deg);
                opacity: 1;
            }
            100% {
                transform: scale(3) rotate(5deg);
                opacity: 0;
            }
        }
        
        .shockwave {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            border: 4px solid rgba(255, 100, 0, 0.8);
            border-radius: 50%;
            animation: shockwaveExpand 0.8s ease-out forwards;
        }
        
        @keyframes shockwaveExpand {
            0% {
                transform: translate(-50%, -50%) scale(0);
                opacity: 1;
            }
            100% {
                transform: translate(-50%, -50%) scale(5);
                opacity: 0;
            }
        }
        
        /* Danger indicator */
        
        @keyframes dangerPulse {
            0%, 100% {
                transform: translateX(-50%) scale(1);
            }
            50% {
                transform: translateX(-50%) scale(1.1);
            }
        }
        
        /* Easter Eggs - repositioned for vertical flight */
        .easter-egg {
            position: absolute;
            animation: easterEggFloat 3s ease-in-out infinite;
            filter: drop-shadow(0 0 10px rgba(255,255,255,0.8));
            z-index: 4;
            transition: all 0.1s linear;
        }
        
        @keyframes easterEggFloat {
            0%, 100% {
                transform: translateY(0) scale(1);
                opacity: 0.9;
            }
            50% {
                transform: translateY(-10px) scale(1.1);
                opacity: 1;
            }
        }

        /* Space Elements - appear at different altitudes */
        .space-element {
            position: absolute;
            opacity: 0;
            transition: opacity 1s ease;
            pointer-events: none;
        }
        
        /* Earth atmosphere (1.5x-3x) */
        .crash-graph.space-level-1 .clouds {
            opacity: 0.4;
        }
        
        /* Planets (3x+) */
        .planet {
            position: absolute;
            border-radius: 50%;
            animation: planetFloat 8s ease-in-out infinite;
        }
        
        .planet-mars {
            top: 15%;
            right: 10%;
            width: 80px;
            height: 80px;
            font-size: 4rem;
            opacity: 0;
        }
        
        .crash-graph.space-level-2 .planet-mars {
            opacity: 1;
        }
        
        .planet-jupiter {
            top: 60%;
            left: 5%;
            font-size: 5rem;
            opacity: 0;
        }
        
        .crash-graph.space-level-3 .planet-jupiter {
            opacity: 1;
        }
        
        .planet-saturn {
            top: 25%;
            left: 15%;
            font-size: 4.5rem;
            opacity: 0;
        }
        
        .crash-graph.space-level-3 .planet-saturn {
            opacity: 1;
        }
        
        @keyframes planetFloat {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-20px) scale(1.05);
            }
        }
        
        /* Galaxy background (5x+) */
        .galaxy {
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            opacity: 0;
            filter: blur(3px);
            animation: galaxySpin 20s linear infinite;
        }
        
        .galaxy-1 {
            top: 10%;
            left: 30%;
            background: radial-gradient(circle, rgba(138, 43, 226, 0.4), transparent);
        }
        
        .galaxy-2 {
            bottom: 15%;
            right: 20%;
            background: radial-gradient(circle, rgba(0, 191, 255, 0.4), transparent);
        }
        
        .crash-graph.space-level-3 .galaxy {
            opacity: 1;
        }
        
        @keyframes galaxySpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Nebula (7x+) */
        .nebula {
            position: absolute;
            width: 200px;
            height: 200px;
            opacity: 0;
            filter: blur(5px);
            animation: nebulaFloat 15s ease-in-out infinite;
        }
        
        .nebula-1 {
            top: 30%;
            right: 15%;
            background: radial-gradient(ellipse, rgba(255, 0, 255, 0.3), transparent);
        }
        
        .nebula-2 {
            bottom: 25%;
            left: 10%;
            background: radial-gradient(ellipse, rgba(0, 255, 255, 0.3), transparent);
        }
        
        @keyframes nebulaFloat {
            0%, 100% {
                transform: scale(1) rotate(0deg);
            }
            50% {
                transform: scale(1.2) rotate(5deg);
            }
        }
        
        /* Milky Way (10x+) */
        .milky-way {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            background: 
                radial-gradient(ellipse 800px 600px at 30% 50%, rgba(255,255,255,0.1), transparent),
                radial-gradient(ellipse 600px 400px at 70% 30%, rgba(200,200,255,0.1), transparent);
            pointer-events: none;
        }
        
        /* Asteroids (random) */
        .asteroid {
            position: absolute;
            opacity: 0;
            font-size: 1.5rem;
            animation: asteroidDrift 5s linear infinite;
        }
        
        @keyframes asteroidDrift {
            from {
                transform: translateX(-50px) translateY(-50px) rotate(0deg);
            }
            to {
                transform: translateX(50px) translateY(50px) rotate(360deg);
            }
        }
        
        /* Shooting stars */
        .shooting-star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: white;
            opacity: 0;
            box-shadow: 0 0 4px white;
        }
        
        .crash-graph.space-level-2 .shooting-star,
        .crash-graph.space-level-3 .shooting-star {
            animation: shootingStar 3s ease-out infinite;
        }
        
        @keyframes shootingStar {
            0% {
                transform: translateX(0) translateY(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 0.5;
            }
            100% {
                transform: translateX(-300px) translateY(200px);
                opacity: 0;
            }
        }
        
        .shooting-star-1 { top: 10%; right: 80%; animation-delay: 0s; }
        .shooting-star-2 { top: 30%; right: 60%; animation-delay: 1.5s; }
        .shooting-star-3 { top: 50%; right: 40%; animation-delay: 2.5s; }

        /* Enhanced background elements */
        
        /* More stars */
        .stars3, .stars4 {
            position: absolute;
            width: 2px;
            height: 2px;
            background: transparent;
            animation: starsAnimation 30s linear infinite;
        }
        
        .stars3 {
            box-shadow: 
                120px 60px #FFF, 220px 90px #FFF, 340px 120px #FFF,
                80px 140px #FFF, 190px 160px #FFF, 310px 50px #FFF,
                420px 180px #FFF, 140px 30px #FFF, 260px 200px #FFF,
                380px 70px #FFF, 60px 220px #FFF, 500px 100px #FFF;
        }
        
        .stars4 {
            box-shadow:
                90px 80px #FFF, 210px 110px #FFF, 330px 40px #FFF,
                110px 170px #FFF, 230px 60px #FFF, 350px 190px #FFF,
                470px 90px #FFF, 170px 210px #FFF, 290px 130px #FFF;
            animation: starsAnimation 40s linear infinite;
        }
        
        /* Twinkling stars */
        .twinkle-star {
            position: absolute;
            color: white;
            font-size: 1rem;
            animation: twinkle 2s ease-in-out infinite;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }
        
        .twinkle-1 { top: 15%; left: 10%; animation-delay: 0s; }
        .twinkle-2 { top: 25%; right: 15%; animation-delay: 0.5s; }
        .twinkle-3 { top: 45%; left: 20%; animation-delay: 1s; }
        .twinkle-4 { top: 65%; right: 25%; animation-delay: 1.5s; }
        .twinkle-5 { top: 80%; left: 30%; animation-delay: 2s; }
        
        /* Comets */
        .comet {
            position: absolute;
            width: 3px;
            height: 3px;
            background: white;
            border-radius: 50%;
            opacity: 0;
        }
        
        .crash-graph.space-level-2 .comet,
        .crash-graph.space-level-3 .comet {
            animation: cometFly 4s ease-out infinite;
        }
        
        @keyframes cometFly {
            0% {
                opacity: 0;
                transform: translate(0, 0);
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 0.5;
            }
            100% {
                opacity: 0;
                transform: translate(-400px, 300px);
                box-shadow: 0 0 8px 2px rgba(255, 255, 255, 0.5);
            }
        }
        
        .comet-1 { top: 10%; right: 20%; animation-delay: 0s; }
        .comet-2 { top: 30%; right: 10%; animation-delay: 2s; }
        .comet-3 { top: 50%; right: 30%; animation-delay: 3.5s; }
        
        /* Distant planets */
        .distant-planet {
            position: absolute;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 2s ease;
        }
        
        .planet-small-1 {
            top: 20%;
            right: 25%;
            width: 30px;
            height: 30px;
            background: radial-gradient(circle at 30% 30%, #a78bfa, #7c3aed);
        }
        
        .planet-small-2 {
            top: 70%;
            left: 20%;
            width: 25px;
            height: 25px;
            background: radial-gradient(circle at 30% 30%, #60a5fa, #3b82f6);
        }
        
        .planet-small-3 {
            top: 40%;
            left: 10%;
            width: 35px;
            height: 35px;
            background: radial-gradient(circle at 30% 30%, #fb923c, #ea580c);
        }
        
        .crash-graph.space-level-2 .distant-planet,
        .crash-graph.space-level-3 .distant-planet {
            opacity: 0.6;
        }
        
        /* Space dust */
        .space-dust {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            pointer-events: none;
        }
        
        .dust-particle {
            position: absolute;
            width: 1px;
            height: 1px;
            background: white;
            border-radius: 50%;
            opacity: 0.4;
        }
        
        .crash-graph.space-level-3 .space-dust {
            opacity: 1;
        }
        
        /* Constellation lines */
        .constellation {
            position: absolute;
            opacity: 0;
        }
        
        .constellation svg {
            width: 200px;
            height: 200px;
        }
        
        .constellation-1 { top: 10%; left: 60%; }
        .constellation-2 { bottom: 20%; right: 10%; }
        
        .crash-graph.space-level-3 .constellation {
            opacity: 0.3;
        }
        
        /* Pulsing stars */
        .pulse-star {
            position: absolute;
            font-size: 1.5rem;
            opacity: 0;
            animation: pulseStar 3s ease-in-out infinite;
        }
        
        @keyframes pulseStar {
            0%, 100% {
                opacity: 0;
                transform: scale(0.5);
            }
            50% {
                opacity: 1;
                transform: scale(1.2);
            }
        }
        
        .crash-graph.space-level-2 .pulse-star,
        .crash-graph.space-level-3 .pulse-star {
            opacity: 1;
        }
        
        .pulse-1 { top: 35%; left: 75%; animation-delay: 0s; }
        .pulse-2 { top: 55%; right: 20%; animation-delay: 1s; }
        .pulse-3 { top: 75%; left: 15%; animation-delay: 2s; }
    
    
    /* 📱 MOBILE RESPONSIVE */
    @media (max-width: 768px) {
        .game-modal-content {
            max-width: 100vw !important;
            max-height: 95vh !important;
            overflow-y: auto !important;
            padding: 12px !important;
            margin: 0 !important;
            border-radius: 12px !important;
        }
        
        .game-card {
            min-width: 100% !important;
            padding: 20px !important;
        }
        
        .casino-grid {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }
        
        .quick-bet-btn, .slot-bet-btn {
            font-size: 0.85rem !important;
            padding: 10px 6px !important;
            min-height: 48px;
            min-width: 48px;
        }
        
        .game-modal h2 {
            font-size: 1.25rem !important;
            margin-bottom: 12px !important;
        }
        
        .balance-display .balance-value {
            font-size: 1.25rem !important;
        }
        
        /* Slots spezifisch */
        .slots-reels {
            gap: 10px !important;
            margin: 15px 0 !important;
        }
        
        .slot-reel {
            width: 90px !important;
            height: 100px !important;
            font-size: 3.5rem !important;
        }
        
        .slots-reels-mega {
            gap: 8px !important;
        }
        
        .slot-reel-mega {
            width: 80px !important;
            height: 100px !important;
        }
        
        /* Plinko Canvas */
        #plinkoCanvas {
            max-width: 100% !important;
            height: auto !important;
        }
        
        .plinko-bet-btn, .plinko-balls-btn {
            font-size: 0.85rem !important;
            padding: 10px 8px !important;
            min-height: 48px;
            min-width: 48px;
        }
        
        /* Crash Graph */
        .crash-graph {
            height: 400px !important;
            min-height: 400px !important;
        }
        
        .airplane {
            font-size: 3rem !important;
        }
        
        .altitude-meter, .speed-meter {
            padding: 8px 12px !important;
            min-width: 100px !important;
        }
        
        .altitude-value, .speed-value {
            font-size: 1.3rem !important;
        }
        
        /* Wheel */
        .wheel-container {
            width: min(300px, 85vw) !important;
            height: min(300px, 85vw) !important;
        }
        
        .wheel-sparkles {
            width: min(350px, 90vw) !important;
            height: min(350px, 90vw) !important;
        }
        
        /* Chicken Board */
        #chickenBoard {
            height: 600px !important;
            min-height: 600px !important;
        }
        
        #chickenModal .game-modal-content {
            max-width: 100vw !important;
            max-height: 98vh !important;
            padding: 8px !important;
        }
        
        .chicken-street {
            min-width: 40px !important;
        }
        
        #chickenPlayer {
            font-size: 3rem !important;
        }
        
        /* Chicken Displays - Oben/Unten auf Mobile */
        #chickenMultiplier {
            top: 10px !important;
            right: 10px !important;
            left: auto !important;
            padding: 12px 16px !important;
        }
        
        #chickenStreets {
            top: auto !important;
            bottom: 10px !important;
            left: 10px !important;
            padding: 12px 16px !important;
        }
        
        #chickenMultiplierValue, #chickenStreetCount {
            font-size: 1.5rem !important;
        }
        
        /* Blackjack Cards */
        .bj-card {
            width: 60px !important;
            height: 85px !important;
            font-size: 0.75rem !important;
        }
        
        .bj-card-rank {
            font-size: 1.25rem !important;
        }
        
        .bj-card-suit {
            font-size: 1.75rem !important;
        }
        
        /* Modal Close Button */
        .modal-close {
            width: 36px !important;
            height: 36px !important;
            font-size: 1.25rem !important;
            top: 10px !important;
            right: 10px !important;
        }
    }
    
    @media (max-width: 480px) {
        .casino-grid {
            grid-template-columns: 1fr !important;
        }
        
        .stats {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        
        .quick-bet-btns, .quick-bet-grid {
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 6px !important;
        }
        
        .game-modal-content {
            max-height: 92vh !important;
            padding: 8px !important;
        }
        
        .slot-reel {
            width: 70px !important;
            height: 85px !important;
            font-size: 3rem !important;
        }
        
        .crash-graph {
            height: 350px !important;
        }
        
        .airplane {
            font-size: 2.5rem !important;
        }
        
        .wheel-container {
            width: min(250px, 90vw) !important;
            height: min(250px, 90vw) !important;
        }
        
        /* Chicken Game Mobile */
        #chickenBoard {
            height: 500px !important;
        }
        
        #chickenModal .game-modal-content {
            max-height: 95vh !important;
            padding: 6px !important;
        }
        
        .chicken-street {
            min-width: 30px !important;
        }
        
        #chickenPlayer {
            font-size: 2.5rem !important;
        }
        
        /* Stack displays vertically on small screens */
        #chickenMultiplier {
            top: 10px !important;
            right: 10px !important;
            left: auto !important;
            padding: 10px 14px !important;
        }
        
        #chickenStreets {
            top: auto !important;
            bottom: 10px !important;
            left: 10px !important;
            right: auto !important;
            padding: 10px 14px !important;
        }
        
        #chickenMultiplierValue, #chickenStreetCount {
            font-size: 1.25rem !important;
        }
        
        /* Chicken control buttons */
        #chickenStartBtn, #chickenCrossBtn, #chickenCashoutBtn {
            font-size: 1rem !important;
            padding: 12px !important;
        }
    }
    </style>

    <div class="container">
        
        <!-- KRASSES ANIMIERTES CASINO LOGO -->
        <div class="casino-logo-container">
            <div class="casino-logo-wrapper">
                <div class="logo-glow"></div>
                <div class="logo-text-main">
                    <span class="logo-letter" style="--i:0">C</span>
                    <span class="logo-letter" style="--i:1">A</span>
                    <span class="logo-letter" style="--i:2">S</span>
                    <span class="logo-letter" style="--i:3">I</span>
                    <span class="logo-letter" style="--i:4">N</span>
                    <span class="logo-letter" style="--i:5">O</span>
                </div>
                <div class="logo-coins">
                    <div class="coin coin-1">💰</div>
                    <div class="coin coin-2">🎰</div>
                    <div class="coin coin-3">💎</div>
                    <div class="coin coin-4">🎲</div>
                </div>
                <div class="logo-sparks">
                    <div class="spark"></div>
                    <div class="spark"></div>
                    <div class="spark"></div>
                    <div class="spark"></div>
                    <div class="spark"></div>
                    <div class="spark"></div>
                </div>
            </div>
            <div class="logo-subtitle">
                <span class="subtitle-text">PUSHING P</span>
                <span class="subtitle-separator">•</span>
                <span class="subtitle-tagline">BIG WINS AWAIT</span>
            </div>
        </div>
        
        <style>
            .casino-logo-container {
                text-align: center;
                margin: 40px 0 60px 0;
                position: relative;
            }
            
            .casino-logo-wrapper {
                position: relative;
                display: inline-block;
                padding: 40px 60px;
            }
            
            /* Animated Glow Background */
            .logo-glow {
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at 50% 50%, 
                    rgba(245, 158, 11, 0.3) 0%, 
                    rgba(236, 72, 153, 0.2) 50%, 
                    transparent 100%);
                border-radius: 50%;
                animation: logoGlowPulse 3s ease-in-out infinite;
                filter: blur(40px);
                z-index: 0;
            }
            
            @keyframes logoGlowPulse {
                0%, 100% { 
                    transform: scale(1);
                    opacity: 0.6;
                }
                50% { 
                    transform: scale(1.3);
                    opacity: 0.9;
                }
            }
            
            /* Main CASINO Text */
            .logo-text-main {
                position: relative;
                font-size: 6rem;
                font-weight: 900;
                letter-spacing: 0.1em;
                z-index: 2;
                display: flex;
                gap: 0.1em;
                text-shadow: 
                    0 0 10px rgba(255, 215, 0, 0.8),
                    0 0 20px rgba(255, 165, 0, 0.6),
                    0 0 30px rgba(255, 100, 0, 0.4),
                    0 10px 40px rgba(0, 0, 0, 0.5);
            }
            
            .logo-letter {
                display: inline-block;
                background: linear-gradient(
                    180deg, 
                    #FFD700 0%, 
                    #FFA500 30%, 
                    #FF8C00 60%, 
                    #FF6347 100%
                );
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                animation: 
                    logoLetterBounce 2s ease-in-out infinite,
                    logoLetterShine 3s linear infinite;
                animation-delay: calc(var(--i) * 0.1s);
                position: relative;
            }
            
            @keyframes logoLetterBounce {
                0%, 100% { transform: translateY(0) rotateZ(0deg); }
                25% { transform: translateY(-10px) rotateZ(-2deg); }
                75% { transform: translateY(-5px) rotateZ(2deg); }
            }
            
            @keyframes logoLetterShine {
                0% { filter: brightness(1) hue-rotate(0deg); }
                50% { filter: brightness(1.3) hue-rotate(10deg); }
                100% { filter: brightness(1) hue-rotate(0deg); }
            }
            
            /* Floating Coins */
            .logo-coins {
                position: absolute;
                inset: 0;
                z-index: 1;
            }
            
            .coin {
                position: absolute;
                font-size: 2.5rem;
                animation: coinFloat 4s ease-in-out infinite;
                filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.6));
            }
            
            .coin-1 {
                top: 10%;
                left: 5%;
                animation-delay: 0s;
            }
            
            .coin-2 {
                top: 15%;
                right: 5%;
                animation-delay: 1s;
            }
            
            .coin-3 {
                bottom: 15%;
                left: 10%;
                animation-delay: 2s;
            }
            
            .coin-4 {
                bottom: 10%;
                right: 10%;
                animation-delay: 1.5s;
            }
            
            @keyframes coinFloat {
                0%, 100% {
                    transform: translateY(0) rotate(0deg) scale(1);
                }
                25% {
                    transform: translateY(-20px) rotate(90deg) scale(1.1);
                }
                50% {
                    transform: translateY(-10px) rotate(180deg) scale(1);
                }
                75% {
                    transform: translateY(-25px) rotate(270deg) scale(1.15);
                }
            }
            
            /* Sparkling Stars */
            .logo-sparks {
                position: absolute;
                inset: 0;
                z-index: 3;
                pointer-events: none;
            }
            
            .spark {
                position: absolute;
                width: 4px;
                height: 4px;
                background: #FFD700;
                border-radius: 50%;
                box-shadow: 
                    0 0 10px #FFD700,
                    0 0 20px #FFA500,
                    0 0 30px #FF6347;
                animation: sparkTwinkle 2s ease-in-out infinite;
            }
            
            .spark:nth-child(1) {
                top: 20%;
                left: 20%;
                animation-delay: 0s;
            }
            
            .spark:nth-child(2) {
                top: 30%;
                right: 15%;
                animation-delay: 0.5s;
            }
            
            .spark:nth-child(3) {
                bottom: 25%;
                left: 15%;
                animation-delay: 1s;
            }
            
            .spark:nth-child(4) {
                bottom: 30%;
                right: 20%;
                animation-delay: 1.5s;
            }
            
            .spark:nth-child(5) {
                top: 50%;
                left: 5%;
                animation-delay: 0.7s;
            }
            
            .spark:nth-child(6) {
                top: 50%;
                right: 5%;
                animation-delay: 1.2s;
            }
            
            @keyframes sparkTwinkle {
                0%, 100% {
                    opacity: 0;
                    transform: scale(0);
                }
                50% {
                    opacity: 1;
                    transform: scale(1.5);
                }
            }
            
            /* Subtitle */
            .logo-subtitle {
                position: relative;
                z-index: 2;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
                margin-top: -20px;
                font-size: 1.2rem;
                font-weight: 700;
                letter-spacing: 0.3em;
                color: #fff;
                text-shadow: 
                    0 0 10px rgba(139, 92, 246, 0.8),
                    0 0 20px rgba(236, 72, 153, 0.6),
                    0 3px 10px rgba(0, 0, 0, 0.5);
                animation: subtitleGlow 2s ease-in-out infinite;
            }
            
            @keyframes subtitleGlow {
                0%, 100% {
                    text-shadow: 
                        0 0 10px rgba(139, 92, 246, 0.8),
                        0 0 20px rgba(236, 72, 153, 0.6),
                        0 3px 10px rgba(0, 0, 0, 0.5);
                }
                50% {
                    text-shadow: 
                        0 0 20px rgba(139, 92, 246, 1),
                        0 0 40px rgba(236, 72, 153, 1),
                        0 3px 15px rgba(0, 0, 0, 0.7);
                }
            }
            
            .subtitle-text {
                background: linear-gradient(135deg, #8b5cf6, #ec4899);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .subtitle-separator {
                color: #f59e0b;
                animation: separatorPulse 1.5s ease-in-out infinite;
            }
            
            @keyframes separatorPulse {
                0%, 100% { transform: scale(1); opacity: 0.7; }
                50% { transform: scale(1.3); opacity: 1; }
            }
            
            .subtitle-tagline {
                background: linear-gradient(135deg, #f59e0b, #ef4444);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .logo-text-main {
                    font-size: 3.5rem;
                }
                
                .coin {
                    font-size: 1.5rem;
                }
                
                .logo-subtitle {
                    font-size: 0.9rem;
                }
            }
        </style>
        
        <?php if ($casino_locked): ?>
            <div style="padding: 32px; background: linear-gradient(135deg, var(--error), #b91c1c); border-radius: 16px; text-align: center; margin: 32px 0;">
                <div style="font-size: 3rem; margin-bottom: 16px;">🔒</div>
                <h2 style="font-size: 1.5rem; margin-bottom: 12px;">Casino gesperrt</h2>
                <p style="font-size: 1.125rem; opacity: 0.95;">
                    Du brauchst mindestens <strong>10,00€</strong> Guthaben, um das Casino zu nutzen.<br>
                    Dein aktuelles Guthaben: <strong><?= number_format($balance, 2, ',', '.') ?>€</strong>
                </p>
            </div>
        <?php else: ?>

        <!-- Stats -->
        <div class="stats" style="grid-template-columns: repeat(4, 1fr);">
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <div class="stat-value"><?= number_format($casino_available_balance, 2, ',', '.') ?> €</div>
                <div class="stat-label">Verfügbar (10€ Reserve)</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🎮</span>
                <div class="stat-value"><?= $games_played ?></div>
                <div class="stat-label">Spiele gespielt</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">✨</span>
                <div class="stat-value"><?= number_format($total_won, 2, ',', '.') ?> €</div>
                <div class="stat-label">Gewonnen</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <div class="stat-value"><?= number_format($total_lost, 2, ',', '.') ?> €</div>
                <div class="stat-label">Verloren</div>
            </div>
        </div>

        <!-- Games Grid -->
        <div class="casino-grid">
            <!-- SLOTS -->
            <div class="game-card" onclick="window.location.href='/games/slots.php'" style="cursor:pointer;">
                <span class="game-icon">🎰</span>
                <div class="game-title">Slot Machine</div>
                <div class="game-desc">Drei gleiche Symbole = Gewinn! Jackpot bei 3x 💎</div>
                <div class="game-stats">
                    <div class="game-stat">
                        <div class="game-stat-label">House Edge</div>
                        <div class="game-stat-value">7%</div>
                    </div>
                    <div class="game-stat">
                        <div class="game-stat-label">Max Win</div>
                        <div class="game-stat-value">100x</div>
                    </div>
                </div>
            </div>

            <!-- PLINKO -->
            <div class="game-card" onclick="window.location.href='/games/plinko.php'" style="cursor:pointer;">
                <span class="game-icon">🎯</span>
                <div class="game-title">Plinko</div>
                <div class="game-desc">Ball fällt durch Pins! Bis zu 5x Multiplikator!</div>
                <div class="game-stats">
                    <div class="game-stat">
                        <div class="game-stat-label">House Edge</div>
                        <div class="game-stat-value">5%</div>
                    </div>
                    <div class="game-stat">
                        <div class="game-stat-label">Max Win</div>
                        <div class="game-stat-value">5x</div>
                    </div>
                </div>
            </div>

            <!-- CRASH -->
            <div class="game-card" onclick="window.location.href='/games/crash.php'" style="cursor:pointer;">
                <span class="game-icon">🚀</span>
                <div class="game-title">Crash</div>
                <div class="game-desc">Multiplier steigt! Cashout bevor es crasht!</div>
                <div class="game-stats">
                    <div class="game-stat">
                        <div class="game-stat-label">House Edge</div>
                        <div class="game-stat-value">2%</div>
                    </div>
                    <div class="game-stat">
                        <div class="game-stat-label">Max Win</div>
                        <div class="game-stat-value">∞</div>
                    </div>
                </div>
            </div>

            <!-- BLACKJACK -->
            <div class="game-card" onclick="window.location.href='/games/blackjack.php'" style="cursor:pointer;">
                <span class="game-icon">🃏</span>
                <div class="game-title">Blackjack</div>
                <div class="game-desc">Klassisches Kartenspiel! Schlag den Dealer!</div>
                <div class="game-stats">
                    <div class="game-stat">
                        <div class="game-stat-label">House Edge</div>
                        <div class="game-stat-value">1%</div>
                    </div>
                    <div class="game-stat">
                        <div class="game-stat-label">Blackjack</div>
                        <div class="game-stat-value">2.5x</div>
                    </div>
                </div>
            </div>

            <!-- CHICKEN -->
            <div class="game-card" onclick="window.location.href='/games/chicken.php'" style="cursor:pointer;">
                <span class="game-icon">🐔</span>
                <div class="game-title">Chicken</div>
                <div class="game-desc">Überquere die Straßen von links nach rechts! M = (1-h) / P(k)
                <div class="game-stats">
                    <div class="game-stat">
                        <div class="game-stat-label">House Edge</div>
                        <div class="game-stat-value">5%</div>
                    </div>
                    <div class="game-stat">
                        <div class="game-stat-label">Max Win</div>
                        <div class="game-stat-value">∞</div>
                    </div>
                </div>
            </div>

            <!-- MINES -->
            <div class="game-card" onclick="window.location.href='/games/mines.php'">
                <span class="game-icon">💎</span>
                <div class="game-title">Mines</div>
                <div class="game-desc">Finde Diamanten, vermeide Minen! Mathematisch faire Quoten!</div>
                <div class="game-stats">
                    <div class="game-stat">
                        <div class="game-stat-label">House Edge</div>
                        <div class="game-stat-value">4%</div>
                    </div>
                    <div class="game-stat">
                        <div class="game-stat-label">RTP</div>
                        <div class="game-stat-value">96%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Multiplayer Lobby Section - COMING SOON -->
        <div style="margin-top: 48px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(239, 68, 68, 0.05)); 
                    border: 2px solid var(--border); border-radius: 24px; padding: 32px; position: relative; overflow: hidden; opacity: 0.6;">
            
            <!-- COMING SOON Overlay -->
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg);
                        background: linear-gradient(135deg, #f59e0b, #ef4444); padding: 20px 60px; border-radius: 16px;
                        font-size: 2rem; font-weight: 900; color: white; text-shadow: 0 4px 8px rgba(0,0,0,0.5);
                        border: 4px solid white; box-shadow: 0 20px 60px rgba(0,0,0,0.5); z-index: 10;">
                🚧 COMING SOON 🚧
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; filter: blur(2px);">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="font-size: 3rem; animation: bounce 2s ease-in-out infinite;">🎲</div>
                    <div>
                        <h2 style="font-size: 1.75rem; font-weight: 900; margin: 0; background: linear-gradient(135deg, #f59e0b, #ef4444); 
                                    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            Multiplayer Lobby
                        </h2>
                        <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 0.875rem;">Spiele mit anderen Mitgliedern!</p>
                    </div>
                </div>
                
                <button onclick="showNotification('Multiplayer kommt bald!', 'info')" style="
                    padding: 12px 24px;
                    background: linear-gradient(135deg, #6B7280, #9CA3AF);
                    border: none;
                    border-radius: 12px;
                    color: white;
                    font-weight: 800;
                    font-size: 1rem;
                    cursor: not-allowed;
                    transition: all 0.2s;
                    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
                ">
                    ➕ Tisch erstellen (Bald verfügbar)
                </button>
            </div>
            
            <!-- Active Tables Grid -->
            <div id="multiplayerTablesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; min-height: 100px; filter: blur(2px);">
                <div style="text-align: center; padding: 40px; color: var(--text-secondary); grid-column: 1 / -1;">
                    Feature in Entwicklung...
                </div>
            </div>
        </div>

        <!-- Recent Big Wins -->
        <?php if (count($recent_wins) > 0): ?>
        <div style="margin-top: 48px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.05), rgba(16, 185, 129, 0.05)); 
                    border: 2px solid var(--border); border-radius: 24px; padding: 32px; position: relative; overflow-y: auto;">
            
            <!-- Decorative gradient -->
            <div style="position: absolute; top: -50%; right: -20%; width: 300px; height: 300px; 
                        background: radial-gradient(circle, rgba(139, 92, 246, 0.1), transparent); 
                        border-radius: 50%; pointer-events: none;"></div>
            
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 28px; position: relative;">
                <div style="font-size: 3rem; animation: bounce 2s ease-in-out infinite;">🏆</div>
                <div>
                    <h2 style="font-size: 1.75rem; font-weight: 900; margin: 0; background: linear-gradient(135deg, #8b5cf6, #10b981); 
                                -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        Letzte Gewinne
                    </h2>
                    <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 0.875rem;">Die größten Gewinner des Casinos</p>
                </div>
            </div>
            
            <style>
                @keyframes bounce {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-10px); }
                }
                
                .win-card {
                    background: var(--bg-secondary);
                    border: 2px solid var(--border);
                    border-radius: 16px;
                    padding: 20px 24px;
                    margin-bottom: 12px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    transition: all 0.3s ease;
                    position: relative;
                    overflow-y: auto;
                }
                
                .win-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.1), transparent);
                    transition: left 0.5s ease;
                }
                
                .win-card:hover {
                    transform: translateX(8px);
                    border-color: var(--accent);
                    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.2);
                }
                
                .win-card:hover::before {
                    left: 100%;
                }
                
                .win-user-info {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                }
                
                .win-avatar {
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, var(--accent), var(--success));
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.5rem;
                    font-weight: 900;
                    color: white;
                    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
                }
                
                .win-details {
                    flex: 1;
                }
                
                .win-name {
                    font-weight: 800;
                    font-size: 1.1rem;
                    color: var(--text-primary);
                    margin-bottom: 4px;
                }
                
                .win-meta {
                    display: flex;
                    gap: 12px;
                    align-items: center;
                    font-size: 0.85rem;
                    color: var(--text-secondary);
                }
                
                .win-game {
                    padding: 4px 12px;
                    background: rgba(139, 92, 246, 0.2);
                    border-radius: 8px;
                    font-weight: 700;
                    color: var(--accent);
                }
                
                .win-amount-box {
                    text-align: right;
                }
                
                .win-profit {
                    font-size: 1.5rem;
                    font-weight: 900;
                    background: linear-gradient(135deg, #10b981, #059669);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                
                .win-multiplier {
                    font-size: 0.85rem;
                    color: var(--text-secondary);
                    margin-top: 4px;
                }
            </style>
            
            <?php foreach (array_slice($recent_wins, 0, 5) as $index => $win): ?>
                <div class="win-card" style="animation: slideIn 0.5s ease <?= $index * 0.1 ?>s backwards;">
                    <div class="win-user-info">
                        <div class="win-avatar">
                            <?= strtoupper(substr($win['name'], 0, 1)) ?>
                        </div>
                        <div class="win-details">
                            <div class="win-name"><?= escape($win['name']) ?></div>
                            <div class="win-meta">
                                <span class="win-game"><?= ucfirst($win['game_type']) ?></span>
                                <span>⏱️ <?= date('d.m.Y H:i', strtotime($win['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="win-amount-box">
                        <div class="win-profit">
                            +<?= number_format($win['win_amount'] - $win['bet_amount'], 2, ',', '.') ?> €
                        </div>
                        <div class="win-multiplier">
                            <?= $win['bet_amount'] > 0 ? number_format(($win['win_amount'] / $win['bet_amount']), 2, ',', '.') . 'x' : '-' ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <style>
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateX(-30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }
            </style>
        </div>
        <?php endif; ?>
        
        <?php endif; // End casino_locked check ?>
    </div>

    <?php if (!$casino_locked): ?>
    <!-- SLOTS MODAL - VEGAS STYLE -->
    <div class="game-modal" id="slotsModal">
        <div class="game-modal-content" style="background: #000; max-width: 1100px; max-height: 80vh; overflow-y: auto; border: 4px solid #f59e0b; box-shadow: 0 0 100px rgba(245, 158, 11, 0.8), inset 0 0 200px rgba(255, 215, 0, 0.1); position: relative;">
            
            <!-- Vegas Lights Border Animation -->
            <div class="vegas-lights-top"></div>
            <div class="vegas-lights-bottom"></div>
            <div class="vegas-lights-left"></div>
            <div class="vegas-lights-right"></div>
            
            <button class="modal-close" onclick="closeGame('slots')" style="background: linear-gradient(135deg, #ef4444, #dc2626); border: 3px solid #fff; color: #fff; font-weight: 900; font-size: 2rem; width: 50px; height: 50px; border-radius: 50%; z-index: 1000;">×</button>
            
            <div style="padding: 20px; display: grid; grid-template-rows: auto auto 1fr auto; gap: 16px; height: 100%;">
                
                <!-- Header Compact -->
                <div style="text-align: center;">
                    <div style="font-size: 2.5rem; font-weight: 900; background: linear-gradient(90deg, #f59e0b, #ef4444, #ec4899, #8b5cf6, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: slotTitleShine 3s linear infinite; background-size: 200% 100%; letter-spacing: 4px;">
                        🎰 MEGA SLOTS 🎰
                    </div>
                </div>
                
                <!-- Balance & Bet Section -->
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <!-- Balance Display -->
                    <div style="background: linear-gradient(135deg, #0f172a, #1e1b4b); padding: 16px; border-radius: 16px; text-align: center; border: 3px solid #fbbf24; box-shadow: 0 0 40px rgba(251, 191, 36, 0.6), inset 0 0 30px rgba(251, 191, 36, 0.1);">
                        <div style="font-size: 0.85rem; color: #fbbf24; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">💰 Verfügbares Guthaben</div>
                        <div style="font-size: 2.5rem; font-weight: 900; color: #10b981; text-shadow: 0 0 30px rgba(16, 185, 129, 1), 0 0 60px rgba(16, 185, 129, 0.5); margin-top: 4px;" id="slotsBalance"><?= number_format(max(0, $balance - 10), 2, ',', '.') ?> €</div>
                    </div>
                    
                    
                    
                    <!-- Hidden input for bet value -->
                    <!-- Bet Input -->
                    <div style="margin: 20px 0;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 700; color: white; text-align: center;">💰 EINSATZ</label>
                        <input type="number" id="slotsBet" value="1.00" min="0.01" max="10.00" step="0.01" 
                               style="width: 100%; padding: 12px; background: rgba(0,0,0,0.3); border: 2px solid #a78bfa; 
                                      border-radius: 12px; color: white; font-size: 1.125rem; font-weight: 700; text-align: center;">
                        
                        <!-- Quick Bet Buttons -->
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 12px;">
                            <button class="quick-bet-btn" onclick="setSlotsQuickBet(0.50)" style="padding: 8px; background: rgba(139, 92, 246, 0.2); border: 2px solid #8b5cf6; border-radius: 8px; color: white; font-weight: 700; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;">0.50€</button>
                            <button class="quick-bet-btn" onclick="setSlotsQuickBet(1.00)" style="padding: 8px; background: rgba(16, 185, 129, 0.2); border: 2px solid #10b981; border-radius: 8px; color: white; font-weight: 700; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;">1€</button>
                            <button class="quick-bet-btn" onclick="setSlotsQuickBet(2.00)" style="padding: 8px; background: rgba(245, 158, 11, 0.2); border: 2px solid #f59e0b; border-radius: 8px; color: white; font-weight: 700; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;">2€</button>
                            <button class="quick-bet-btn" onclick="setSlotsQuickBet(5.00)" style="padding: 8px; background: rgba(59, 130, 246, 0.2); border: 2px solid #3b82f6; border-radius: 8px; color: white; font-weight: 700; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;">5€</button>
                            <button class="quick-bet-btn" onclick="setSlotsQuickBet(10.00)" style="padding: 8px; background: rgba(239, 68, 68, 0.2); border: 2px solid #ef4444; border-radius: 8px; color: white; font-weight: 700; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;">10€</button>
                        </div>
                        <div style="margin-top: 8px; font-size: 0.75rem; color: rgba(255,255,255,0.6); text-align: center;">
                            Min: 0.01€ • Max: 10.00€
                        </div>
                    </div>
                </div>

                <!-- Slot Machine Main Area - LAS VEGAS STYLE -->
                <div class="slots-machine">
                    <!-- Vegas Crown -->
                    <div class="slots-crown">⭐ JACKPOT ⭐</div>
                    
                    <!-- LED Lights Border -->
                    <div class="slots-lights"></div>
                    
                    <!-- Slot Reels Container -->
                    <div class="slots-reels" id="slotsReels">
                        <div class="slot-reel-frame">
                            <div class="slot-reel">
                                <div class="reel-inner" id="reel1">🍒</div>
                            </div>
                        </div>
                        <div class="slot-reel-frame">
                            <div class="slot-reel">
                                <div class="reel-inner" id="reel2">🍋</div>
                            </div>
                        </div>
                        <div class="slot-reel-frame">
                            <div class="slot-reel">
                                <div class="reel-inner" id="reel3">⭐</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Win/Loss Messages -->
                    <div id="slotsWin" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 3rem; font-weight: 900; text-align: center; pointer-events: none; z-index: 100;"></div>
                    <div id="slotsLoss" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 2rem; font-weight: 900; text-align: center; color: #ef4444; text-shadow: 0 0 20px rgba(239, 68, 68, 1), 0 0 40px rgba(239, 68, 68, 0.8); pointer-events: none; z-index: 100;"></div>
                </div>
                    
                    <!-- Spin Button -->
                    <button id="slotsSpin" onclick="spinSlots()" style="background: linear-gradient(135deg, #f59e0b 0%, #ef4444 50%, #f59e0b 100%); border: 5px solid #fbbf24; color: #fff; padding: 18px; border-radius: 20px; font-size: 1.8rem; font-weight: 900; cursor: pointer; text-transform: uppercase; letter-spacing: 5px; box-shadow: 0 10px 50px rgba(245, 158, 11, 1), inset 0 -5px 20px rgba(0,0,0,0.5); transition: all 0.3s; animation: slotSpinPulse 1.5s ease-in-out infinite; background-size: 200% 100%; position: relative; overflow-y: auto;">
                        <span style="position: relative; z-index: 2;">🎰 MEGA SPIN 🎰</span>
                        <div style="position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent); animation: shimmer 2s linear infinite;"></div>
                    </button>
                    
                    <!-- Payout Table Compact -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; background: rgba(0,0,0,0.6); padding: 14px; border-radius: 16px; border: 3px solid #8b5cf6; box-shadow: 0 0 30px rgba(139, 92, 246, 0.5);">
                        <div style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.05)); border-radius: 12px; border: 2px solid #10b981; box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);">
                            <div style="font-size: 2rem; margin-bottom: 4px;">🍒🍒🍒</div>
                            <div style="color: #10b981; font-weight: 900; font-size: 1.1rem; text-shadow: 0 0 10px rgba(16, 185, 129, 1);">10x</div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(139, 92, 246, 0.05)); border-radius: 12px; border: 2px solid #8b5cf6; box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);">
                            <div style="font-size: 2rem; margin-bottom: 4px;">7️⃣7️⃣7️⃣</div>
                            <div style="color: #a78bfa; font-weight: 900; font-size: 1.1rem; text-shadow: 0 0 10px rgba(167, 139, 250, 1);">50x</div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.3), rgba(236, 72, 153, 0.3)); border-radius: 12px; border: 3px solid #f59e0b; animation: slotJackpotGlow 1.5s ease-in-out infinite; box-shadow: 0 0 30px rgba(245, 158, 11, 0.6);">
                            <div style="font-size: 2rem; margin-bottom: 4px;">💎💎💎</div>
                            <div style="color: #fbbf24; font-weight: 900; font-size: 1.2rem; text-shadow: 0 0 15px rgba(251, 191, 36, 1);">100x 🎉</div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <style>
        /* Vegas Lights Border */
        .vegas-lights-top, .vegas-lights-bottom, .vegas-lights-left, .vegas-lights-right {
            position: absolute;
            background: linear-gradient(90deg, 
                #f59e0b 0%, #ef4444 10%, #ec4899 20%, #8b5cf6 30%, #3b82f6 40%,
                #10b981 50%, #f59e0b 60%, #ef4444 70%, #ec4899 80%, #8b5cf6 90%, #f59e0b 100%);
            background-size: 200% 100%;
            animation: vegasLights 2s linear infinite;
            z-index: 1;
        }
        .vegas-lights-top { top: 0; left: 0; right: 0; height: 8px; }
        .vegas-lights-bottom { bottom: 0; left: 0; right: 0; height: 8px; }
        .vegas-lights-left { left: 0; top: 0; bottom: 0; width: 8px; }
        .vegas-lights-right { right: 0; top: 0; bottom: 0; width: 8px; }
        
        @keyframes vegasLights {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }
        
        @keyframes slotTitleShine {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }
        
        @keyframes slotSpinPulse {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 10px 40px rgba(245, 158, 11, 0.8), inset 0 -4px 15px rgba(0,0,0,0.5);
            }
            50% { 
                transform: scale(1.03); 
                box-shadow: 0 15px 60px rgba(245, 158, 11, 1), inset 0 -4px 15px rgba(0,0,0,0.5), 0 0 80px rgba(245, 158, 11, 0.6);
            }
        }
        
        @keyframes slotJackpotGlow {
            0%, 100% { 
                box-shadow: 0 0 15px rgba(245, 158, 11, 0.5);
                transform: scale(1);
            }
            50% { 
                box-shadow: 0 0 40px rgba(245, 158, 11, 1), 0 0 60px rgba(236, 72, 153, 0.8);
                transform: scale(1.05);
            }
        }
        
        .slot-bet-btn {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            border: 3px solid #6366f1;
            color: #fff;
            padding: 12px 8px;
            border-radius: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            position: relative;
            overflow-y: auto;
        }
        
        .slot-bet-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .slot-bet-btn:hover::before {
            left: 100%;
        }
        
        .slot-bet-btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 1);
            border-color: #a78bfa;
        }
        
        .slot-bet-btn.slot-bet-active {
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            border: 3px solid #fbbf24;
            box-shadow: 0 0 30px rgba(245, 158, 11, 1), 0 8px 25px rgba(245, 158, 11, 0.6);
            transform: scale(1.1);
            animation: betActive 1s ease-in-out infinite;
        }
        
        @keyframes betActive {
            0%, 100% { 
                box-shadow: 0 0 30px rgba(245, 158, 11, 1), 0 8px 25px rgba(245, 158, 11, 0.6);
            }
            50% { 
                box-shadow: 0 0 50px rgba(245, 158, 11, 1), 0 12px 35px rgba(245, 158, 11, 0.8);
            }
        }
        
        @keyframes neonPulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        
        @keyframes starRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .slots-reels-mega {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        
        .slot-reel-mega {
            width: 150px;
            height: 170px;
            background: linear-gradient(180deg, #000 0%, #1a1a1a 50%, #000 100%);
            border: 5px solid #f59e0b;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 50px rgba(245, 158, 11, 0.4), 0 0 40px rgba(245, 158, 11, 0.6);
        }
        
        .slot-reel-mega::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            animation: slotReelShine 3s linear infinite;
        }
        
        @keyframes slotReelShine {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .reel-inner {
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 0 25px rgba(255, 215, 0, 1));
            transition: all 0.3s;
        }
        
        .reel-inner.spinning {
            animation: reelSpin 0.1s linear infinite;
            filter: blur(8px) drop-shadow(0 0 30px rgba(245, 158, 11, 1));
        }
        
        .reel-inner.stopping {
            animation: reelBounce 0.8s ease-out;
            filter: drop-shadow(0 0 35px rgba(255, 215, 0, 1));
        }
        
        .reel-inner.winning {
            animation: winningPulse 0.5s ease-in-out infinite, winningScale 2s ease-in-out;
            filter: drop-shadow(0 0 50px rgba(245, 158, 11, 1)) drop-shadow(0 0 80px rgba(236, 72, 153, 1));
        }
        
        @keyframes reelSpin {
            0% { transform: translateY(0); }
            100% { transform: translateY(-20px); }
        }
        
        @keyframes reelBounce {
            0% { transform: scale(1.3); }
            30% { transform: scale(0.9); }
            50% { transform: scale(1.1); }
            70% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }
        
        @keyframes winningPulse {
            0%, 100% { 
                transform: scale(1); 
            }
            50% { 
                transform: scale(1.2); 
            }
        }
        
        @keyframes winningScale {
            0% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(-5deg) scale(1.1); }
            50% { transform: rotate(5deg) scale(1.15); }
            75% { transform: rotate(-3deg) scale(1.1); }
            100% { transform: rotate(0deg) scale(1); }
        }
        
        #slotsSpin:hover {
            transform: scale(1.05);
            animation: none;
            background-position: 100% 0%;
        }
        
        #slotsSpin:active {
            transform: scale(0.98);
        }
    </style>

    <!-- PLINKO MODAL -->
    <div class="game-modal" id="plinkoModal">
        <div class="game-modal-content" style="max-width: 1400px; max-height: 80vh; overflow-y: auto; padding: 20px;">
            <button class="modal-close" onclick="closeGame('plinko')">×</button>
            
            <h2 style="font-size: 1.5rem; margin: 0 0 12px 0; text-align: center; background: linear-gradient(135deg, #f59e0b, #ec4899, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 900; filter: drop-shadow(0 0 30px rgba(245,158,11,0.6));">
                🎯 PLINKO 🎯
            </h2>
            
            <div style="display: grid; gap: 10px;">
                
                <!-- Balance & Settings -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                    <!-- Balance -->
                    <div style="background: linear-gradient(135deg, rgba(139,92,246,0.2), rgba(236,72,153,0.2)); padding: 10px; border-radius: 12px; border: 2px solid rgba(139,92,246,0.5); box-shadow: 0 8px 30px rgba(139,92,246,0.3);">
                        <div style="font-size: 0.7rem; color: #c4b5fd; font-weight: 700; margin-bottom: 3px; text-align: center; letter-spacing: 1px;">💎 GUTHABEN</div>
                        <div id="plinkoBalance" style="font-size: 1.2rem; font-weight: 900; text-align: center; color: #fff; text-shadow: 0 0 25px rgba(255,255,255,0.8);">
                            <?= number_format(max(0, $balance - 10), 2, ',', '.') ?> €
                        </div>
                    </div>
                    
                    <!-- Bet Selection -->
                    <div style="background: rgba(0,0,0,0.4); padding: 10px; border-radius: 12px; border: 2px solid rgba(245,158,11,0.3);">
                        <div style="font-size: 0.7rem; color: #fbbf24; font-weight: 700; margin-bottom: 5px; text-align: center; letter-spacing: 1px;">💰 EINSATZ PRO BALL</div>
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 5px;">
                            <button class="plinko-bet-btn" onclick="setPlinkoBet(1)">1€</button>
                            <button class="plinko-bet-btn plinko-bet-active" onclick="setPlinkoBet(5)">5€</button>
                            <button class="plinko-bet-btn" onclick="setPlinkoBet(10)">10€</button>
                            <button class="plinko-bet-btn" onclick="setPlinkoBet(25)">25€</button>
                            <button class="plinko-bet-btn" onclick="setPlinkoBet(50)">50€</button>
                        </div>
                        <div style="margin: 16px 0;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 700; color: var(--text-primary);">💰 EINSATZ PRO BALL</label>
                        <input type="number" id="plinkoBet" value="1.00" min="0.01" max="10.00" step="0.01" 
                               style="width: 100%; padding: 12px; background: var(--bg-secondary); border: 2px solid var(--border); 
                                      border-radius: 12px; color: var(--text-primary); font-size: 1.125rem; font-weight: 700; text-align: center;">
                        
                        <!-- Quick Bet Buttons -->
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 12px;">
                            <button class="quick-bet-btn" onclick="setPlinkoQuickBet(0.50)">0.50€</button>
                            <button class="quick-bet-btn" onclick="setPlinkoQuickBet(1.00)">1€</button>
                            <button class="quick-bet-btn" onclick="setPlinkoQuickBet(2.00)">2€</button>
                            <button class="quick-bet-btn" onclick="setPlinkoQuickBet(5.00)">5€</button>
                            <button class="quick-bet-btn" onclick="setPlinkoQuickBet(10.00)">10€</button>
                        </div>
                        <div style="margin-top: 8px; font-size: 0.75rem; color: var(--text-secondary); text-align: center;">
                            Min: 0.01€ • Max: 10.00€
                        </div>
                    </div>
                    </div>
                    
                    <!-- Multi-Ball Selection -->
                    <div style="background: rgba(0,0,0,0.4); padding: 10px; border-radius: 12px; border: 2px solid rgba(16,185,129,0.3);">
                        <div style="font-size: 0.7rem; color: #10b981; font-weight: 700; margin-bottom: 5px; text-align: center; letter-spacing: 1px;">🎯 ANZAHL BÄLLE</div>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px;">
                            <button class="plinko-balls-btn plinko-balls-active" onclick="setPlinkoBalls(1)">1</button>
                            <button class="plinko-balls-btn" onclick="setPlinkoBalls(5)">5</button>
                            <button class="plinko-balls-btn" onclick="setPlinkoBalls(10)">10</button>
                            <button class="plinko-balls-btn" onclick="setPlinkoBalls(25)">25</button>
                        </div>
                        <input type="number" id="plinkoBallCount" value="1" min="1" max="25" readonly style="display: none;">
                    </div>
                </div>
                
                <!-- Plinko Board -->
                <div style="position: relative;">
                    <div id="plinkoInstructions" style="display: none; position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(139,92,246,0.95); padding: 12px 24px; border-radius: 12px; border: 2px solid #a78bfa; z-index: 10; text-align: center; font-weight: 700; color: #fff; box-shadow: 0 8px 30px rgba(139,92,246,0.8); pointer-events: none;">
                        👆 Klicke oben auf das Spielfeld, um den Ball zu starten!
                    </div>
                    <canvas id="plinkoCanvas" width="1200" height="700" 
                            style="width: 100%; max-width: 1200px; height: auto; margin: 0 auto; display: block; border-radius: 16px; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); box-shadow: 0 0 60px rgba(139,92,246,0.4), inset 0 0 100px rgba(0,0,0,0.8); cursor: crosshair;"></canvas>
                </div>
                
                <!-- Result Display -->
                <div id="plinkoResult" style="min-height: 35px;"></div>
                
                <!-- Drop Button -->
                <button id="plinkoDropBtn" onclick="dropBalls()" 
                        style="width: 100%; padding: 14px; font-size: 1.2rem; font-weight: 900; letter-spacing: 2px; background: linear-gradient(135deg, #8b5cf6, #ec4899); border: 3px solid #a78bfa; border-radius: 12px; cursor: pointer; box-shadow: 0 8px 40px rgba(139,92,246,0.7); text-shadow: 0 3px 10px rgba(0,0,0,0.7); transition: all 0.3s; color: #fff;">
                    🎯 BÄLLE VORBEREITEN 🎯<br>
                    <span style="font-size: 0.8rem; font-weight: 600; opacity: 0.9;">Bei 10+ Bällen: Klicke wo du willst, mehrfach möglich!</span>
                </button>
                
            </div>
        </div>
    </div>
    
    <style>
        .plinko-bet-btn, .plinko-balls-btn {
            padding: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #374151, #1f2937);
            border: 2px solid #6b7280;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .plinko-bet-btn:hover, .plinko-balls-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 30px rgba(139,92,246,0.5);
            border-color: #8b5cf6;
        }
        
        .plinko-bet-btn.plinko-bet-active {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-color: #fbbf24;
            box-shadow: 0 0 30px rgba(245,158,11,0.8);
            transform: scale(1.05);
        }
        
        .plinko-balls-btn.plinko-balls-active {
            background: linear-gradient(135deg, #10b981, #059669);
            border-color: #34d399;
            box-shadow: 0 0 30px rgba(16,185,129,0.8);
            transform: scale(1.05);
        }
        
        #plinkoDropBtn:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 60px rgba(139,92,246,0.9);
        }
        
        #plinkoDropBtn:active {
            transform: scale(0.98);
        }
        
        #plinkoDropBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: scale(1);
        }
    </style>

    <!-- BLACKJACK MODAL -->
    <div class="game-modal" id="blackjackModal">
        <div class="game-modal-content" style="max-width: 900px; max-height: 80vh; overflow-y: auto; background: linear-gradient(135deg, #0f172a, #1e1b4b); border: 4px solid #10b981;">
            <button class="modal-close" onclick="closeGame('blackjack')">×</button>
            
            <div style="padding: 20px;"
                <!-- Header -->
                <div style="text-align: center; margin-bottom: 24px;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: #fbbf24; text-shadow: 0 0 20px #fbbf24;">
                        🃏 BLACKJACK 🃏
                    </div>
                    <div style="color: var(--text-secondary); margin-top: 8px;">Schlag den Dealer!</div>
                </div>
                
                <!-- Balance Display -->
                <div style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; text-align: center; margin-bottom: 20px; border: 2px solid #10b981;">
                    <div style="font-size: 0.875rem; color: #10b981; font-weight: 700;">Verfügbares Guthaben</div>
                    <div style="font-size: 2rem; font-weight: 900; color: #10b981;" id="blackjackBalance"><?= number_format(max(0, $balance - 10), 2, ',', '.') ?> €</div>
                </div>
                
                <!-- Dealer Hand -->
                <div style="margin-bottom: 32px;">
                    <div style="text-align: center; margin-bottom: 12px;">
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">🎩 Dealer</div>
                        <div style="font-size: 2rem; font-weight: 900; color: #8b5cf6;" id="blackjackDealerValue">?</div>
                    </div>
                    <div id="blackjackDealerCards" style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; min-height: 140px; align-items: center;">
                        <!-- Dealer cards rendered here -->
                    </div>
                </div>
                
                <!-- Player Hand -->
                <div style="margin-bottom: 24px;">
                    <div style="text-align: center; margin-bottom: 12px;">
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">👤 Du</div>
                        <div style="font-size: 2rem; font-weight: 900; color: #10b981;" id="blackjackPlayerValue">0</div>
                    </div>
                    <div id="blackjackPlayerCards" style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; min-height: 140px; align-items: center;">
                        <!-- Player cards rendered here -->
                    </div>
                </div>
                
                <!-- Result Display -->
                <div id="blackjackResult" style="text-align: center; min-height: 80px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                </div>
                
                <!-- Bet Selection -->
                <div style="margin-bottom: 20px;">
                    <div style="text-align: center; margin-bottom: 12px; font-weight: 700; color: var(--text-secondary);">Einsatz wählen:</div>
                    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;">
                        <button class="blackjack-bet-btn active" onclick="setBlackjackBet(1)">1€</button>
                        <button class="blackjack-bet-btn" onclick="setBlackjackBet(5)">5€</button>
                        <button class="blackjack-bet-btn" onclick="setBlackjackBet(10)">10€</button>
                        <button class="blackjack-bet-btn" onclick="setBlackjackBet(25)">25€</button>
                        <button class="blackjack-bet-btn" onclick="setBlackjackBet(50)">50€</button>
                    </div>
                    <!-- Bet Input -->
                    <div style="margin: 20px 0;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 700; color: var(--text-primary); text-align: center;">💰 EINSATZ</label>
                        <input type="number" id="blackjackBet" value="1.00" min="0.01" max="10.00" step="0.01" 
                               style="width: 100%; padding: 12px; background: var(--bg-secondary); border: 2px solid var(--border); 
                                      border-radius: 12px; color: var(--text-primary); font-size: 1.125rem; font-weight: 700; text-align: center;">
                        
                        <!-- Quick Bet Buttons -->
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 12px;">
                            <button class="quick-bet-btn" onclick="setBlackjackQuickBet(0.50)">0.50€</button>
                            <button class="quick-bet-btn" onclick="setBlackjackQuickBet(1.00)">1€</button>
                            <button class="quick-bet-btn" onclick="setBlackjackQuickBet(2.00)">2€</button>
                            <button class="quick-bet-btn" onclick="setBlackjackQuickBet(5.00)">5€</button>
                            <button class="quick-bet-btn" onclick="setBlackjackQuickBet(10.00)">10€</button>
                        </div>
                        <div style="margin-top: 8px; font-size: 0.75rem; color: var(--text-secondary); text-align: center;">
                            Min: 0.01€ • Max: 10.00€
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div id="blackjackStart" style="display: block;">
                    <button onclick="startBlackjack()" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 12px; color: white; font-weight: 800; font-size: 1.25rem; cursor: pointer; transition: all 0.2s;">
                        🎲 Spiel starten
                    </button>
                </div>
                
                <div id="blackjackActions" style="display: none; gap: 12px;">
                    <button onclick="blackjackHit()" style="flex: 1; padding: 14px; background: var(--accent); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer;">
                        👆 Hit
                    </button>
                    <button onclick="blackjackStand()" style="flex: 1; padding: 14px; background: #10b981; border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer;">
                        ✋ Stand
                    </button>
                    <button onclick="blackjackDouble()" style="flex: 1; padding: 14px; background: #f59e0b; border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer;">
                        ⬆️ Double
                    </button>
                </div>
                
                <!-- Rules -->
                <div style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 12px; font-size: 0.875rem; color: var(--text-secondary);">
                    <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">📋 Regeln:</div>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Ziel: Komme näher an 21 als der Dealer, ohne zu überschreiten</li>
                        <li>Bildkarten = 10, Ass = 1 oder 11, andere = Nennwert</li>
                        <li>Blackjack (21 mit 2 Karten) zahlt 2.5x</li>
                        <li>Dealer zieht bis 16, steht ab 17</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .bj-card {
            width: 100px;
            height: 140px;
            background: white;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: transform 0.2s;
            animation: cardDeal 0.4s ease-out;
        }
        
        .bj-card:hover {
            transform: translateY(-4px);
        }
        
        .bj-card-rank {
            font-size: 2rem;
            font-weight: 900;
            color: #000;
        }
        
        .bj-card-suit {
            font-size: 2.5rem;
            margin-top: 4px;
        }
        
        .bj-card-back {
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            color: white;
            font-size: 4rem;
        }
        
        .blackjack-bet-btn {
            padding: 12px;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .blackjack-bet-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
        }
        
        .blackjack-bet-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        
        @keyframes cardDeal {
            from {
                transform: translateY(-100px) rotate(-10deg);
                opacity: 0;
            }
            to {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
        }
    </style>

    <!-- CRASH MODAL -->
    <div class="game-modal" id="crashModal">
        <div class="game-modal-content" style="max-width: 1000px; padding: 20px;">
            <button class="modal-close" onclick="closeGame('crash')">×</button>
            <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                
                <!-- Header kompakt -->
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 1.75rem; margin: 0;">🚀 Crash</h2>
                        <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 0.875rem;">Cashout bevor es crasht!</p>
                    </div>
                    <div class="balance-display" style="margin: 0;">
                        <div class="balance-label" style="font-size: 0.75rem;">Guthaben</div>
                        <div class="balance-value" id="crashBalance" style="font-size: 1.5rem;"><?= number_format(max(0, $balance - 10), 2, ',', '.') ?> €</div>
                    </div>
                </div>

            <div class="crash-graph" id="crashGraph">
                <div class="crash-sky">
                    <!-- Stars background -->
                    <div class="stars"></div>
                    <div class="stars2"></div>
                    <div class="stars3"></div>
                    <div class="stars4"></div>
                    
                    <!-- Twinkling stars -->
                    <div class="twinkle-star twinkle-1">✨</div>
                    <div class="twinkle-star twinkle-2">⭐</div>
                    <div class="twinkle-star twinkle-3">✨</div>
                    <div class="twinkle-star twinkle-4">⭐</div>
                    <div class="twinkle-star twinkle-5">✨</div>
                    
                    <!-- Comets -->
                    <div class="comet comet-1"></div>
                    <div class="comet comet-2"></div>
                    
                    <!-- Pulsing stars -->
                    <div class="pulse-star pulse-1">💫</div>
                    <div class="pulse-star pulse-2">🌟</div>
                    
                    <!-- Distant planets -->
                    <div class="distant-planet planet-small-1"></div>
                    <div class="distant-planet planet-small-2"></div>
                    
                    <!-- Sun (visible at start, fades in space) -->
                    <div class="celestial-body"></div>
                    
                    <!-- Ground (moves down as rocket goes up) -->
                    <div class="ground" id="ground"></div>
                    
                    <!-- Space Elements -->
                    <!-- Planets -->
                    <div class="planet planet-mars">🔴</div>
                    <div class="planet planet-jupiter">🪐</div>
                    <div class="planet planet-saturn">🪐</div>
                    
                    <!-- Galaxies -->
                    <div class="galaxy galaxy-1"></div>
                    <div class="galaxy galaxy-2"></div>
                    
                    <!-- Nebulas -->
                    <div class="nebula nebula-1"></div>
                    <div class="nebula nebula-2"></div>
                    
                    <!-- Milky Way -->
                    <div class="milky-way" id="milkyWay"></div>
                    
                    <!-- Shooting stars -->
                    <div class="shooting-star shooting-star-1"></div>
                    <div class="shooting-star shooting-star-2"></div>
                    
                    <!-- Asteroids -->
                    <div class="asteroid" id="asteroid1" style="top: 20%; left: 80%;">☄️</div>
                    <div class="asteroid" id="asteroid2" style="top: 70%; left: 10%;">🌑</div>
                    
                    <!-- Altitude meter -->
                    <div class="altitude-meter" id="altitudeMeter">
                        <div class="altitude-label">ALTITUDE</div>
                        <div class="altitude-value" id="altitudeValue">0km</div>
                    </div>
                    
                    <!-- Speed meter -->
                    <div class="speed-meter" id="speedMeter">
                        <div class="speed-label">MULTIPLIER</div>
                        <div class="speed-value" id="speedValue">0.00x</div>
                    </div>
                    
                    <!-- ROCKET -->
                    <div class="airplane" id="crashAirplane">
                        🚀
                    </div>
                    
                    <!-- Explosion (hidden initially) -->
                    <div class="explosion" id="crashExplosion" style="display: none;">
                        💥
                        <div class="shockwave"></div>
                    </div>
                    
                    <!-- Easter Eggs - REDUCED & SPACED OUT -->
                    <div class="easter-egg" id="egg1" data-trigger="3.0" style="display: none; left: 25%; font-size: 2.5rem;">
                        🌍
                    </div>
                    <div class="easter-egg" id="egg2" data-trigger="6.0" style="display: none; right: 25%; font-size: 3rem;">
                        🛰️
                    </div>
                    <div class="easter-egg" id="egg3" data-trigger="10.0" style="display: none; left: 35%; font-size: 3.5rem;">
                        🌙
                    </div>
                    <div class="easter-egg" id="egg4" data-trigger="15.0" style="display: none; right: 30%; font-size: 4rem;">
                        ⭐
                    </div>
                    <div class="easter-egg" id="egg5" data-trigger="25.0" style="display: none; left: 50%; transform: translateX(-50%); font-size: 5rem;">
                        🪐
                    </div>
                </div>
                <div class="crash-multiplier" id="crashMultiplier">0.00x</div>
                
            </div>


            <!-- Control Panel - Kompakt -->
            <div style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(16, 185, 129, 0.1)); 
                        border: 2px solid var(--border); 
                        border-radius: 16px; 
                        padding: 16px;">
                
                <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                    <div class="quick-bet-btns" style="display: flex; gap: 8px; flex-wrap: wrap; flex: 1;">
                        <button class="quick-bet-btn" onclick="setCrashBet(0.50)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #8b5cf6; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);">
                            💰 0.50€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(1.00)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #10b981; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);">
                            💵 1€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(2.00)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #f59e0b; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);">
                            💸 2€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(5.00)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #3b82f6; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);">
                            💎 5€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(10.00)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #ef4444; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);">
                            👑 10€
                        </button>
                    </div>
                </div>

                <div class="bet-input-group" style="display: flex; gap: 12px; align-items: stretch;">
                    <div style="flex: 1; position: relative;">
                        <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 1rem; color: var(--accent); font-weight: 900;">💵</div>
                        <input type="number" class="bet-input" id="crashBet" value="1.00" min="0.01" max="10.00" step="0.01" 
                               style="padding-left: 38px; height: 50px; font-size: 1.25rem; font-weight: 900; text-align: center; border: 3px solid var(--accent); background: var(--bg-secondary); border-radius: 12px;">
                    </div>
                    <button class="bet-btn" id="crashStartBtn" onclick="startCrash()" 
                            style="flex: 1; height: 50px; font-size: 1.1rem; font-weight: 900; background: linear-gradient(135deg, #10b981, #059669); border: 3px solid #10b981; border-radius: 12px; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4); transition: all 0.3s;">
                        🚀 START
                    </button>
                    <button class="bet-btn" id="crashCashoutBtn" onclick="cashoutCrash()" 
                            style="display: none; flex: 1; height: 50px; font-size: 1.1rem; font-weight: 900; background: linear-gradient(135deg, #f59e0b, #d97706); border: 3px solid #f59e0b; border-radius: 12px; box-shadow: 0 4px 20px rgba(245, 158, 11, 0.4); transition: all 0.3s; animation: cashoutPulse 1s ease-in-out infinite;">
                        💰 CASHOUT
                    </button>
                </div>

                <style>
                    @keyframes cashoutPulse {
                        0%, 100% {
                            transform: scale(1);
                            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.4);
                        }
                        50% {
                            transform: scale(1.05);
                            box-shadow: 0 8px 30px rgba(245, 158, 11, 0.8);
                        }
                    }
                    .quick-bet-btn:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.5);
                        border-color: var(--success);
                    }
                    #crashStartBtn:hover {
                        transform: scale(1.05);
                        box-shadow: 0 8px 30px rgba(16, 185, 129, 0.6);
                    }
                    #crashCashoutBtn:hover {
                        transform: scale(1.08);
                        box-shadow: 0 10px 40px rgba(245, 158, 11, 1);
                    }
                </style>
            </div>

            <div class="win-message" id="crashWin" style="margin-top: 12px;"></div>
            <div class="loss-message" id="crashLoss" style="margin-top: 12px;"></div>
            
            </div><!-- Close grid -->
        </div>
    </div>

    <!-- CHICKEN MODAL -->
    <div class="game-modal" id="chickenModal">
        <div class="game-modal-content" style="max-width: 1000px; max-height: 95vh; overflow-y: auto; padding: 16px;">
            <button class="modal-close" onclick="closeGame('chicken')">×</button>
            
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 1.5rem; margin: 0;">🐔 Chicken</h2>
                    <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 0.875rem;">Überquere die Straße ohne erwischt zu werden!</p>
                </div>
                <div class="balance-display" style="margin: 0; padding: 12px 16px;">
                    <div class="balance-label" style="font-size: 0.75rem;">Guthaben</div>
                    <div class="balance-value" id="chickenBalance" style="font-size: 1.25rem;"><?= number_format(max(0, $balance - 10), 2, ',', '.') ?> €</div>
                </div>
            </div>

            <!-- Game Board - 10 VERTICAL STREETS - KRASSER STYLE! -->
            <div id="chickenBoard" style="background: linear-gradient(180deg, #1a1a2e 0%, #0f0f1e 100%); 
                                          border: 4px solid #f59e0b; 
                                          border-radius: 20px; 
                                          padding: 20px; 
                                          height: clamp(500px, 55vh, 700px);
                                          position: relative;
                                          overflow: hidden;
                                          box-shadow: 0 20px 60px rgba(245, 158, 11, 0.6), 
                                                      inset 0 0 100px rgba(245, 158, 11, 0.1),
                                                      0 0 100px rgba(236, 72, 153, 0.3);">
                
                <!-- Animated Background Pattern -->
                <div style="position: absolute; inset: 0; opacity: 0.1; background-image: repeating-linear-gradient(0deg, transparent, transparent 50px, rgba(255,255,255,0.05) 50px, rgba(255,255,255,0.05) 100px); pointer-events: none; z-index: 0;"></div>
                
                <!-- Start Zone (Left Side) - NEON STYLE -->
                <div style="position: absolute; 
                            left: 0; 
                            top: 0; 
                            width: 80px; 
                            height: 100%; 
                            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                            border-right: 4px solid #34d399;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-direction: column;
                            z-index: 10;
                            box-shadow: inset -5px 0 20px rgba(0,0,0,0.5),
                                        0 0 40px rgba(16, 185, 129, 0.6);
                            animation: startZonePulse 2s ease-in-out infinite;">
                    <div style="font-size: 3rem; margin-bottom: 10px; filter: drop-shadow(0 0 20px rgba(16, 185, 129, 1));">🏁</div>
                    <div style="font-size: 0.9rem; font-weight: 900; color: white; text-shadow: 0 0 10px rgba(255,255,255,0.8), 0 2px 4px rgba(0,0,0,0.8); writing-mode: vertical-rl; transform: rotate(180deg); letter-spacing: 3px;">START</div>
                </div>

                <!-- 10 Vertical Streets Container -->
                <div id="chickenRoads" style="position: absolute; 
                                              left: 80px; 
                                              top: 0; 
                                              right: 80px;
                                              height: 100%; 
                                              display: flex;
                                              gap: 8px;">
                    <!-- 10 streets will be created here -->
                </div>

                <!-- Goal Zone (Right Side) - GOLD NEON -->
                <div style="position: absolute; 
                            right: 0; 
                            top: 0; 
                            width: 80px; 
                            height: 100%; 
                            background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #f59e0b 100%);
                            border-left: 4px solid #fbbf24;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-direction: column;
                            z-index: 10;
                            box-shadow: inset 5px 0 20px rgba(0,0,0,0.3),
                                        0 0 60px rgba(245, 158, 11, 0.9),
                                        0 0 100px rgba(251, 191, 36, 0.6);
                            animation: goalZonePulse 1.5s ease-in-out infinite;">
                    <div style="font-size: 3.5rem; margin-bottom: 10px; filter: drop-shadow(0 0 30px rgba(251, 191, 36, 1)); animation: trophyFloat 2s ease-in-out infinite;">🏆</div>
                    <div style="font-size: 0.9rem; font-weight: 900; color: #fff; text-shadow: 0 0 15px rgba(251, 191, 36, 1), 0 2px 6px rgba(0,0,0,0.9); writing-mode: vertical-rl; transform: rotate(180deg); letter-spacing: 3px;">ZIEL</div>
                </div>
                
                <!-- Chicken (starts left, moves right across streets) - MEGA GLOW -->
                <div id="chickenPlayer" style="position: absolute; 
                                               left: 40px; 
                                               top: 50%; 
                                               transform: translate(0, -50%); 
                                               font-size: 5rem; 
                                               transition: all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
                                               filter: drop-shadow(0 0 30px rgba(245, 158, 11, 1)) 
                                                       drop-shadow(0 8px 20px rgba(0,0,0,0.9))
                                                       drop-shadow(0 0 50px rgba(236, 72, 153, 0.6));
                                               z-index: 100;
                                               animation: chickenIdle 1s ease-in-out infinite;">
                    🐔
                </div>

                <!-- Multiplier Display - NEON GREEN -->
                <div id="chickenMultiplier" style="position: absolute; 
                                                    top: 20px; 
                                                    right: 100px; 
                                                    background: linear-gradient(135deg, rgba(16, 185, 129, 0.98), rgba(5, 150, 105, 0.98));
                                                    padding: 18px 32px; 
                                                    border-radius: 20px; 
                                                    border: 4px solid #10b981;
                                                    box-shadow: 0 8px 40px rgba(16, 185, 129, 0.8),
                                                                inset 0 2px 12px rgba(255,255,255,0.3),
                                                                0 0 60px rgba(16, 185, 129, 0.5);
                                                    z-index: 200;
                                                    animation: multiplierPulse 1.5s ease-in-out infinite;">
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,1); margin-bottom: 6px; font-weight: 900; text-align: center; letter-spacing: 2px; text-shadow: 0 2px 6px rgba(0,0,0,0.8);">💰 GEWINN</div>
                    <div id="chickenMultiplierValue" style="font-size: 2.5rem; font-weight: 900; color: white; text-shadow: 0 0 20px rgba(255,255,255,1), 0 4px 12px rgba(0,0,0,0.8); text-align: center; font-family: 'Arial Black', sans-serif;">1.00x</div>
                    <div id="chickenCurrentWin" style="font-size: 1rem; color: #fbbf24; font-weight: 800; text-align: center; margin-top: 6px; text-shadow: 0 0 10px rgba(251, 191, 36, 0.8);">0.00€</div>
                </div>

                <!-- Street Counter - NEON PURPLE -->
                <div id="chickenStreets" style="position: absolute; 
                                                top: 20px; 
                                                left: 100px; 
                                                background: linear-gradient(135deg, rgba(139, 92, 246, 0.98), rgba(124, 58, 237, 0.98));
                                                padding: 18px 32px; 
                                                border-radius: 20px; 
                                                border: 4px solid #8b5cf6;
                                                box-shadow: 0 8px 40px rgba(139, 92, 246, 0.8),
                                                            inset 0 2px 12px rgba(255,255,255,0.3),
                                                            0 0 60px rgba(139, 92, 246, 0.5);
                                                z-index: 200;
                                                animation: streetPulse 1.5s ease-in-out infinite;">
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,1); margin-bottom: 6px; font-weight: 900; text-align: center; letter-spacing: 2px; text-shadow: 0 2px 6px rgba(0,0,0,0.8);">🛣️ STRASSEN</div>
                    <div id="chickenStreetCount" style="font-size: 2.5rem; font-weight: 900; color: white; text-shadow: 0 0 20px rgba(255,255,255,1), 0 4px 12px rgba(0,0,0,0.8); text-align: center; font-family: 'Arial Black', sans-serif;">0 / 10</div>
                </div>
                
                <style>
                    /* KRASSE CHICKEN GAME ANIMATIONEN */
                    @keyframes chickenIdle {
                        0%, 100% { 
                            transform: translate(0, -50%) scale(1) rotate(0deg); 
                        }
                        50% { 
                            transform: translate(0, -52%) scale(1.05) rotate(2deg); 
                        }
                    }
                    
                    @keyframes startZonePulse {
                        0%, 100% { 
                            box-shadow: inset -5px 0 20px rgba(0,0,0,0.5),
                                        0 0 40px rgba(16, 185, 129, 0.6);
                        }
                        50% { 
                            box-shadow: inset -5px 0 20px rgba(0,0,0,0.5),
                                        0 0 60px rgba(16, 185, 129, 1);
                        }
                    }
                    
                    @keyframes goalZonePulse {
                        0%, 100% { 
                            box-shadow: inset 5px 0 20px rgba(0,0,0,0.3),
                                        0 0 60px rgba(245, 158, 11, 0.9),
                                        0 0 100px rgba(251, 191, 36, 0.6);
                        }
                        50% { 
                            box-shadow: inset 5px 0 20px rgba(0,0,0,0.3),
                                        0 0 80px rgba(245, 158, 11, 1),
                                        0 0 140px rgba(251, 191, 36, 1);
                        }
                    }
                    
                    @keyframes trophyFloat {
                        0%, 100% { 
                            transform: translateY(0) scale(1);
                            filter: drop-shadow(0 0 30px rgba(251, 191, 36, 1));
                        }
                        50% { 
                            transform: translateY(-10px) scale(1.1);
                            filter: drop-shadow(0 0 50px rgba(251, 191, 36, 1));
                        }
                    }
                    
                    @keyframes multiplierPulse {
                        0%, 100% { 
                            transform: scale(1);
                            box-shadow: 0 8px 40px rgba(16, 185, 129, 0.8),
                                        inset 0 2px 12px rgba(255,255,255,0.3),
                                        0 0 60px rgba(16, 185, 129, 0.5);
                        }
                        50% { 
                            transform: scale(1.02);
                            box-shadow: 0 12px 50px rgba(16, 185, 129, 1),
                                        inset 0 2px 12px rgba(255,255,255,0.5),
                                        0 0 80px rgba(16, 185, 129, 0.8);
                        }
                    }
                    
                    @keyframes streetPulse {
                        0%, 100% { 
                            transform: scale(1);
                            box-shadow: 0 8px 40px rgba(139, 92, 246, 0.8),
                                        inset 0 2px 12px rgba(255,255,255,0.3),
                                        0 0 60px rgba(139, 92, 246, 0.5);
                        }
                        50% { 
                            transform: scale(1.02);
                            box-shadow: 0 12px 50px rgba(139, 92, 246, 1),
                                        inset 0 2px 12px rgba(255,255,255,0.5),
                                        0 0 80px rgba(139, 92, 246, 0.8);
                        }
                    }
                    
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); opacity: 1; }
                        50% { transform: scale(1.1); opacity: 0.8; }
                    }
                    @keyframes bounce {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-10px); }
                    }
                    @keyframes goalGlow {
                        0%, 100% { box-shadow: inset 5px 0 15px rgba(0,0,0,0.2), 0 0 30px rgba(255, 215, 0, 0.5); }
                        50% { box-shadow: inset 5px 0 15px rgba(0,0,0,0.2), 0 0 50px rgba(255, 215, 0, 0.8); }
                    }
                </style>
            </div>

            <!-- Controls -->
            <div style="margin-top: 16px; background: var(--bg-secondary); padding: 16px; border-radius: 16px; border: 2px solid var(--border);">
                <!-- Bet Amount -->
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 700; color: var(--text-secondary); font-size: 0.875rem;">Einsatz</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); gap: 8px; margin-bottom: 12px;">
                        <button class="quick-bet-btn" onclick="setChickenBet(0.50)" style="padding: 10px 6px; font-size: 0.875rem;">💰 0.50€</button>
                        <button class="quick-bet-btn" onclick="setChickenBet(1.00)" style="padding: 10px 6px; font-size: 0.875rem;">💵 1€</button>
                        <button class="quick-bet-btn" onclick="setChickenBet(2.00)" style="padding: 10px 6px; font-size: 0.875rem;">💸 2€</button>
                        <button class="quick-bet-btn" onclick="setChickenBet(5.00)" style="padding: 10px 6px; font-size: 0.875rem;">💎 5€</button>
                        <button class="quick-bet-btn" onclick="setChickenBet(10.00)" style="padding: 10px 6px; font-size: 0.875rem;">👑 10€</button>
                    </div>
                    <input type="number" id="chickenBet" value="1.00" min="0.01" max="10.00" step="0.01" 
                           style="width: 100%; padding: 12px; font-size: 1.125rem; font-weight: 700; 
                                  background: var(--bg-primary); border: 2px solid var(--border); 
                                  border-radius: 12px; color: var(--text-primary); text-align: center;">
                </div>

                <!-- Action Buttons -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                    <button id="chickenStartBtn" onclick="startChicken()" 
                            style="padding: 14px; font-size: 1.125rem; font-weight: 900; 
                                   background: linear-gradient(135deg, #10b981, #059669); 
                                   color: white; border: none; border-radius: 12px; cursor: pointer;
                                   box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4); transition: all 0.3s; min-height: 52px;">
                        🎮 START
                    </button>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button id="chickenCrossBtn" onclick="crossStreet()" disabled
                                style="padding: 14px; font-size: 1.125rem; font-weight: 900; 
                                       background: linear-gradient(135deg, #f59e0b, #d97706); 
                                       color: white; border: none; border-radius: 12px; cursor: pointer;
                                       box-shadow: 0 4px 20px rgba(245, 158, 11, 0.4); transition: all 0.3s;
                                       opacity: 0.5; min-height: 52px;">
                            🚶 ÜBER­QUEREN
                        </button>
                        <button id="chickenCashoutBtn" onclick="cashoutChicken()" disabled
                                style="padding: 14px; font-size: 1.125rem; font-weight: 900; 
                                       background: linear-gradient(135deg, #8b5cf6, #7c3aed); 
                                       color: white; border: none; border-radius: 12px; cursor: pointer;
                                       box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4); transition: all 0.3s;
                                       opacity: 0.5; min-height: 52px;">
                            💰 CASHOUT
                        </button>
                    </div>
                </div>

                <!-- Game Info -->
                <div style="margin-top: 16px; padding: 16px; background: var(--bg-tertiary); border-radius: 12px; border-left: 4px solid var(--accent);">
                    <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                        <strong>🎮 Spielprinzip:</strong> 10 Straßen nebeneinander - dunkel bis betreten!<br>
                        <strong>🚧 Baustelle (80%):</strong> Straße leer = SAFE! Grün wird angezeigt.<br>
                        <strong>🚗 Verkehr (20%):</strong> Autos = ÜBERFAHREN! Rot + Game Over.<br>
                        <strong>📊 Multiplier:</strong> M = (1 - 0.05) / 0.8^k - Exponentiell!<br>
                        <strong>🏆 Ziel:</strong> Alle 10 Straßen überqueren = Mega-Win!
                    </div>
                </div>
            </div>

            <div id="chickenWin" style="margin-top: 12px;"></div>
            <div id="chickenLoss" style="margin-top: 12px;"></div>
        </div>
    </div>

    <!-- MINES MODAL -->
    <div class="game-modal" id="minesModal">
        <div class="game-modal-content" style="max-width: 800px;">
            <button class="modal-close" onclick="closeGame('mines')">×</button>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h2 style="font-size: 2rem; margin: 0;">💎 Mines</h2>
                    <p style="color: var(--text-secondary); margin: 8px 0 0 0;">Finde Diamanten, vermeide Minen! Mathematisch fair.</p>
                </div>
                <div class="balance-display" style="margin: 0;">
                    <div class="balance-label">Guthaben</div>
                    <div class="balance-value" id="minesBalance"><?= number_format(max(0, $balance - 10), 2, ',', '.') ?> €</div>
                </div>
            </div>

            <!-- Mines Configuration -->
            <div id="minesConfig" style="background: var(--bg-secondary); padding: 24px; border-radius: 16px; margin-bottom: 24px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">💰 Einsatz</label>
                        <input type="number" id="minesBet" min="0.10" max="1000" step="0.10" value="1.00" 
                               style="width: 100%; padding: 12px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.125rem; font-weight: 700;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">💣 Minen (1-24)</label>
                        <input type="number" id="minesCount" min="1" max="24" step="1" value="3" 
                               style="width: 100%; padding: 12px; background: var(--bg-tertiary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.125rem; font-weight: 700;">
                    </div>
                </div>
                
                <div class="quick-bet-btns" style="margin-bottom: 16px;">
                    <button class="quick-bet-btn" onclick="setMinesBet(0.50)">0.50€</button>
                    <button class="quick-bet-btn active" onclick="setMinesBet(1.00)">1.00€</button>
                    <button class="quick-bet-btn" onclick="setMinesBet(5.00)">5.00€</button>
                    <button class="quick-bet-btn" onclick="setMinesBet(10.00)">10.00€</button>
                    <button class="quick-bet-btn" onclick="setMinesBet(25.00)">25.00€</button>
                </div>

                <button onclick="startMines()" id="minesStartBtn"
                        style="width: 100%; padding: 16px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 800; font-size: 1.125rem; cursor: pointer; transition: all 0.3s;">
                    🎮 Spiel starten
                </button>
            </div>

            <!-- Game Stats -->
            <div id="minesStats" style="display: none; background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(16, 185, 129, 0.1)); padding: 20px; border-radius: 16px; margin-bottom: 24px; border: 2px solid var(--border);">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; text-align: center;">
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">💎 Aufgedeckt</div>
                        <div id="minesRevealed" style="font-size: 1.75rem; font-weight: 900; color: var(--success);">0</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">📈 Multiplikator</div>
                        <div id="minesMultiplier" style="font-size: 1.75rem; font-weight: 900; color: var(--accent);">1.00x</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">💰 Potenzial</div>
                        <div id="minesPotential" style="font-size: 1.75rem; font-weight: 900; color: var(--success);">0.00€</div>
                    </div>
                </div>
            </div>

            <!-- Mines Grid (5x5 = 25 fields) -->
            <div id="minesGrid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 24px; max-width: 600px; margin-left: auto; margin-right: auto;">
                <!-- 25 tiles will be generated here -->
            </div>

            <!-- Cashout Button -->
            <button onclick="cashoutMines()" id="minesCashoutBtn" 
                    style="display: none; width: 100%; padding: 20px; background: linear-gradient(135deg, var(--success), #059669); border: none; border-radius: 16px; color: white; font-weight: 900; font-size: 1.5rem; cursor: pointer; box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4); transition: all 0.3s;">
                💰 Auszahlen: <span id="cashoutAmount">0.00€</span>
            </button>

            <!-- Game Info -->
            <div style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 12px; font-size: 0.875rem; color: var(--text-secondary);">
                <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">📊 Spielmechanik:</div>
                <div style="line-height: 1.6;">
                    • <strong>Mathematisch Fair:</strong> Jedes Feld hat berechenbare Wahrscheinlichkeiten<br>
                    • <strong>1. Klick bei 3 Minen:</strong> 88% sicher (22/25), 12% Mine (3/25)<br>
                    • <strong>Multiplikator:</strong> Steigt mit jedem sicheren Feld dynamisch<br>
                    • <strong>RTP 96%:</strong> Faire Quoten mit 4% House Edge<br>
                    • <strong>Strategie:</strong> Je mehr Minen, desto höher die Multiplikatoren!
                </div>
            </div>

            <div id="minesResult" style="margin-top: 16px;"></div>
        </div>
    </div>

    <script>
    let userBalance = parseFloat(<?= $casino_available_balance ?>) || 0; // Already minus 10€ reserve
    const RESERVE_AMOUNT = 10.00; // 10€ Reserve
    
    // Notification function
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'error' ? '#ef4444' : type === 'success' ? '#10b981' : '#8b5cf6'};
            color: white;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            z-index: 99999;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // Add animation styles
    const notifStyles = document.createElement('style');
    notifStyles.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    `;
    document.head.appendChild(notifStyles);
    
    // Update all balance displays
    function updateAllBalances(balance) {
        // Balance from get_balance.php is already minus 10€ reserve
        userBalance = Math.max(0, parseFloat(balance) || 0);
        const formattedBalance = userBalance.toFixed(2).replace('.', ',') + ' €';
        
        // Update all balance displays
        const crashBalanceEl = document.getElementById('crashBalance');
        const slotsBalanceEl = document.getElementById('slotsBalance');
        const plinkoBalanceEl = document.getElementById('plinkoBalance');
        const chickenBalanceEl = document.getElementById('chickenBalance');
        const minesBalanceEl = document.getElementById('minesBalance');
        
        if (crashBalanceEl) crashBalanceEl.textContent = formattedBalance;
        if (slotsBalanceEl) slotsBalanceEl.textContent = formattedBalance;
        if (plinkoBalanceEl) plinkoBalanceEl.textContent = formattedBalance;
        if (chickenBalanceEl) chickenBalanceEl.textContent = formattedBalance;
        if (minesBalanceEl) minesBalanceEl.textContent = formattedBalance;
    }

    // ============================================
    // CHICKEN GAME
    // ============================================
    const CHICKEN_CONFIG = {
        survivalRate: 0.8,      // 80% Überlebensrate (20% Absturz)
        houseEdge: 0.05,        // 5% Hausvorteil
        numStreets: 10          // 10 Straßen
    };

    let chickenGame = {
        active: false,
        bet: 0,
        currentStreet: 0,
        streetStates: []  // Array: true = befahren (gefährlich), false = Baustelle (safe)
    };

    function setChickenBet(amount) {
        document.getElementById('chickenBet').value = amount;
        document.querySelectorAll('#chickenModal .quick-bet-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
    }

    function calculateChickenMultiplier(street) {
        // Wahrscheinlichkeit bis Straße k: P(k) = 0.8^k
        const probability = Math.pow(CHICKEN_CONFIG.survivalRate, street);
        
        // Fairer Multiplier mit Hausvorteil: M = (1 - h) / P(k)
        const multiplier = (1 - CHICKEN_CONFIG.houseEdge) / probability;
        
        return multiplier;
    }

    function createAllStreets() {
        const roadsContainer = document.getElementById('chickenRoads');
        roadsContainer.innerHTML = '';
        
        for (let i = 0; i < CHICKEN_CONFIG.numStreets; i++) {
            const street = document.createElement('div');
            street.id = `street-${i}`;
            street.className = 'chicken-street';
            street.style.cssText = `
                flex: 1;
                background: linear-gradient(to bottom, 
                    rgba(30, 30, 50, 0.8) 0%, 
                    rgba(40, 40, 60, 0.8) 10%, 
                    rgba(50, 50, 70, 0.8) 45%, 
                    rgba(139, 92, 246, 0.3) 48%, 
                    rgba(139, 92, 246, 0.3) 52%, 
                    rgba(50, 50, 70, 0.8) 55%, 
                    rgba(40, 40, 60, 0.8) 90%, 
                    rgba(30, 30, 50, 0.8) 100%
                );
                position: relative;
                border-left: 3px solid rgba(139, 92, 246, 0.4);
                border-right: 3px solid rgba(139, 92, 246, 0.4);
                transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
                overflow: hidden;
                box-shadow: inset 0 0 30px rgba(0,0,0,0.7),
                            0 0 15px rgba(139, 92, 246, 0.2);
            `;
            
            // Street number - NEON STYLE
            const streetNum = document.createElement('div');
            streetNum.textContent = (i + 1);
            streetNum.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 5rem;
                font-weight: 900;
                color: rgba(139, 92, 246, 0.2);
                z-index: 1;
                text-shadow: 0 0 30px rgba(139, 92, 246, 0.6), 
                             0 4px 8px rgba(0,0,0,0.8);
                font-family: 'Arial Black', sans-serif;
            `;
            street.appendChild(streetNum);
            
            // Potential win label (only on NEXT street, not all) - KRASSER!
            const winLabel = document.createElement('div');
            winLabel.id = `street-win-${i}`;
            winLabel.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, rgba(245, 158, 11, 1), rgba(217, 119, 6, 1));
                padding: 14px 24px;
                border-radius: 20px;
                border: 4px solid #fbbf24;
                font-size: 1.75rem;
                font-weight: 900;
                color: white;
                text-shadow: 0 0 20px rgba(255,255,255,1), 
                             0 2px 8px rgba(0,0,0,0.9);
                white-space: nowrap;
                display: none;
                z-index: 10;
                box-shadow: 0 0 40px rgba(245, 158, 11, 1),
                            0 8px 30px rgba(245, 158, 11, 0.8);
                pointer-events: none;
                animation: winLabelPulse 1s ease-in-out infinite;
            `;
            street.appendChild(winLabel);
            
            roadsContainer.appendChild(street);
        }
        
        // Update win labels for all streets
        updateAllStreetWinLabels();
        
        // Add chicken animation styles
        if (!document.getElementById('chickenJumpAnim')) {
            const style = document.createElement('style');
            style.id = 'chickenJumpAnim';
            style.textContent = `
                @keyframes winLabelPulse {
                    0%, 100% { 
                        transform: translate(-50%, -50%) scale(1);
                        box-shadow: 0 0 40px rgba(245, 158, 11, 1),
                                    0 8px 30px rgba(245, 158, 11, 0.8);
                    }
                    50% { 
                        transform: translate(-50%, -50%) scale(1.05);
                        box-shadow: 0 0 60px rgba(245, 158, 11, 1),
                                    0 12px 50px rgba(245, 158, 11, 1);
                    }
                }
                
                @keyframes chickenJump {
                    0% { transform: translate(-50%, -50%) scale(1) rotate(0deg); }
                    20% { transform: translate(-50%, -70%) scale(1.3) rotate(-10deg); }
                    40% { transform: translate(-50%, -90%) scale(1.2) rotate(5deg); }
                    60% { transform: translate(-50%, -70%) scale(1.3) rotate(-5deg); }
                    80% { transform: translate(-50%, -55%) scale(1.1) rotate(2deg); }
                    100% { transform: translate(-50%, -50%) scale(1) rotate(0deg); }
                }
                @keyframes streetReveal {
                    0% { transform: scaleX(0.8); opacity: 0.5; }
                    50% { transform: scaleX(1.05); }
                    100% { transform: scaleX(1); opacity: 1; }
                }
                @keyframes explode {
                    0% { transform: translate(-50%, -50%) scale(1) rotate(0deg); }
                    25% { transform: translate(-50%, -60%) scale(1.5) rotate(180deg); }
                    50% { transform: translate(-50%, -40%) scale(2) rotate(360deg); opacity: 1; }
                    75% { transform: translate(-50%, -30%) scale(2.5) rotate(540deg); opacity: 0.5; }
                    100% { transform: translate(-50%, -20%) scale(3) rotate(720deg); opacity: 0; }
                }
                @keyframes dangerFlash {
                    0%, 100% { 
                        filter: brightness(1);
                    }
                    50% { 
                        filter: brightness(1.3);
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    function updateAllStreetWinLabels() {
        if (!chickenGame.active) return;
        
        // Only show on NEXT street
        const nextStreetIndex = chickenGame.currentStreet;
        
        for (let i = 0; i < CHICKEN_CONFIG.numStreets; i++) {
            const winLabel = document.getElementById(`street-win-${i}`);
            if (!winLabel) continue;
            
            // Only show on the very next street
            if (i === nextStreetIndex && nextStreetIndex < CHICKEN_CONFIG.numStreets) {
                const multiplier = calculateChickenMultiplier(i + 1);
                const winAmount = chickenGame.bet * multiplier;
                
                winLabel.textContent = `${winAmount.toFixed(2)}€`;
                winLabel.style.display = 'block';
            } else {
                winLabel.style.display = 'none';
            }
        }
    }

    async function startChicken() {
        const bet = parseFloat(document.getElementById('chickenBet').value);
        
        if (bet < 0.01 || bet > 10.00) {
            showNotification('Einsatz muss zwischen 0,01€ und 10,00€ liegen!', 'error');
            return;
        }
        
        if (bet > userBalance) {
            showNotification('Nicht genug Guthaben! Verfügbar: ' + userBalance.toFixed(2) + '€', 'error');
            return;
        }

        // Deduct bet
        try {
            const response = await fetch('/api/casino/deduct_balance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount: bet })
            });

            if (!response.ok) {
                throw new Error('HTTP Error: ' + response.status);
            }

            const data = await response.json();
            if (data.status !== 'success') {
                showNotification(data.error || 'Fehler beim Abbuchen des Einsatzes', 'error');
                return;
            }

            updateAllBalances(data.balance);
        } catch (err) {
            console.error('Deduct Balance Error:', err);
            showNotification('Netzwerkfehler: ' + err.message, 'error');
            return;
        }

        // Initialize game
        chickenGame = {
            active: true,
            bet: bet,
            currentStreet: 0,
            streetStates: []
        };

        // UI Updates
        document.getElementById('chickenStartBtn').disabled = true;
        document.getElementById('chickenCrossBtn').disabled = false;
        document.getElementById('chickenCashoutBtn').disabled = false;
        document.getElementById('chickenBet').disabled = true;
        document.getElementById('chickenWin').innerHTML = '';
        document.getElementById('chickenLoss').innerHTML = '';
        
        // Create all 10 streets
        createAllStreets();
        
        document.getElementById('chickenStreetCount').textContent = '0 / 10';
        document.getElementById('chickenMultiplierValue').textContent = '1.00x';
        
        // Show win label on next street
        updateAllStreetWinLabels();
        
        // Reset chicken position
        const chicken = document.getElementById('chickenPlayer');
        chicken.style.left = '40px';
        chicken.style.top = '50%';
        chicken.style.transform = 'translate(0, -50%)';
        chicken.textContent = '🐔';
        
        showNotification('🎮 Spiel gestartet! Überquere 10 Straßen! Einsatz: ' + bet.toFixed(2) + '€', 'info');
    }

    function updatePotentialWin() {
        // REMOVED - not needed anymore
    }

    async function crossStreet() {
        if (!chickenGame.active) return;
        
        if (chickenGame.currentStreet >= CHICKEN_CONFIG.numStreets) {
            showNotification('🏆 Alle Straßen überquert!', 'success');
            await cashoutChicken();
            return;
        }

        // Disable buttons during animation
        document.getElementById('chickenCrossBtn').disabled = true;
        document.getElementById('chickenCashoutBtn').disabled = true;

        // Server determines if street has traffic or is construction site
        try {
            const response = await fetch('/api/casino/chicken_cross.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    street: chickenGame.currentStreet + 1,
                    survival_rate: CHICKEN_CONFIG.survivalRate
                })
            });

            if (!response.ok) {
                throw new Error('HTTP Error: ' + response.status);
            }

            const data = await response.json();
            
            if (data.status !== 'success') {
                showNotification(data.error || 'Fehler beim Überqueren', 'error');
                document.getElementById('chickenCrossBtn').disabled = false;
                document.getElementById('chickenCashoutBtn').disabled = false;
                return;
            }

            const survived = data.survived;
            
            // Increment street FIRST
            chickenGame.currentStreet++;
            
            // Validate street index before accessing DOM
            const streetIndex = chickenGame.currentStreet - 1; // Array index is 0-based
            if (streetIndex < 0 || streetIndex >= CHICKEN_CONFIG.numStreets) {
                console.error('Invalid street index:', streetIndex, 'currentStreet:', chickenGame.currentStreet);
                showNotification('Fehler: Ungültiger Straßenindex', 'error');
                document.getElementById('chickenCrossBtn').disabled = false;
                document.getElementById('chickenCashoutBtn').disabled = false;
                return;
            }
            
            // Get current street element (now incremented)
            const streetEl = document.getElementById(`street-${streetIndex}`);
            
            if (!streetEl) {
                console.error('Street element not found:', streetIndex);
                console.error('Available streets:', document.querySelectorAll('[id^="street-"]').length);
                showNotification('Fehler: Straße nicht gefunden', 'error');
                document.getElementById('chickenCrossBtn').disabled = false;
                document.getElementById('chickenCashoutBtn').disabled = false;
                return;
            }
            
            // Calculate chicken position - perfectly centered on street
            const roadsContainer = document.getElementById('chickenRoads');
            const containerRect = roadsContainer.getBoundingClientRect();
            const streetRect = streetEl.getBoundingClientRect();
            
            // Calculate exact center of the street
            const streetCenterX = streetRect.left + (streetRect.width / 2);
            const containerLeft = containerRect.left;
            const relativeLeft = streetCenterX - containerLeft;
            
            // Account for the start zone offset (80px)
            const newLeft = 80 + relativeLeft;
            
            const chicken = document.getElementById('chickenPlayer');
            
            // Add jumping animation class
            chicken.style.animation = 'chickenJump 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
            
            // Animate chicken to street center
            chicken.style.left = newLeft + 'px';
            
            // Wait for chicken to reach street
            await new Promise(resolve => setTimeout(resolve, 800));
            
            // NOW reveal what happens (street already incremented above)
            
            if (!survived) {
                // TRAFFIC! Chicken gets hit - NEON RED DANGER!
                streetEl.style.background = `
                    linear-gradient(to bottom, 
                        rgba(239, 68, 68, 1) 0%, 
                        rgba(220, 38, 38, 1) 10%, 
                        rgba(185, 28, 28, 1) 45%, 
                        rgba(245, 158, 11, 0.8) 48%, 
                        rgba(245, 158, 11, 0.8) 52%, 
                        rgba(185, 28, 28, 1) 55%, 
                        rgba(220, 38, 38, 1) 90%, 
                        rgba(239, 68, 68, 1) 100%
                    )
                `;
                streetEl.style.animation = 'streetReveal 0.5s ease-out, dangerFlash 0.3s ease-in-out infinite';
                streetEl.style.boxShadow = '0 0 60px rgba(239, 68, 68, 1), ' +
                                          'inset 0 0 50px rgba(0,0,0,0.7), ' +
                                          '0 0 100px rgba(239, 68, 68, 0.8)';
                streetEl.style.border = '3px solid #ef4444';
                
                // Add cars
                addCarsToStreet(streetEl);
                
                // Chicken MEGA explosion
                chicken.style.animation = 'explode 1s cubic-bezier(0.34, 1.56, 0.64, 1)';
                chicken.textContent = '💥';
                
                // Screen shake effect
                document.getElementById('chickenBoard').style.animation = 'shake 0.5s';
                
                setTimeout(() => {
                    document.getElementById('chickenBoard').style.animation = '';
                }, 500);
                
                // Show loss
                document.getElementById('chickenLoss').innerHTML = `
                    <div style="background: linear-gradient(135deg, #ef4444, #dc2626); 
                                padding: 20px; 
                                border-radius: 12px; 
                                text-align: center;
                                border: 3px solid #f87171;
                                box-shadow: 0 8px 32px rgba(239, 68, 68, 0.4);">
                        <div style="font-size: 3rem; margin-bottom: 12px;">💥🚗</div>
                        <div style="font-size: 1.5rem; font-weight: 900; color: white; margin-bottom: 8px;">
                            ÜBERFAHREN! Straße ${chickenGame.currentStreet}
                        </div>
                        <div style="font-size: 1.25rem; color: rgba(255,255,255,0.9);">
                            Verloren: ${chickenGame.bet.toFixed(2)}€
                        </div>
                    </div>
                `;

                await saveCasinoHistory('chicken', chickenGame.bet, 0);
                endChickenGame();
                showNotification('💥 Überfahren auf Straße ' + chickenGame.currentStreet + '!', 'error');
                return;
            }
            
            // SAFE! Construction site (Baustelle) - NEON GREEN!
            streetEl.style.background = `
                linear-gradient(to bottom, 
                    rgba(16, 185, 129, 1) 0%, 
                    rgba(5, 150, 105, 1) 10%, 
                    rgba(4, 120, 87, 1) 45%, 
                    rgba(245, 158, 11, 0.8) 48%, 
                    rgba(245, 158, 11, 0.8) 52%, 
                    rgba(4, 120, 87, 1) 55%, 
                    rgba(5, 150, 105, 1) 90%, 
                    rgba(16, 185, 129, 1) 100%
                )
            `;
            streetEl.style.animation = 'streetReveal 0.5s ease-out';
            streetEl.style.boxShadow = '0 0 60px rgba(16, 185, 129, 1), ' +
                                      'inset 0 0 50px rgba(0,0,0,0.5), ' +
                                      '0 0 100px rgba(16, 185, 129, 0.6)';
            streetEl.style.border = '3px solid #10b981';
            
            // Add construction signs
            addConstructionSigns(streetEl);
            
            // Update multiplier with animation
            const multiplier = calculateChickenMultiplier(chickenGame.currentStreet);
            const multiplierEl = document.getElementById('chickenMultiplierValue');
            multiplierEl.textContent = multiplier.toFixed(2) + 'x';
            multiplierEl.style.animation = 'pulse 0.5s ease-out';
            setTimeout(() => multiplierEl.style.animation = '', 500);
            
            // Update current win amount
            const currentWin = chickenGame.bet * multiplier;
            document.getElementById('chickenCurrentWin').textContent = currentWin.toFixed(2) + '€';
            
            document.getElementById('chickenStreetCount').textContent = `${chickenGame.currentStreet} / 10`;

            // Update win label for next street only
            updateAllStreetWinLabels();

            // Celebration - chicken victory dance
            chicken.style.animation = '';
            chicken.textContent = chickenGame.currentStreet === 10 ? '🐔👑' : '🐔✨';
            
            // Confetti effect
            if (chickenGame.currentStreet === 10) {
                createConfetti();
            }

            const potentialWin = chickenGame.bet * multiplier;
            showNotification(`✅ Safe! Baustelle auf Straße ${chickenGame.currentStreet}! ${multiplier.toFixed(2)}x = ${potentialWin.toFixed(2)}€`, 'success');

            // Check if all streets crossed
            if (chickenGame.currentStreet >= CHICKEN_CONFIG.numStreets) {
                await new Promise(resolve => setTimeout(resolve, 1000));
                showNotification('🏆 Alle 10 Straßen geschafft! Auto-Cashout!', 'success');
                await cashoutChicken();
                return;
            }

            // Re-enable buttons
            document.getElementById('chickenCrossBtn').disabled = false;
            document.getElementById('chickenCashoutBtn').disabled = false;

        } catch (err) {
            console.error('Chicken Cross Error:', err);
            showNotification('Netzwerkfehler: ' + err.message, 'error');
            document.getElementById('chickenCrossBtn').disabled = false;
            document.getElementById('chickenCashoutBtn').disabled = false;
        }
    }

    function addCarsToStreet(streetEl) {
        const carEmojis = ['🚗', '🚙', '🚕', '🚌', '🚐', '🚓', '🚑'];
        const numCars = 4;
        
        for (let i = 0; i < numCars; i++) {
            const car = document.createElement('div');
            car.textContent = carEmojis[Math.floor(Math.random() * carEmojis.length)];
            car.style.cssText = `
                position: absolute;
                top: ${15 + (i * 22)}%;
                left: 50%;
                transform: translateX(-50%);
                font-size: 2.5rem;
                animation: carCrash 0.4s ease-in-out infinite;
                filter: drop-shadow(0 4px 12px rgba(0,0,0,0.9));
            `;
            streetEl.appendChild(car);
        }
        
        if (!document.getElementById('carCrashAnim')) {
            const style = document.createElement('style');
            style.id = 'carCrashAnim';
            style.textContent = `
                @keyframes carCrash {
                    0%, 100% { transform: translateX(-50%) translateY(0) rotate(0deg); }
                    25% { transform: translateX(-55%) translateY(-5px) rotate(-5deg); }
                    75% { transform: translateX(-45%) translateY(5px) rotate(5deg); }
                }
            `;
            document.head.appendChild(style);
        }
    }

    function addConstructionSigns(streetEl) {
        const signs = ['🚧', '⚠️', '🏗️'];
        
        for (let i = 0; i < 3; i++) {
            const sign = document.createElement('div');
            sign.textContent = signs[i];
            sign.style.cssText = `
                position: absolute;
                top: ${15 + (i * 35)}%;
                left: 50%;
                transform: translateX(-50%);
                font-size: 2.5rem;
                filter: drop-shadow(0 4px 8px rgba(0,0,0,0.8));
                animation: signBounce ${0.5 + (i * 0.2)}s ease-in-out infinite;
            `;
            streetEl.appendChild(sign);
        }
        
        if (!document.getElementById('signBounceAnim')) {
            const style = document.createElement('style');
            style.id = 'signBounceAnim';
            style.textContent = `
                @keyframes signBounce {
                    0%, 100% { transform: translateX(-50%) translateY(0); }
                    50% { transform: translateX(-50%) translateY(-8px); }
                }
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-10px); }
                    75% { transform: translateX(10px); }
                }
            `;
            document.head.appendChild(style);
        }
    }

    function createConfetti() {
        const board = document.getElementById('chickenBoard');
        const colors = ['#FFD700', '#FFA500', '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4'];
        
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.textContent = ['🎉', '✨', '⭐', '💫', '🌟'][Math.floor(Math.random() * 5)];
            confetti.style.cssText = `
                position: absolute;
                left: ${Math.random() * 100}%;
                top: ${Math.random() * 100}%;
                font-size: ${1 + Math.random() * 2}rem;
                animation: confettiFall ${2 + Math.random() * 2}s linear;
                pointer-events: none;
                z-index: 1000;
            `;
            board.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 4000);
        }
        
        if (!document.getElementById('confettiAnim')) {
            const style = document.createElement('style');
            style.id = 'confettiAnim';
            style.textContent = `
                @keyframes confettiFall {
                    0% { transform: translateY(-100%) rotate(0deg); opacity: 1; }
                    100% { transform: translateY(600%) rotate(720deg); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    async function cashoutChicken() {
        if (!chickenGame.active || chickenGame.currentStreet === 0) {
            showNotification('Du musst mindestens eine Straße überqueren!', 'error');
            return;
        }

        const multiplier = calculateChickenMultiplier(chickenGame.currentStreet);
        const winAmount = chickenGame.bet * multiplier;

        // Credit win
        try {
            const response = await fetch('/api/casino/add_balance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount: winAmount })
            });

            if (!response.ok) {
                throw new Error('HTTP Error: ' + response.status);
            }

            const data = await response.json();
            if (data.status !== 'success') {
                showNotification(data.error || 'Fehler beim Auszahlen', 'error');
                return;
            }

            updateAllBalances(data.balance);

            // Show win
            const profit = winAmount - chickenGame.bet;
            document.getElementById('chickenWin').innerHTML = `
                <div style="background: linear-gradient(135deg, #10b981, #059669); 
                            padding: 24px; 
                            border-radius: 12px; 
                            text-align: center;
                            border: 3px solid #34d399;
                            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.5);">
                    <div style="font-size: 3rem; margin-bottom: 12px;">🏆</div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: white; margin-bottom: 8px;">
                        CASHOUT! ${chickenGame.currentStreet} Straßen
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: #a7f3d0;">
                        Gewinn: ${winAmount.toFixed(2)}€ (${multiplier.toFixed(2)}x)
                    </div>
                    <div style="font-size: 1rem; color: rgba(255,255,255,0.8); margin-top: 8px;">
                        Profit: +${profit.toFixed(2)}€
                    </div>
                </div>
            `;

            // Save to history
            await saveCasinoHistory('chicken', chickenGame.bet, winAmount);

            showNotification('💰 Cashout erfolgreich! +' + profit.toFixed(2) + '€', 'success');
            
            // Celebration animation
            const chicken = document.getElementById('chickenPlayer');
            chicken.textContent = '🎉';

        } catch (err) {
            console.error('Cashout Error:', err);
            showNotification('Netzwerkfehler beim Cashout: ' + err.message, 'error');
            return;
        }

        endChickenGame();
    }

    function endChickenGame() {
        chickenGame.active = false;
        
        document.getElementById('chickenStartBtn').disabled = false;
        document.getElementById('chickenCrossBtn').disabled = true;
        document.getElementById('chickenCashoutBtn').disabled = true;
        document.getElementById('chickenBet').disabled = false;

        // Reset chicken after delay
        setTimeout(() => {
            const chicken = document.getElementById('chickenPlayer');
            chicken.style.left = '40px';
            chicken.style.top = '50%';
            chicken.style.transform = 'translate(0, -50%)';
            chicken.textContent = '🐔';
            chicken.style.animation = '';
            document.getElementById('chickenRoads').innerHTML = '';
            document.getElementById('chickenStreetCount').textContent = '0 / 10';
            document.getElementById('chickenMultiplierValue').textContent = '1.00x';
            document.getElementById('chickenCurrentWin').textContent = '0.00€';
        }, 3000);
    }

    async function saveCasinoHistory(game, bet, win) {
        try {
            await fetch('/api/casino/save_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    game: game,
                    bet_amount: bet,
                    win_amount: win
                })
            });
        } catch (err) {
            console.error('Error saving history:', err);
        }
    }

    function openGame(game) {
        // Update balance when opening any game
        fetch('/api/casino/get_balance.php')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    updateAllBalances(data.balance);
                }
            })
            .catch(err => console.error('Balance-Fehler:', err));
        
        document.getElementById(game + 'Modal').classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Initialize Plinko board when opening plinko
        if (game === 'plinko') {
            setTimeout(() => initPlinko(), 100);
        }
        
        // Initialize Mines grid when opening mines
        if (game === 'mines') {
            setTimeout(() => {
                resetMines();
                createMinesGrid();
            }, 100);
        }
    }
    
    function closeGame(game) {
        document.getElementById(game + 'Modal').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    // SLOTS GAME
    const slotSymbols = ['🍒', '🍋', '⭐', '7️⃣', '💎'];
    let slotsSpinning = false;
    
    function setSlotsQuickBet(amount) {
        document.getElementById('slotsBet').value = amount.toFixed(2);
    }
    
    async function spinSlots() {
        if (slotsSpinning) return;
        
        const bet = parseFloat(document.getElementById('slotsBet').value);
        if (bet < 0.01 || bet > 10.00) {
            showNotification('Einsatz muss zwischen 0,01€ und 10,00€ liegen!', 'error');
            return;
        }
        
        if (bet > userBalance) {
            showNotification('Nicht genug Guthaben! Verfügbar: ' + userBalance.toFixed(2) + '€ (10€ Reserve)', 'error');
            return;
        }
        
        slotsSpinning = true;
        document.getElementById('slotsSpin').disabled = true;
        document.getElementById('slotsWin').innerHTML = '';
        document.getElementById('slotsLoss').innerHTML = '';
        
        // Spin animation - blurred symbols cycling
        const symbols = ['🍒', '🍋', '⭐', '7️⃣', '💎', '🔔', '🍀'];
        const reels = ['reel1', 'reel2', 'reel3'];
        
        reels.forEach(id => {
            const reel = document.getElementById(id);
            reel.classList.add('spinning');
            
            // Fast symbol cycling during spin
            let symbolIndex = 0;
            const spinInterval = setInterval(() => {
                reel.textContent = symbols[symbolIndex];
                symbolIndex = (symbolIndex + 1) % symbols.length;
            }, 50);
            
            // Store interval for cleanup
            reel.dataset.spinInterval = spinInterval;
        });
        
        try {
            const response = await fetch('/api/casino/play_slots.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bet: bet })
            });
            
            if (!response.ok) {
                console.error('HTTP Error:', response.status);
                const text = await response.text();
                console.error('Response:', text);
                throw new Error('Server-Fehler: ' + response.status);
            }
            
            const text = await response.text();
            console.log('Server response:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Response was:', text);
                throw new Error('Ungültige Server-Antwort');
            }
            
            if (data.status === 'success') {
                // Stop reels sequentially with bounce effect
                reels.forEach((id, index) => {
                    setTimeout(() => {
                        const reel = document.getElementById(id);
                        
                        // Clear spinning interval
                        clearInterval(parseInt(reel.dataset.spinInterval));
                        
                        reel.classList.remove('spinning');
                        reel.classList.add('stopping');
                        reel.textContent = data.result[index];
                        
                        // Remove stopping class after animation
                        setTimeout(() => {
                            reel.classList.remove('stopping');
                            
                            // Add winning animation if all reels match
                            if (data.win_amount > 0 && index === 2) {
                                reels.forEach(reelId => {
                                    document.getElementById(reelId).classList.add('winning');
                                });
                            }
                        }, 800);
                    }, 1500 + (index * 500)); // Stagger: 1.5s, 2s, 2.5s
                });
                
                // Update balance after all reels stopped
                setTimeout(() => {
                    updateAllBalances(data.new_balance);
                    
                    if (data.win_amount > 0) {
                        const winMsg = document.getElementById('slotsWin');
                        winMsg.innerHTML = `
                            <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 20px; border-radius: 16px; border: 3px solid #fbbf24; box-shadow: 0 0 40px rgba(245, 158, 11, 0.6); animation: winPulse 0.5s ease-in-out infinite;">
                                <div style="font-size: 2.5rem; margin-bottom: 8px;">🎉 GEWONNEN! 🎉</div>
                                <div style="font-size: 2rem; font-weight: 900;">${data.win_amount.toFixed(2)}€</div>
                                <div style="font-size: 1.2rem; margin-top: 4px; opacity: 0.9;">${data.multiplier}x Multiplier</div>
                            </div>
                        `;
                        
                        // Confetti effect
                        createConfetti();
                    } else {
                        const lossMsg = document.getElementById('slotsLoss');
                        lossMsg.innerHTML = `
                            <div style="background: linear-gradient(135deg, #ef4444, #dc2626); padding: 16px; border-radius: 12px; border: 2px solid #f87171; color: #fff; font-size: 1.2rem;">
                                Verloren: ${bet.toFixed(2)}€
                            </div>
                        `;
                    }
                    
                    setTimeout(() => {
                        document.getElementById('slotsWin').innerHTML = '';
                        document.getElementById('slotsLoss').innerHTML = '';
                        
                        // Remove winning class
                        reels.forEach(reelId => {
                            document.getElementById(reelId).classList.remove('winning');
                        });
                    }, 4000);
                    
                    slotsSpinning = false;
                    document.getElementById('slotsSpin').disabled = false;
                }, 3000); // After all reels stopped (1500 + 500*3 = 3000ms)
            } else {
                showNotification('Fehler: ' + data.error, 'error');
                slotsSpinning = false;
                document.getElementById('slotsSpin').disabled = false;
                
                // Clear all intervals
                reels.forEach(id => {
                    const reel = document.getElementById(id);
                    clearInterval(parseInt(reel.dataset.spinInterval));
                    reel.classList.remove('spinning');
                });
            }
        } catch (error) {
            showNotification('Verbindungsfehler: ' + error.message, 'error');
            slotsSpinning = false;
            document.getElementById('slotsSpin').disabled = false;
            
            // Clear all intervals
            reels.forEach(id => {
                const reel = document.getElementById(id);
                clearInterval(parseInt(reel.dataset.spinInterval));
                reel.classList.remove('spinning');
            });
        }
    }
    
    // Confetti effect for big wins
    function createConfetti() {
        const colors = ['#f59e0b', '#ec4899', '#8b5cf6', '#10b981', '#3b82f6'];
        const confettiCount = 50;
        
        for (let i = 0; i < confettiCount; i++) {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.top = '-10px';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.borderRadius = '50%';
            confetti.style.pointerEvents = 'none';
            confetti.style.zIndex = '99999';
            confetti.style.animation = `confettiFall ${2 + Math.random() * 2}s linear forwards`;
            
            document.body.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 4000);
        }
    }
    
    // Add confetti animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes confettiFall {
            to {
                transform: translateY(100vh) rotate(${Math.random() * 360}deg);
                opacity: 0;
            }
        }
        
        @keyframes winPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    `;
    document.head.appendChild(style);
    
    // PLINKO GAME
    let plinkoDropping = false;
    let plinkoCanvas, plinkoCtx;
    let balls = []; // Array für Multi-Ball
    let plinkoAnimationId = null;
    let totalWin = 0;
    let ballsDropped = 0;
    let ballsToDropCount = 0;
    let currentDropX = null;
    
    // Plinko configuration (GRÖSSERES SPIELFELD)
    const ROWS = 16;
    const SLOTS = 13;  // 1.0x Slots entfernt für mehr Spannung
    const PIN_RADIUS = 6;
    const BALL_RADIUS = 10;
    
    const slotMultipliers = [
        { multiplier: 10.0, color: '#f59e0b' },  // 0 - Links außen - Mega Jackpot
        { multiplier: 0.3, color: '#ef4444' },   // 1 - Großer Verlust
        { multiplier: 1.5, color: '#0e7490' },   // 2 - Gut
        { multiplier: 0.5, color: '#dc2626' },   // 3 - Verlust
        { multiplier: 2.0, color: '#10b981' },   // 4 - Gewinn
        { multiplier: 0.7, color: '#f87171' },   // 5 - Kleiner Verlust
        { multiplier: 5.0, color: '#8b5cf6' },   // 6 - Mitte - Großer Jackpot
        { multiplier: 0.7, color: '#f87171' },   // 7 - Kleiner Verlust
        { multiplier: 2.0, color: '#10b981' },   // 8 - Gewinn
        { multiplier: 0.5, color: '#dc2626' },   // 9 - Verlust
        { multiplier: 1.5, color: '#0e7490' },   // 10 - Gut
        { multiplier: 0.3, color: '#ef4444' },   // 11 - Großer Verlust
        { multiplier: 10.0, color: '#f59e0b' }   // 12 - Rechts außen - Mega Jackpot
    ];
    
    function initPlinko() {
        console.log('🎯 Initializing Plinko...');
        plinkoCanvas = document.getElementById('plinkoCanvas');
        if (!plinkoCanvas) {
            console.error('❌ Plinko canvas not found');
            return false;
        }
        
        plinkoCtx = plinkoCanvas.getContext('2d');
        if (!plinkoCtx) {
            console.error('❌ Could not get 2D context');
            return false;
        }
        
        // Add click listener for manual ball drop
        plinkoCanvas.addEventListener('click', handleCanvasClick);
        
        console.log('✅ Plinko canvas found:', plinkoCanvas.width, 'x', plinkoCanvas.height);
        console.log('✅ Plinko context ready');
        
        // Draw initial board
        drawPlinkoBoard();
        
        console.log('✅ Plinko initialized successfully');
        return true;
    }
    
    function handleCanvasClick(event) {
        if (ballsToDropCount <= 0) {
            return;
        }
        
        // Bei 10+ Bällen: Erlaube schnelles Klicken (nicht auf plinkoDropping warten)
        const ballCount = parseInt(document.getElementById('plinkoBallCount').value);
        if (ballCount < 10 && plinkoDropping) {
            return;
        }
        
        const rect = plinkoCanvas.getBoundingClientRect();
        const scaleX = plinkoCanvas.width / rect.width;
        const scaleY = plinkoCanvas.height / rect.height;
        
        const x = (event.clientX - rect.left) * scaleX;
        const y = (event.clientY - rect.top) * scaleY;
        
        // Only allow clicks in the top area (y < 120)
        if (y < 120) {
            // Beschränke Drop-Position auf sichere Mitte (verhindert direkte 5x Drops)
            const centerX = 600; // Canvas Mitte
            const maxOffset = 350; // Max 350px von der Mitte entfernt
            let dropX = Math.max(50, Math.min(1150, x));
            
            // Wenn zu weit außen, ziehe zur Mitte
            const distanceFromCenter = Math.abs(dropX - centerX);
            if (distanceFromCenter > maxOffset) {
                dropX = centerX + (dropX > centerX ? maxOffset : -maxOffset);
            }
            
            // Zusätzliche Randomisierung für Fairness (±30px)
            dropX += (Math.random() - 0.5) * 60;
            dropX = Math.max(250, Math.min(950, dropX)); // Verhindere extreme Ränder
            
            currentDropX = dropX;
            dropSingleBallManual();
        }
    }
    
    function drawPlinkoBoard() {
        if (!plinkoCanvas || !plinkoCtx) {
            return;
        }
        
        const width = 1200;
        const height = 700;
        
        plinkoCtx.clearRect(0, 0, width, height);
        
        // Draw pins
        const startY = 100;
        const endY = 550;
        const rowSpacing = (endY - startY) / (ROWS - 1);
        
        for (let row = 0; row < ROWS; row++) {
            const pinsInRow = row + 3;
            const spacing = width / (pinsInRow + 1);
            const y = startY + row * rowSpacing;
            
            for (let pin = 0; pin < pinsInRow; pin++) {
                const x = spacing * (pin + 1);
                
                // Pin glow
                const gradient = plinkoCtx.createRadialGradient(x, y, 0, x, y, PIN_RADIUS * 3);
                gradient.addColorStop(0, 'rgba(139,92,246,0.6)');
                gradient.addColorStop(1, 'rgba(139,92,246,0)');
                plinkoCtx.fillStyle = gradient;
                plinkoCtx.fillRect(x - PIN_RADIUS * 3, y - PIN_RADIUS * 3, PIN_RADIUS * 6, PIN_RADIUS * 6);
                
                // Pin
                plinkoCtx.beginPath();
                plinkoCtx.arc(x, y, PIN_RADIUS, 0, Math.PI * 2);
                plinkoCtx.fillStyle = '#8b5cf6';
                plinkoCtx.fill();
                plinkoCtx.strokeStyle = '#a78bfa';
                plinkoCtx.lineWidth = 2;
                plinkoCtx.stroke();
            }
        }
        
        // Draw slots
        const slotY = 600;
        const slotWidth = width / SLOTS;
        const slotHeight = 80;
        
        for (let i = 0; i < SLOTS; i++) {
            const x = i * slotWidth;
            const slot = slotMultipliers[i];
            
            // Slot background
            const gradient = plinkoCtx.createLinearGradient(x, slotY, x, slotY + slotHeight);
            gradient.addColorStop(0, slot.color + '80');
            gradient.addColorStop(1, slot.color);
            plinkoCtx.fillStyle = gradient;
            plinkoCtx.fillRect(x, slotY, slotWidth, slotHeight);
            
            // Slot border
            plinkoCtx.strokeStyle = 'rgba(255,255,255,0.3)';
            plinkoCtx.lineWidth = 2;
            plinkoCtx.strokeRect(x, slotY, slotWidth, slotHeight);
            
            // Multiplier text
            plinkoCtx.fillStyle = '#ffffff';
            plinkoCtx.font = 'bold 24px Inter';
            plinkoCtx.textAlign = 'center';
            plinkoCtx.textBaseline = 'middle';
            plinkoCtx.shadowColor = 'rgba(0,0,0,0.8)';
            plinkoCtx.shadowBlur = 6;
            plinkoCtx.fillText(slot.multiplier + 'x', x + slotWidth / 2, slotY + slotHeight / 2);
            plinkoCtx.shadowBlur = 0;
        }
        
        // Draw all balls
        balls.forEach(ball => {
            if (ball && typeof ball.x === 'number' && typeof ball.y === 'number') {
                // Outer glow - LILA
                const glowGradient = plinkoCtx.createRadialGradient(ball.x, ball.y, 0, ball.x, ball.y, BALL_RADIUS * 3);
                glowGradient.addColorStop(0, 'rgba(167, 139, 250, 1)');
                glowGradient.addColorStop(0.4, 'rgba(139, 92, 246, 0.8)');
                glowGradient.addColorStop(1, 'rgba(139, 92, 246, 0)');
                plinkoCtx.fillStyle = glowGradient;
                plinkoCtx.beginPath();
                plinkoCtx.arc(ball.x, ball.y, BALL_RADIUS * 3, 0, Math.PI * 2);
                plinkoCtx.fill();
                
                // Main ball body - LILA
                const ballGradient = plinkoCtx.createRadialGradient(
                    ball.x - BALL_RADIUS * 0.3, 
                    ball.y - BALL_RADIUS * 0.3, 
                    BALL_RADIUS * 0.1,
                    ball.x, 
                    ball.y, 
                    BALL_RADIUS
                );
                ballGradient.addColorStop(0, '#ffffff');
                ballGradient.addColorStop(0.2, '#ddd6fe');
                ballGradient.addColorStop(0.5, '#a78bfa');
                ballGradient.addColorStop(1, '#8b5cf6');
                
                plinkoCtx.fillStyle = ballGradient;
                plinkoCtx.beginPath();
                plinkoCtx.arc(ball.x, ball.y, BALL_RADIUS, 0, Math.PI * 2);
                plinkoCtx.fill();
                
                // White outline
                plinkoCtx.strokeStyle = '#ffffff';
                plinkoCtx.lineWidth = 2;
                plinkoCtx.beginPath();
                plinkoCtx.arc(ball.x, ball.y, BALL_RADIUS, 0, Math.PI * 2);
                plinkoCtx.stroke();
                
                // Highlight spot
                plinkoCtx.fillStyle = 'rgba(255, 255, 255, 0.8)';
                plinkoCtx.beginPath();
                plinkoCtx.arc(ball.x - BALL_RADIUS * 0.3, ball.y - BALL_RADIUS * 0.3, BALL_RADIUS * 0.3, 0, Math.PI * 2);
                plinkoCtx.fill();
            }
        });
    }
    function setPlinkoQuickBet(amount) {
        document.getElementById('plinkoBet').value = amount.toFixed(2);
    }
    
    
    function setPlinkoBet(amount) {
        if (plinkoDropping) return;
        
        document.getElementById('plinkoBet').value = amount;
        
        document.querySelectorAll('.plinko-bet-btn').forEach(btn => {
            btn.classList.remove('plinko-bet-active');
        });
        
        const clickedBtn = Array.from(document.querySelectorAll('.plinko-bet-btn')).find(
            btn => btn.textContent.includes(amount + '€')
        );
        if (clickedBtn) {
            clickedBtn.classList.add('plinko-bet-active');
        }
    }
    
    function setPlinkoBalls(count) {
        if (plinkoDropping) return;
        
        document.getElementById('plinkoBallCount').value = count;
        
        document.querySelectorAll('.plinko-balls-btn').forEach(btn => {
            btn.classList.remove('plinko-balls-active');
        });
        
        const clickedBtn = Array.from(document.querySelectorAll('.plinko-balls-btn')).find(
            btn => btn.textContent.trim() === count.toString()
        );
        if (clickedBtn) {
            clickedBtn.classList.add('plinko-balls-active');
        }
    }
    
    async function dropBalls() {
        if (plinkoDropping) {
            console.log('❌ Already dropping, wait...');
            return;
        }
        
        const bet = parseFloat(document.getElementById('plinkoBet').value);
        const ballCount = parseInt(document.getElementById('plinkoBallCount').value);
        const totalBet = bet * ballCount;
        
        console.log(`🎯 Preparing ${ballCount} balls - Bet per ball: ${bet}€, Total: ${totalBet}€`);
        
        if (bet < 0.01 || bet > 10.00) {
            showNotification('Einsatz muss zwischen 0,01€ und 10,00€ liegen!', 'error');
            return;
        }
        
        if (totalBet > userBalance) {
            showNotification(`Nicht genug Guthaben! Benötigt: ${totalBet.toFixed(2)}€, Verfügbar: ${userBalance.toFixed(2)}€`, 'error');
            return;
        }
        
        // Set balls to drop count
        ballsToDropCount = ballCount;
        totalWin = 0;
        ballsDropped = 0;
        
        // Disable all buttons
        document.getElementById('plinkoDropBtn').disabled = true;
        document.getElementById('plinkoDropBtn').style.opacity = '0.5';
        document.querySelectorAll('.plinko-bet-btn, .plinko-balls-btn').forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
        });
        
        // Show instructions
        const instructionsDiv = document.getElementById('plinkoInstructions');
        if (instructionsDiv) {
            instructionsDiv.style.display = 'block';
            if (ballCount >= 10) {
                instructionsDiv.innerHTML = `👆 Klicke oben auf das Spielfeld! Du kannst mehrfach klicken für mehrere Bälle gleichzeitig! (${ballsToDropCount} übrig)`;
            } else {
                instructionsDiv.innerHTML = `👆 Klicke oben auf das Spielfeld, um ${ballCount > 1 ? 'die Bälle zu starten' : 'den Ball zu starten'}! (${ballsToDropCount} übrig)`;
            }
        }
        
        document.getElementById('plinkoResult').innerHTML = `<div style="text-align: center; padding: 12px; color: #fbbf24; font-size: 1.1rem; font-weight: 700;">🎯 Bereit! Klicke auf das Spielfeld zum Starten (${ballCount} Bälle, ${totalBet.toFixed(2)}€ Einsatz)</div>`;
    }
    
    async function dropSingleBallManual() {
        if (ballsToDropCount <= 0 || currentDropX === null) {
            return;
        }
        
        const ballCount = parseInt(document.getElementById('plinkoBallCount').value);
        
        // Bei 10+ Bällen: Erlaube mehrere Bälle gleichzeitig
        if (ballCount < 10 && plinkoDropping) {
            return;
        }
        
        // Setze plinkoDropping nur wenn weniger als 10 Bälle
        if (ballCount < 10) {
            plinkoDropping = true;
        }
        
        const bet = parseFloat(document.getElementById('plinkoBet').value);
        const totalBalls = ballCount;
        
        try {
            const response = await fetch('/api/casino/play_plinko.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bet: bet })
            });
            
            if (!response.ok) {
                throw new Error('Server-Fehler: ' + response.status);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Create ball at clicked position
                const ball = createBallAt(currentDropX);
                balls.push(ball);
                
                // Im 10+ Ball-Modus sofort für nächsten Ball bereit machen
                if (ballCount >= 10) {
                    // Animation läuft asynchron weiter
                    animateSingleBall(ball, data.slot).then(() => {
                        setTimeout(() => {
                            const idx = balls.indexOf(ball);
                            if (idx > -1) balls.splice(idx, 1);
                            drawPlinkoBoard();
                        }, 1500);
                    });
                } else {
                    await animateSingleBall(ball, data.slot);
                    
                    // Remove ball after landing
                    setTimeout(() => {
                        const idx = balls.indexOf(ball);
                        if (idx > -1) balls.splice(idx, 1);
                        drawPlinkoBoard();
                    }, 1500);
                }
                
                // Accumulate winnings
                totalWin += data.win;
                ballsDropped++;
                ballsToDropCount--;
                
                // Update instructions
                const instructionsDiv = document.getElementById('plinkoInstructions');
                if (instructionsDiv && ballsToDropCount > 0) {
                    if (ballCount >= 10) {
                        instructionsDiv.innerHTML = `👆 Weiter klicken! Mehrere Bälle gleichzeitig möglich! (${ballsToDropCount} Bälle übrig)`;
                    } else {
                        instructionsDiv.innerHTML = `👆 Klicke erneut zum Starten! (${ballsToDropCount} Bälle übrig)`;
                    }
                } else if (instructionsDiv) {
                    instructionsDiv.style.display = 'none';
                }
                
                // Update result display
                const profitLoss = totalWin - (bet * ballsDropped);
                const isWin = profitLoss > 0;
                document.getElementById('plinkoResult').innerHTML = `
                    <div style="text-align: center; padding: 12px; background: linear-gradient(135deg, rgba(${isWin ? '16,185,129' : '239,68,68'},0.2), rgba(${isWin ? '5,150,105' : '185,28,28'},0.3)); border-radius: 12px; border: 2px solid ${isWin ? '#10b981' : '#ef4444'};">
                        <div style="font-size: 1.2rem; font-weight: 900; color: ${isWin ? '#10b981' : '#ef4444'}; margin-bottom: 4px;">
                            Ball ${ballsDropped}/${totalBalls}: ${data.slot_multiplier}x
                        </div>
                        <div style="font-size: 1rem; color: #fff;">
                            Gewinn: ${totalWin.toFixed(2)}€ | Profit: ${profitLoss > 0 ? '+' : ''}${profitLoss.toFixed(2)}€
                        </div>
                    </div>
                `;
                
                // Final ball - show final result and re-enable
                if (ballsToDropCount === 0) {
                    // Warte bis alle Bälle gelandet sind (wichtig bei Multi-Ball!)
                    const waitForAllBalls = setInterval(() => {
                        if (balls.length === 0) {
                            clearInterval(waitForAllBalls);
                            
                            updateAllBalances(data.new_balance);
                            
                            // Re-enable buttons
                            enablePlinkoButtons();
                            
                            drawPlinkoBoard();
                        }
                    }, 100);
                    
                    // Fallback: Nach max 10 Sekunden zwangsweise aufräumen
                    setTimeout(() => {
                        clearInterval(waitForAllBalls);
                        if (ballsToDropCount === 0) {
                            balls = [];
                            enablePlinkoButtons();
                            drawPlinkoBoard();
                        }
                    }, 10000);
                } else {
                    // Not final ball - re-enable for next drop
                    plinkoDropping = false;
                }
                
            } else {
                throw new Error(data.error || 'Unbekannter Fehler');
            }
        } catch (error) {
            console.error('❌ Plinko error:', error);
            showNotification('Fehler: ' + error.message, 'error');
            ballsToDropCount = 0;
            plinkoDropping = false;
            enablePlinkoButtons();
        }
    }
    
    
    function enablePlinkoButtons() {
        plinkoDropping = false; // Reset dropping state
        ballsToDropCount = 0;   // Reset counter
        currentDropX = null;    // Reset drop position
        
        document.getElementById('plinkoDropBtn').disabled = false;
        document.getElementById('plinkoDropBtn').style.opacity = '1';
        document.querySelectorAll('.plinko-bet-btn, .plinko-balls-btn').forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        });
        
        const instructionsDiv = document.getElementById('plinkoInstructions');
        if (instructionsDiv) {
            instructionsDiv.style.display = 'none';
        }
    }
    
    function createBall() {
        return {
            x: 600, // Canvas center (1200 / 2)
            y: 30,
            vx: 0,
            vy: 0,
            active: true
        };
    }
    
    function createBallAt(x) {
        return {
            x: x,
            y: 30,
            vx: 0,
            vy: 0,
            active: true
        };
    }
    
    function animateSingleBall(ball, finalSlot) {
        return new Promise((resolve) => {
            const width = 1200;
            const startY = 100;
            const endY = 550;
            const slotY = 600;
            const slotWidth = width / SLOTS;
            const rowSpacing = (endY - startY) / (ROWS - 1);
            
            // PHYSIK-KONSTANTEN - GEISTESKRANK EDITION! 🔥
            const GRAVITY = 0.25;      // 2.5x STÄRKER! (war 0.10)
            const BOUNCE = 0.75;       // MEHR Bounce! (war 0.62)
            const FRICTION = 0.985;    // WENIGER Reibung! (war 0.99)
            const MAX_SPEED = 4.5;     // SCHNELLER! (war 2.5)
            const CENTER_PULL = 0.008; // WENIGER Pull! (war 0.015)
            const MIN_VY = 0.8;        // MEHR Minimum! (war 0.3)
            
            let finished = false;
            let frameCount = 0;
            let lastY = ball.y;
            let stuckCounter = 0;
            
            // KÜRZERES Timeout: 3 Sekunden - SCHNELLER!
            const forceFinishTimeout = setTimeout(() => {
                if (!finished) {
                    console.warn('🚨 FORCE FINISH after 3s - Ball to slot', finalSlot);
                    finished = true;
                    const serverSlot = finalSlot;
                    ball.x = serverSlot * slotWidth + slotWidth / 2;
                    ball.y = slotY + 35;
                    ball.vx = 0;
                    ball.vy = 0;
                    drawPlinkoBoard();
                    resolve();
                }
            }, 3000); // 3 Sekunden statt 5!
            
            // Get all pin positions
            const pins = [];
            for (let row = 0; row < ROWS; row++) {
                const pinsInRow = row + 3;
                const spacing = width / (pinsInRow + 1);
                const y = startY + row * rowSpacing;
                
                for (let pin = 0; pin < pinsInRow; pin++) {
                    const x = spacing * (pin + 1);
                    pins.push({ x, y, row, index: pin });
                }
            }
            
            const animate = () => {
                if (finished || !ball.active) {
                    clearTimeout(forceFinishTimeout);
                    resolve();
                    return;
                }
                
                frameCount++;
                
                // AGGRESSIVE Stuck-Detection - KEIN HÄNGENBLEIBEN!
                if (frameCount % 15 === 0) { // ÖFTER checken (15 statt 25)
                    if (Math.abs(ball.y - lastY) < 1.5) { // EMPFINDLICHER (1.5 statt 2.0)
                        stuckCounter++;
                        console.warn('🔴 Ball stuck - POWER BOOST!', frameCount, stuckCounter);
                        
                        // KRASSER Schub!
                        ball.vy += 3.5; // VIEL MEHR! (war 1.5)
                        ball.vx += (Math.random() - 0.5) * 3.0; // MEHR Chaos! (war 1.5)
                        ball.y += 8; // MEHR Push! (war 3)
                        
                        if (stuckCounter > 1) { // SCHNELLER reagieren (1 statt 2)
                            console.warn('🔴🔴 TELEPORT TIME!');
                            ball.y += 150; // KRASSER Teleport! (war 80)
                            ball.vy = 4.0; // MEHR Speed! (war 2.0)
                            ball.vx = (Math.random() - 0.5) * 3.0; // MEHR horizontal
                        }
                    } else {
                        stuckCounter = 0;
                    }
                    lastY = ball.y;
                }
                
                // Apply gravity - KRASS!
                ball.vy += GRAVITY;
                
                // GARANTIERE STARKE vertikale Geschwindigkeit
                if (ball.y > startY + 20) {
                    if (Math.abs(ball.vy) < MIN_VY) {
                        ball.vy += MIN_VY * 3; // TRIPPLE! (war 2x)
                    }
                }
                
                // Leichte zentrale Anziehung (macht äußere Slots schwerer erreichbar)
                const centerX = width / 2;
                const distanceFromCenter = ball.x - centerX;
                ball.vx -= distanceFromCenter * CENTER_PULL / width;
                
                // Limit max speed
                const currentSpeed = Math.sqrt(ball.vx * ball.vx + ball.vy * ball.vy);
                if (currentSpeed > MAX_SPEED) {
                    const ratio = MAX_SPEED / currentSpeed;
                    ball.vx *= ratio;
                    ball.vy *= ratio;
                }
                
                // Check collision with pins
                let collided = false;
                pins.forEach(pin => {
                    const dx = ball.x - pin.x;
                    const dy = ball.y - pin.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    const minDistance = BALL_RADIUS + PIN_RADIUS + 2; // ENGER! (war 4) - Mehr Kollisionen!
                    
                    if (distance < minDistance && !collided) {
                        collided = true;
                        
                        // Collision!
                        const angle = Math.atan2(dy, dx);
                        const overlap = minDistance - distance;
                        
                        // Moderater Push (Balance)
                        ball.x += Math.cos(angle) * (overlap + 8); // MEHR Push! (war 5)
                        ball.y += Math.sin(angle) * (overlap + 8);
                        
                        // Calculate bounce - HÄRTER!
                        const speed = Math.sqrt(ball.vx * ball.vx + ball.vy * ball.vy);
                        const bounceSpeed = speed * BOUNCE;
                        
                        ball.vx = Math.cos(angle) * bounceSpeed;
                        ball.vy = Math.sin(angle) * bounceSpeed;
                        
                        // STARKER random impulse - CHAOS!
                        ball.vx += (Math.random() - 0.5) * 3.5; // MEHR! (war 1.5)
                        
                        // Anti-Verkant - AGGRESSIV!
                        if (Math.abs(ball.vy) < 1.2) { // Höherer Threshold
                            ball.vy += 3.0; // KRASS! (war 1.5)
                        }
                        if (Math.abs(ball.vx) < 0.8) { // Höherer Threshold
                            ball.vx += (Math.random() - 0.5) * 3.0; // MEHR! (war 1.5)
                        }
                        
                        // STARKER Push nach unten
                        ball.y += 3; // MEHR! (war 1)
                    }
                });
                
                // Zusätzlicher Check: Falls Ball langsam wird - BOOST!
                if (ball.y > startY + 40) {
                    const totalSpeed = Math.sqrt(ball.vx * ball.vx + ball.vy * ball.vy);
                    if (totalSpeed < 1.5) { // Höherer Threshold (war 0.8)
                        ball.vy += 3.5; // KRASSER Boost! (war 1.5)
                        ball.vx += (Math.random() - 0.5) * 2.5; // MEHR! (war 1.0)
                        ball.y += 10; // MEHR! (war 5)
                        console.warn('🟡 Ball slow - POWER BOOST!');
                    }
                }
                
                // Emergency: Falls Ball zu lange braucht - AGGRESSIVE!
                if (frameCount > 200) { // FRÜHER! (war 350)
                    ball.vy += 2.0; // MEHR! (war 0.5)
                    ball.y += 8; // MEHR! (war 3)
                    if (frameCount % 30 === 0) { // Öfter loggen
                        console.warn('🟠 Ball taking long - FORCING!', frameCount);
                    }
                }
                
                // Nur im Notfall: Ball MUSS landen - KRASS!
                if (frameCount > 250) { // FRÜHER! (war 450)
                    ball.vy += 4.0; // VIEL MEHR! (war 1.0)
                    ball.y += 15; // VIEL MEHR! (war 5)
                    if (frameCount % 20 === 0) {
                        console.warn('🔴 FORCING LANDING - frame:', frameCount);
                    }
                }
                
                // Apply velocity
                ball.x += ball.vx;
                ball.y += ball.vy;
                
                // Apply friction
                ball.vx *= FRICTION;
                
                // Boundary checks
                if (ball.x < BALL_RADIUS) {
                    ball.x = BALL_RADIUS;
                    ball.vx = Math.abs(ball.vx) * BOUNCE;
                }
                if (ball.x > width - BALL_RADIUS) {
                    ball.x = width - BALL_RADIUS;
                    ball.vx = -Math.abs(ball.vx) * BOUNCE;
                }
                
                // Check if ball reached slot
                if (ball.y >= slotY - 10) {
                    clearTimeout(forceFinishTimeout); // Clear timeout wenn Ball normal landet
                    
                    // USE SERVER-DETERMINED SLOT (nicht die physikalische Position!)
                    // Dies garantiert, dass der Ball im korrekten Slot landet
                    const serverSlot = finalSlot;
                    
                    // Snap to center of SERVER slot
                    ball.x = serverSlot * slotWidth + slotWidth / 2;
                    ball.y = slotY + 35;
                    ball.vx = 0;
                    ball.vy = 0;
                    
                    finished = true;
                    drawPlinkoBoard();
                    setTimeout(() => resolve(), 300);
                    return;
                }
                
                // Gradually steer ball towards final slot as it gets closer to bottom
                if (ball.y > endY - 100) {
                    const targetX = finalSlot * slotWidth + slotWidth / 2;
                    const diff = targetX - ball.x;
                    ball.vx += diff * 0.003; // Sanfte Lenkung zum Zielslot
                }
                
                // Draw current frame
                drawPlinkoBoard();
                
                // Continue animation
                requestAnimationFrame(animate);
            };
            
            // Start animation
            animate();
        });
    }
    
    function showPlinkoResult(data, bet) {
        const resultDiv = document.getElementById('plinkoResult');
        
        if (data.multiplier > 1.0) {
            // WIN
            resultDiv.innerHTML = `
                <div style="background: linear-gradient(135deg, #10b981, #059669); padding: 14px; border-radius: 12px; border: 3px solid #34d399; box-shadow: 0 0 40px rgba(16,185,129,0.8); animation: winPulse 0.6s ease-in-out 3;">
                    <div style="font-size: 1.3rem; margin-bottom: 4px; text-align: center;">🎉 GEWONNEN! 🎉</div>
                    <div style="font-size: 1.6rem; font-weight: 900; color: #fff; text-align: center;">${data.win_amount.toFixed(2)}€ (${data.multiplier}x)</div>
                </div>
            `;
            createPlinkoConfetti();
        } else if (data.multiplier === 1.0) {
            // BREAK EVEN
            resultDiv.innerHTML = `
                <div style="background: linear-gradient(135deg, #eab308, #ca8a04); padding: 12px; border-radius: 12px; border: 2px solid #fbbf24; color: #fff;">
                    <div style="font-size: 1.1rem; text-align: center; font-weight: 700;">🟡 Break Even (1.0x)</div>
                </div>
            `;
        } else {
            // LOSS
            const lossAmount = (bet - data.win_amount).toFixed(2);
            resultDiv.innerHTML = `
                <div style="background: linear-gradient(135deg, #ef4444, #dc2626); padding: 12px; border-radius: 12px; border: 2px solid #f87171; color: #fff;">
                    <div style="font-size: 1.1rem; text-align: center; font-weight: 700;">😢 Verloren: -${lossAmount}€ (${data.multiplier}x)</div>
                </div>
            `;
        }
        
        // Auto-clear result after 4 seconds
        setTimeout(() => {
            resultDiv.innerHTML = '';
        }, 4000);
    }
    
    function createPlinkoConfetti() {
        const colors = ['#ef4444', '#eab308', '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899'];
        
        for (let i = 0; i < 60; i++) {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.left = '50%';
            confetti.style.top = '30%';
            confetti.style.width = (6 + Math.random() * 8) + 'px';
            confetti.style.height = (6 + Math.random() * 8) + 'px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
            confetti.style.zIndex = '9999';
            confetti.style.pointerEvents = 'none';
            confetti.style.boxShadow = '0 0 10px currentColor';
            
            document.body.appendChild(confetti);
            
            const angle = Math.random() * Math.PI * 2;
            const velocity = 6 + Math.random() * 12;
            const vx = Math.cos(angle) * velocity;
            const vy = Math.sin(angle) * velocity - 10;
            
            let x = 0, y = 0, vy2 = vy, rotation = 0;
            const rotSpeed = (Math.random() - 0.5) * 25;
            
            const animate = () => {
                y += vy2;
                x += vx;
                vy2 += 0.5;
                rotation += rotSpeed;
                
                confetti.style.transform = `translate(${x}px, ${y}px) rotate(${rotation}deg)`;
                confetti.style.opacity = Math.max(0, 1 - y / 500);
                
                if (y < 600) {
                    requestAnimationFrame(animate);
                } else {
                    confetti.remove();
                }
            };
            
            requestAnimationFrame(animate);
        }
    }
    
    // CRASH GAME
    let crashRunning = false;
    let crashMultiplier = 0.00;
    let crashInterval = null;
    let crashPoint = 0;
    
    function setCrashBet(amount) {
        document.getElementById('crashBet').value = amount;
    }
    
    async function startCrash() {
        if (crashRunning) return;
        
        const bet = parseFloat(document.getElementById('crashBet').value);
        if (bet < 0.01 || bet > 10.00) {
            alert('Einsatz muss zwischen 0,01€ und 10,00€ liegen!');
            return;
        }
        
        if (bet > userBalance) {
            alert('Nicht genug Guthaben! Verfügbar: ' + userBalance.toFixed(2) + '€ (10€ Reserve)');
            return;
        }
        
        try {
            const response = await fetch('/api/casino/start_crash.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bet: bet })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                crashRunning = true;
                crashPoint = parseFloat(data.crash_point); // Server-generated crash point
                crashMultiplier = 0.00;
                
                console.log('🎮 Crash point:', crashPoint + 'x');
                
                // Disable bet input and quick bet buttons
                const crashBetInput = document.getElementById('crashBet');
                const quickBetButtons = document.querySelectorAll('#crashModal .quick-bet-btn');
                
                crashBetInput.disabled = true;
                crashBetInput.style.opacity = '0.5';
                crashBetInput.style.cursor = 'not-allowed';
                
                quickBetButtons.forEach(btn => {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                });
                
                // Reset rocket and explosion
                const airplane = document.getElementById('crashAirplane');
                const explosion = document.getElementById('crashExplosion');
                const ground = document.getElementById('ground');
                const crashGraph = document.getElementById('crashGraph');
                
                airplane.classList.remove('crashed');
                airplane.classList.add('flying');
                airplane.style.opacity = '1';
                airplane.style.bottom = '150px';
                explosion.style.display = 'none';
                ground.style.transform = 'translateY(0)';
                crashGraph.className = 'crash-graph';
                
                // Hide all easter eggs (only 5 now)
                for (let i = 1; i <= 5; i++) {
                    const egg = document.getElementById('egg' + i);
                    if (egg) {
                        egg.style.display = 'none';
                        egg.style.bottom = '200px';
                    }
                }
                
                document.getElementById('crashStartBtn').style.display = 'none';
                document.getElementById('crashCashoutBtn').style.display = 'block';
                document.getElementById('crashWin').classList.remove('show');
                document.getElementById('crashLoss').classList.remove('show');
                
                // Animate rocket going UP
                let altitude = 0; // km
                let groundOffset = 0;
                
                crashInterval = setInterval(() => {
                    crashMultiplier += 0.01;
                    altitude = crashMultiplier * 10; // km
                    groundOffset += 5; // Move ground down
                    
                    // Move ground down to simulate upward movement
                    ground.style.transform = `translateY(${groundOffset}px)`;
                    
                    // Move easter eggs down with ground
                    for (let i = 1; i <= 9; i++) {
                        const egg = document.getElementById('egg' + i);
                        if (egg && egg.style.display !== 'none') {
                            const baseBottom = 200 + (i * 30);
                            egg.style.bottom = (baseBottom - groundOffset/2) + 'px';
                        }
                    }
                    
                    // Update meters
                    document.getElementById('altitudeValue').textContent = altitude.toFixed(1) + 'km';
                    document.getElementById('speedValue').textContent = crashMultiplier.toFixed(2) + 'x';
                    
                    document.getElementById('crashMultiplier').textContent = crashMultiplier.toFixed(2) + 'x';
                    document.getElementById('crashMultiplier').style.color = crashMultiplier >= 2 ? '#10b981' : '#8b5cf6';
                    
                    // Change background based on altitude
                    const milkyWay = document.getElementById('milkyWay');
                    if (crashMultiplier >= 10) {
                        crashGraph.className = 'crash-graph space-level-3';
                        milkyWay.style.opacity = '1';
                    } else if (crashMultiplier >= 5) {
                        crashGraph.className = 'crash-graph space-level-3';
                        milkyWay.style.opacity = '0.5';
                    } else if (crashMultiplier >= 3) {
                        crashGraph.className = 'crash-graph space-level-2';
                        milkyWay.style.opacity = '0';
                    } else if (crashMultiplier >= 1.5) {
                        crashGraph.className = 'crash-graph space-level-1';
                        milkyWay.style.opacity = '0';
                    }
                    
                    // Show asteroids at higher levels
                    for (let i = 1; i <= 3; i++) {
                        const asteroid = document.getElementById('asteroid' + i);
                        if (asteroid) {
                            asteroid.style.opacity = crashMultiplier >= (3 + i) ? '1' : '0';
                        }
                    }
                    
                    // Show nebulas
                    const nebulas = document.querySelectorAll('.nebula');
                    nebulas.forEach(n => {
                        n.style.opacity = crashMultiplier >= 7 ? '1' : '0';
                    });
                    
                    // Show easter eggs at specific multipliers (REDUCED TO 5)
                    for (let i = 1; i <= 5; i++) {
                        const egg = document.getElementById('egg' + i);
                        if (egg) {
                            const trigger = parseFloat(egg.getAttribute('data-trigger'));
                            if (crashMultiplier >= trigger && egg.style.display === 'none') {
                                egg.style.display = 'block';
                                egg.style.bottom = (200 + (i * 50) - groundOffset/2) + 'px';
                            }
                        }
                    }
                    
                    // Show danger indicator when close to crash
                    if (crashMultiplier >= crashPoint * 0.8) {
                    }
                    
                    if (crashMultiplier >= crashPoint) {
                        crashGame();
                    }
                }, 100);
                
                updateAllBalances(data.balance);
            } else {
                alert('Fehler: ' + data.error);
            }
        } catch (error) {
            alert('Verbindungsfehler: ' + error.message);
        }
    }
    
    async function cashoutCrash() {
        if (!crashRunning) return;
        
        clearInterval(crashInterval);
        crashRunning = false;
        
        const bet = parseFloat(document.getElementById('crashBet').value);
        const airplane = document.getElementById('crashAirplane');
        
        // Stop flying animation
        airplane.classList.remove('flying');
        
        try {
            const response = await fetch('/api/casino/cashout_crash.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bet: bet, multiplier: crashMultiplier })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                updateAllBalances(data.new_balance);
                document.getElementById('crashWin').textContent = `🎉 Cashed Out! ${crashMultiplier.toFixed(2)}x = ${data.win_amount.toFixed(2)}€`;
                document.getElementById('crashWin').classList.add('show');
                
                // Reset after 3 seconds
                setTimeout(() => {
                    airplane.style.bottom = "150px";
                    // Rocket stays centered
                    document.getElementById('crashStartBtn').style.display = 'block';
                    document.getElementById('crashCashoutBtn').style.display = 'none';
                    document.getElementById('crashMultiplier').textContent = '0.00x';
                    document.getElementById('crashMultiplier').style.color = 'var(--success)';
                    
                    // Re-enable bet input and buttons
                    const crashBetInput = document.getElementById('crashBet');
                    const quickBetButtons = document.querySelectorAll('#crashModal .quick-bet-btn');
                    
                    crashBetInput.disabled = false;
                    crashBetInput.style.opacity = '1';
                    crashBetInput.style.cursor = 'text';
                    
                    quickBetButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    });
                    
                    // Hide win message
                    document.getElementById('crashWin').classList.remove('show');
                }, 2000);
            }
        } catch (error) {
            alert('Verbindungsfehler: ' + error.message);
        }
    }
    
    function crashGame() {
        clearInterval(crashInterval);
        crashRunning = false;
        
        const bet = parseFloat(document.getElementById('crashBet').value);
        const airplane = document.getElementById('crashAirplane');
        const explosion = document.getElementById('crashExplosion');
        const debris = document.getElementById('debris');
        
        // Update balance from server SOFORT
        fetch('/api/casino/get_balance.php')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    updateAllBalances(data.balance);
                }
            })
            .catch(err => console.error('Balance-Fehler:', err));
        
        // SOFORT Buttons umschalten!
        document.getElementById('crashCashoutBtn').style.display = 'none';
        document.getElementById('crashStartBtn').style.display = 'block';
        
        // Re-enable bet input SOFORT!
        const crashBetInput = document.getElementById('crashBet');
        const quickBetButtons = document.querySelectorAll('#crashModal .quick-bet-btn');
        
        crashBetInput.disabled = false;
        crashBetInput.style.opacity = '1';
        crashBetInput.style.cursor = 'text';
        
        quickBetButtons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        });
        
        // Crash animation
        airplane.classList.remove('flying');
        airplane.classList.add('crashed');
        
        // Show explosion and debris
        setTimeout(() => {
            explosion.style.display = 'block';
            explosion.style.left = airplane.style.left;
            explosion.style.top = (100 - parseFloat(airplane.style.bottom)) + '%';
            
            debris.style.display = 'block';
        }, 400);
        
        document.getElementById('crashMultiplier').textContent = 'CRASHED!';
        document.getElementById('crashMultiplier').style.color = 'var(--error)';
        document.getElementById('crashLoss').textContent = `💥 CRASHED bei ${crashPoint}x!`;
        document.getElementById('crashLoss').classList.add('show');
        
        // Reset after 2 seconds
        setTimeout(() => {
            airplane.classList.remove('crashed');
            airplane.style.bottom = "150px";
            airplane.style.opacity = '1';
            explosion.style.display = 'none';
            debris.style.display = 'none';
            
            // Reset ground position
            const ground = document.getElementById('ground');
            ground.style.transform = 'translateY(0)';
            
            // Reset meters
            document.getElementById('altitudeValue').textContent = '0.0km';
            document.getElementById('speedValue').textContent = '0.00x';
            
            document.getElementById('crashMultiplier').textContent = '0.00x';
            document.getElementById('crashMultiplier').style.color = 'var(--success)';
            
            // Hide loss message
            document.getElementById('crashLoss').classList.remove('show');
        }, 2000);
    }
    
    // ========== BLACKJACK GAME ==========
    let blackjackGame = null;
    
    function setBlackjackBet(amount) {
        document.getElementById('blackjackBet').value = amount;
        document.querySelectorAll('.blackjack-bet-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.blackjack-bet-btn').classList.add('active');
    }
    function setBlackjackQuickBet(amount) {
        document.getElementById('blackjackBet').value = amount.toFixed(2);
    }
    
    
    async function startBlackjack() {
        const bet = parseFloat(document.getElementById('blackjackBet').value);
        if (bet < 0.01 || bet > 10.00) {
            showNotification('Einsatz muss zwischen 0,01€ und 10,00€ liegen!', 'error');
            return;
        }
        
        if (bet > userBalance) {
            showNotification('Nicht genug Guthaben!', 'error');
            return;
        }
        
        try {
            const response = await fetch('/api/casino/play_blackjack.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'start', bet: bet })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                blackjackGame = data;
                
                // Render hands based on whether dealer cards are visible
                if (data.gameOver) {
                    renderBlackjackHands(data.playerHand, data.dealerHand, data.playerValue, data.dealerValue);
                } else {
                    renderBlackjackHands(data.playerHand, data.dealerHand, data.playerValue, null);
                }
                
                // Check for immediate result (Blackjack)
                if (data.gameOver) {
                    // Game ended immediately
                    handleBlackjackResult(data);
                } else {
                    // Show action buttons for normal play
                    document.getElementById('blackjackActions').style.display = 'flex';
                    document.getElementById('blackjackStart').style.display = 'none';
                }
            } else {
                showNotification(data.error, 'error');
            }
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }
    
    async function blackjackHit() {
        try {
            const response = await fetch('/api/casino/play_blackjack.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'hit' })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                renderBlackjackHands(data.playerHand, blackjackGame.dealerHand, data.playerValue, null);
                
                if (data.result === 'bust') {
                    setTimeout(() => blackjackStand(), 1000);
                }
            }
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }
    
    async function blackjackStand() {
        try {
            const response = await fetch('/api/casino/play_blackjack.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'stand' })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                renderBlackjackHands(data.playerHand, data.dealerHand, data.playerValue, data.dealerValue);
                handleBlackjackResult(data);
            }
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }
    
    async function blackjackDouble() {
        try {
            const response = await fetch('/api/casino/play_blackjack.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'double' })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                renderBlackjackHands(data.playerHand, data.dealerHand, data.playerValue, data.dealerValue);
                handleBlackjackResult(data);
            }
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }
    
    function renderBlackjackHands(playerHand, dealerHand, playerValue, dealerValue) {
        const playerCardsEl = document.getElementById('blackjackPlayerCards');
        const dealerCardsEl = document.getElementById('blackjackDealerCards');
        const playerValueEl = document.getElementById('blackjackPlayerValue');
        const dealerValueEl = document.getElementById('blackjackDealerValue');
        
        playerCardsEl.innerHTML = playerHand.map(card => `
            <div class="bj-card">
                <div class="bj-card-rank">${card.rank}</div>
                <div class="bj-card-suit" style="color: ${card.suit === '♥' || card.suit === '♦' ? '#ef4444' : '#000'};">${card.suit}</div>
            </div>
        `).join('');
        
        dealerCardsEl.innerHTML = dealerHand.map((card, i) => {
            if (dealerValue === null && i > 0) {
                return '<div class="bj-card bj-card-back">🂠</div>';
            }
            return `
                <div class="bj-card">
                    <div class="bj-card-rank">${card.rank}</div>
                    <div class="bj-card-suit" style="color: ${card.suit === '♥' || card.suit === '♦' ? '#ef4444' : '#000'};">${card.suit}</div>
                </div>
            `;
        }).join('');
        
        playerValueEl.textContent = playerValue;
        dealerValueEl.textContent = dealerValue !== null ? dealerValue : '?';
    }
    
    function handleBlackjackResult(data) {
        const resultEl = document.getElementById('blackjackResult');
        
        let resultText = '';
        let resultColor = '';
        let profitText = '';
        
        // Calculate actual profit/loss for display
        const actualProfit = data.profit - (data.result === 'push' ? 0 : 0);
        
        if (data.result === 'blackjack') {
            resultText = '🎉 BLACKJACK! 🎉';
            resultColor = '#f59e0b';
            profitText = `Gewinn: +${(data.winAmount).toFixed(2)}€ (Auszahlung: ${data.winAmount.toFixed(2)}€)`;
        } else if (data.result === 'win' || data.result === 'dealer_bust') {
            resultText = data.result === 'dealer_bust' ? '✅ DEALER BUST!' : '✅ GEWONNEN!';
            resultColor = '#10b981';
            profitText = `Gewinn: +${(data.winAmount).toFixed(2)}€`;
        } else if (data.result === 'push') {
            resultText = '🤝 UNENTSCHIEDEN';
            resultColor = '#fbbf24';
            profitText = 'Einsatz zurück';
        } else if (data.result === 'bust' || data.result === 'lose' || data.result === 'dealer_blackjack') {
            resultText = data.result === 'bust' ? '💥 ÜBERKAUFT!' : (data.result === 'dealer_blackjack' ? '🃏 DEALER BLACKJACK' : '❌ VERLOREN');
            resultColor = '#ef4444';
            profitText = `Verlust: -${(data.winAmount || 0).toFixed(2)}€`;
        }
        
        resultEl.innerHTML = `
            <div style="font-size: 2.5rem; font-weight: 900; color: ${resultColor}; text-shadow: 0 0 20px ${resultColor}; animation: winPulse 0.5s ease-in-out 3;">
                ${resultText}
                <div style="font-size: 1.25rem; margin-top: 12px; opacity: 0.9;">${profitText}</div>
            </div>
        `;
        
        updateAllBalances(data.newBalance);
        
        // Hide action buttons, show start button
        document.getElementById('blackjackActions').style.display = 'none';
        document.getElementById('blackjackStart').style.display = 'block';
        
        // Clear result after 5 seconds
        setTimeout(() => {
            resultEl.innerHTML = '';
        }, 5000);
    }
    
    // Close modals on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.game-modal').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = 'auto';
        }
    });
    
    // ========== MINES GAME ==========
    let minesGame = {
        active: false,
        bet: 0,
        mines: 3,
        revealed: [],
        grid: []
    };

    function setMinesBet(amount) {
        document.getElementById('minesBet').value = amount;
        document.querySelectorAll('#minesModal .quick-bet-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
    }

    function createMinesGrid() {
        const grid = document.getElementById('minesGrid');
        grid.innerHTML = '';
        
        for (let i = 0; i < 25; i++) {
            const tile = document.createElement('div');
            tile.id = `mine-tile-${i}`;
            tile.style.cssText = `
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
                overflow: hidden;
            `;
            
            tile.innerHTML = '<div style="opacity: 0;">?</div>';
            
            tile.addEventListener('mouseenter', function() {
                if (minesGame.active && !minesGame.revealed.includes(i)) {
                    this.style.transform = 'scale(1.05)';
                    this.style.borderColor = 'var(--accent)';
                    this.style.boxShadow = '0 8px 30px rgba(139, 92, 246, 0.5)';
                }
            });
            
            tile.addEventListener('mouseleave', function() {
                if (minesGame.active && !minesGame.revealed.includes(i)) {
                    this.style.transform = 'scale(1)';
                    this.style.borderColor = 'rgba(139, 92, 246, 0.4)';
                    this.style.boxShadow = 'none';
                }
            });
            
            tile.addEventListener('click', () => revealMinesTile(i));
            
            grid.appendChild(tile);
        }
    }

    async function startMines() {
        const bet = parseFloat(document.getElementById('minesBet').value);
        const mines = parseInt(document.getElementById('minesCount').value);
        
        if (bet < 0.10 || bet > userBalance) {
            showNotification('Ungültiger Einsatz!', 'error');
            return;
        }
        
        if (mines < 1 || mines > 24) {
            showNotification('Wähle 1-24 Minen!', 'error');
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
                minesGame = {
                    active: true,
                    bet: data.bet_amount,
                    mines: data.mines,
                    revealed: [],
                    grid: []
                };
                
                userBalance -= bet;
                updateBalance();
                
                document.getElementById('minesConfig').style.display = 'none';
                document.getElementById('minesStats').style.display = 'block';
                document.getElementById('minesCashoutBtn').style.display = 'none';
                
                createMinesGrid();
                updateMinesStats(0, 1.0, 0);
                
                showNotification('Spiel gestartet! Finde die Diamanten!', 'success');
            } else {
                showNotification(data.error, 'error');
            }
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }

    async function revealMinesTile(position) {
        if (!minesGame.active || minesGame.revealed.includes(position)) {
            return;
        }
        
        try {
            const response = await fetch('/api/casino/play_mines.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reveal', position })
            });
            
            const data = await response.json();
            const tile = document.getElementById(`mine-tile-${position}`);
            
            if (data.status === 'mine_hit') {
                // BOOM - Hit a mine
                tile.innerHTML = '💣';
                tile.style.background = 'linear-gradient(135deg, #ef4444, #b91c1c)';
                tile.style.borderColor = '#ef4444';
                tile.style.animation = 'mineExplode 0.5s ease-out';
                
                minesGame.active = false;
                minesGame.revealed.push(position);
                
                // Show all mines after delay
                setTimeout(() => {
                    data.mine_positions.forEach(pos => {
                        if (pos !== position) {
                            const mineTile = document.getElementById(`mine-tile-${pos}`);
                            mineTile.innerHTML = '💣';
                            mineTile.style.background = 'linear-gradient(135deg, rgba(239, 68, 68, 0.3), rgba(185, 28, 28, 0.3))';
                            mineTile.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                        }
                    });
                    
                    showMinesResult(false, 0, data.mine_positions.length);
                }, 800);
                
            } else if (data.status === 'safe') {
                // Safe tile - diamond found
                tile.innerHTML = '💎';
                tile.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                tile.style.borderColor = '#10b981';
                tile.style.animation = 'diamondSparkle 0.6s ease-out';
                tile.style.cursor = 'default';
                
                minesGame.revealed.push(position);
                
                updateMinesStats(data.revealed_count, data.current_multiplier, data.potential_win);
                
                // Show cashout button if at least 1 tile revealed
                if (data.revealed_count > 0) {
                    document.getElementById('minesCashoutBtn').style.display = 'block';
                    document.getElementById('cashoutAmount').textContent = data.potential_win.toFixed(2) + '€';
                }
                
                // Auto cashout if all safe tiles revealed
                if (data.all_revealed) {
                    setTimeout(() => cashoutMines(), 500);
                }
                
            } else {
                showNotification(data.error || 'Fehler aufgetreten', 'error');
            }
            
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }

    async function cashoutMines() {
        if (!minesGame.active) return;
        
        try {
            const response = await fetch('/api/casino/play_mines.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cashout' })
            });
            
            const data = await response.json();
            
            if (data.status === 'cashout') {
                minesGame.active = false;
                
                // Update balance
                userBalance += data.win_amount;
                updateBalance();
                
                // Show all mines
                data.mine_positions.forEach(pos => {
                    if (!minesGame.revealed.includes(pos)) {
                        const mineTile = document.getElementById(`mine-tile-${pos}`);
                        mineTile.innerHTML = '💣';
                        mineTile.style.background = 'linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2))';
                        mineTile.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                    }
                });
                
                showMinesResult(true, data.win_amount, data.revealed, data.multiplier);
            } else {
                showNotification(data.error, 'error');
            }
            
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }

    function updateMinesStats(revealed, multiplier, potential) {
        document.getElementById('minesRevealed').textContent = revealed;
        document.getElementById('minesMultiplier').textContent = multiplier.toFixed(3) + 'x';
        document.getElementById('minesPotential').textContent = potential.toFixed(2) + '€';
    }

    function showMinesResult(won, amount, revealed, multiplier = 0) {
        const resultDiv = document.getElementById('minesResult');
        
        if (won) {
            const profit = amount - minesGame.bet;
            resultDiv.innerHTML = `
                <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); 
                            padding: 24px; border-radius: 16px; border: 2px solid var(--success); text-align: center; animation: resultSlideIn 0.5s ease-out;">
                    <div style="font-size: 4rem; margin-bottom: 16px;">🎉</div>
                    <div style="font-size: 2rem; font-weight: 900; color: var(--success); margin-bottom: 12px;">Gewinn!</div>
                    <div style="font-size: 1.25rem; margin-bottom: 8px;">
                        <span style="color: var(--text-secondary);">Multiplikator:</span> 
                        <span style="color: var(--accent); font-weight: 800;">${multiplier.toFixed(3)}x</span>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);">+${profit.toFixed(2)}€</div>
                    <div style="margin-top: 16px; font-size: 0.875rem; color: var(--text-secondary);">
                        ${revealed} sichere Felder aufgedeckt!
                    </div>
                    <button onclick="resetMines()" style="margin-top: 20px; padding: 12px 32px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer; font-size: 1rem;">
                        ✨ Neues Spiel
                    </button>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2)); 
                            padding: 24px; border-radius: 16px; border: 2px solid var(--error); text-align: center; animation: resultSlideIn 0.5s ease-out;">
                    <div style="font-size: 4rem; margin-bottom: 16px;">💥</div>
                    <div style="font-size: 2rem; font-weight: 900; color: var(--error); margin-bottom: 12px;">BOOM!</div>
                    <div style="font-size: 1.125rem; color: var(--text-secondary); margin-bottom: 8px;">
                        Mine getroffen!
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--error);">-${minesGame.bet.toFixed(2)}€</div>
                    <div style="margin-top: 16px; font-size: 0.875rem; color: var(--text-secondary);">
                        ${minesGame.revealed.length} Felder aufgedeckt
                    </div>
                    <button onclick="resetMines()" style="margin-top: 20px; padding: 12px 32px; background: linear-gradient(135deg, var(--accent), #a855f7); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer; font-size: 1rem;">
                        🔄 Nochmal versuchen
                    </button>
                </div>
            `;
        }
        
        document.getElementById('minesCashoutBtn').style.display = 'none';
    }

    function resetMines() {
        minesGame = { active: false, bet: 0, mines: 3, revealed: [], grid: [] };
        document.getElementById('minesConfig').style.display = 'block';
        document.getElementById('minesStats').style.display = 'none';
        document.getElementById('minesCashoutBtn').style.display = 'none';
        document.getElementById('minesResult').innerHTML = '';
        createMinesGrid();
    }

    // Add animations
    const minesStyles = document.createElement('style');
    minesStyles.textContent = `
        @keyframes mineExplode {
            0% { transform: scale(1); }
            50% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        @keyframes diamondSparkle {
            0%, 100% { transform: scale(1); filter: brightness(1); }
            50% { transform: scale(1.2); filter: brightness(1.5); }
        }
        @keyframes resultSlideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(minesStyles);
    
    // ========== MULTIPLAYER LOBBY ==========
    let multiplayerRefreshInterval = null;
    
    async function loadMultiplayerTables() {
        try {
            const response = await fetch('/api/casino/get_multiplayer_tables.php');
            const data = await response.json();
            
            const grid = document.getElementById('multiplayerTablesGrid');
            
            if (data.status === 'success' && data.tables.length > 0) {
                grid.innerHTML = data.tables.map(table => `
                    <div class="multiplayer-table-card" style="
                        background: var(--bg-secondary);
                        border: 2px solid var(--border);
                        border-radius: 16px;
                        padding: 20px;
                        transition: all 0.3s;
                    " onmouseover="this.style.transform='translateY(-4px)'; this.style.borderColor='var(--accent)'" 
                       onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='var(--border)'">
                        <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 12px;">
                            <div style="flex: 1;">
                                <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">
                                    ${table.table_name}
                                </div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Host: ${table.host_name}
                                </div>
                            </div>
                            <div style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: white; padding: 4px 12px; border-radius: 8px; font-weight: 700; font-size: 0.875rem;">
                                ${table.game_type.toUpperCase()}
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 16px 0;">
                            <div style="background: var(--bg-primary); padding: 8px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">Min/Max</div>
                                <div style="font-size: 0.95rem; font-weight: 700; color: var(--accent);">${table.min_bet}€ - ${table.max_bet}€</div>
                            </div>
                            <div style="background: var(--bg-primary); padding: 8px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">Spieler</div>
                                <div style="font-size: 0.95rem; font-weight: 700; color: #10b981;">${table.current_players}/${table.max_players}</div>
                            </div>
                        </div>
                        
                        <button onclick="joinMultiplayerTable(${table.id}, ${table.min_bet}, ${table.max_bet})" style="
                            width: 100%;
                            padding: 12px;
                            background: linear-gradient(135deg, #10b981, #059669);
                            border: none;
                            border-radius: 12px;
                            color: white;
                            font-weight: 800;
                            cursor: pointer;
                            transition: all 0.2s;
                        " onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            🎮 Beitreten
                        </button>
                    </div>
                `).join('');
            } else {
                grid.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary); grid-column: 1 / -1;">
                        <div style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;">🎲</div>
                        <div style="font-size: 1.125rem; font-weight: 600;">Keine aktiven Tische</div>
                        <div style="font-size: 0.875rem; margin-top: 8px;">Erstelle einen neuen Tisch und lade andere ein!</div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Failed to load tables:', error);
        }
    }
    
    function showCreateTableModal() {
        const modal = document.createElement('div');
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 99999; display: flex; align-items: center; justify-content: center;';
        
        modal.innerHTML = `
            <div style="background: var(--bg-primary); padding: 40px; border-radius: 20px; max-width: 500px; width: 90%;">
                <h2 style="font-size: 2rem; font-weight: 900; margin-bottom: 24px; text-align: center;">🎲 Tisch erstellen</h2>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">Tischname</label>
                    <input type="text" id="tableName" value="${'<?= $name ?>'}'s Tisch" style="width: 100%; padding: 12px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1rem;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">Min. Einsatz</label>
                        <input type="number" id="minBet" value="1" min="0.01" max="10.00" step="0.01" style="width: 100%; padding: 12px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary);">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">Max. Einsatz</label>
                        <input type="number" id="maxBet" value="50" min="0.01" max="10.00" step="0.01" style="width: 100%; padding: 12px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary);">
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">Max. Spieler</label>
                    <input type="number" id="maxPlayers" value="4" min="0.01" max="10.00" step="0.01" style="width: 100%; padding: 12px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary);">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <button onclick="this.closest('[style*=fixed]').remove()" style="padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
                        Abbrechen
                    </button>
                    <button onclick="createMultiplayerTable()" style="padding: 14px; background: linear-gradient(135deg, #f59e0b, #ef4444); border: none; border-radius: 12px; color: white; font-weight: 800; cursor: pointer;">
                        Erstellen
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    async function createMultiplayerTable() {
        const tableName = document.getElementById('tableName').value;
        const minBet = parseFloat(document.getElementById('minBet').value);
        const maxBet = parseFloat(document.getElementById('maxBet').value);
        const maxPlayers = parseInt(document.getElementById('maxPlayers').value);
        
        try {
            const response = await fetch('/api/casino/create_multiplayer_table.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    table_name: tableName,
                    min_bet: minBet,
                    max_bet: maxBet,
                    max_players: maxPlayers,
                    game_type: 'blackjack'
                })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                showNotification('Tisch erstellt! Warte auf Spieler...', 'success');
                document.querySelector('[style*="fixed"]').remove();
                loadMultiplayerTables();
                // TODO: Open waiting room
            } else {
                showNotification(data.error, 'error');
            }
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }
    
    function joinMultiplayerTable(tableId, minBet, maxBet) {
        const modal = document.createElement('div');
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 99999; display: flex; align-items: center; justify-content: center;';
        
        modal.innerHTML = `
            <div style="background: var(--bg-primary); padding: 40px; border-radius: 20px; max-width: 400px; width: 90%;">
                <h2 style="font-size: 1.75rem; font-weight: 900; margin-bottom: 24px; text-align: center;">💰 Einsatz wählen</h2>
                
                <div style="text-align: center; margin-bottom: 20px; padding: 16px; background: var(--bg-secondary); border-radius: 12px;">
                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 4px;">Erlaubter Bereich</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--accent);">${minBet}€ - ${maxBet}€</div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <input type="number" id="joinBet" value="${minBet}" min="${minBet}" max="${maxBet}" step="0.5" style="width: 100%; padding: 16px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 12px; color: var(--text-primary); font-size: 1.25rem; text-align: center; font-weight: 700;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <button onclick="this.closest('[style*=fixed]').remove()" style="padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
                        Abbrechen
                    </button>
                    <button onclick="confirmJoinTable(${tableId})" style="padding: 14px; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 12px; color: white; font-weight: 800; cursor: pointer;">
                        Beitreten
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    async function confirmJoinTable(tableId) {
        const bet = parseFloat(document.getElementById('joinBet').value);
        
        try {
            const response = await fetch('/api/casino/join_multiplayer_table.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    table_id: tableId,
                    bet_amount: bet
                })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                showNotification('Erfolgreich beigetreten!', 'success');
                document.querySelector('[style*="fixed"]').remove();
                loadMultiplayerTables();
                // TODO: Open game table
            } else {
                showNotification(data.error, 'error');
            }
        } catch (error) {
            showNotification('Fehler: ' + error.message, 'error');
        }
    }
    
    // Initialize wheel on page load
    window.addEventListener('DOMContentLoaded', () => {
        initPlinko();
        
        // Load multiplayer tables
        loadMultiplayerTables();
        multiplayerRefreshInterval = setInterval(loadMultiplayerTables, 5000);
        
        // Setup game card click handlers
        const openSlotsBtn = document.getElementById('openSlotsBtn');
        const openPlinkoBtn = document.getElementById('openPlinkoBtn');
        const openCrashBtn = document.getElementById('openCrashBtn');
        const openBlackjackBtn = document.getElementById('openBlackjackBtn');
        
        if (openSlotsBtn) {
            openSlotsBtn.addEventListener('click', () => openGame('slots'));
        }
        if (openPlinkoBtn) {
            openPlinkoBtn.addEventListener('click', () => openGame('plinko'));
        }
        if (openCrashBtn) {
            openCrashBtn.addEventListener('click', () => openGame('crash'));
        }
        if (openBlackjackBtn) {
            openBlackjackBtn.addEventListener('click', () => openGame('blackjack'));
        }
        
        const openChickenBtn = document.getElementById('openChickenBtn');
        if (openChickenBtn) {
            openChickenBtn.addEventListener('click', () => openGame('chicken'));
        }
        
        const openMinesBtn = document.getElementById('openMinesBtn');
        if (openMinesBtn) {
            openMinesBtn.addEventListener('click', () => openGame('mines'));
        }
    });
    </script>
    <?php endif; // End modals for unlocked casino ?>
</body>
</html>

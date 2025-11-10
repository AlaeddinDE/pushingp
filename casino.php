<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin_user = is_admin();

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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎰 Casino – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .casino-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
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
            overflow: hidden;
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
            max-height: 95vh;
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
            max-height: 98vh;
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
        
        /* Slots Specific */
        .slots-reels {
            display: flex;
            gap: 24px;
            justify-content: center;
            margin: 40px 0;
            perspective: 1000px;
        }
        
        .slot-reel {
            width: 140px;
            height: 140px;
            background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            border: 4px solid var(--accent);
            position: relative;
            overflow: hidden;
            box-shadow: 
                inset 0 0 30px rgba(0,0,0,0.5),
                0 10px 30px rgba(0,0,0,0.4),
                0 0 20px rgba(139, 92, 246, 0.3);
            transition: all 0.3s ease;
        }
        
        .slot-reel::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255,255,255,0.1) 50%,
                transparent 70%
            );
            transform: rotate(45deg);
            animation: shine 3s linear infinite;
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }
        
        .slot-reel.spinning {
            animation: 
                slotSpin 0.08s linear infinite,
                slotShake 0.15s ease-in-out infinite,
                slotGlow 0.2s ease-in-out infinite;
            border-color: #10b981;
        }
        
        .slot-reel.stopping {
            animation: 
                slotBounce 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards,
                slotFlash 0.3s ease-out;
        }
        
        @keyframes slotSpin {
            0% { 
                transform: translateY(0) rotateX(0deg);
            }
            25% {
                transform: translateY(-25%) rotateX(90deg);
            }
            50% {
                transform: translateY(-50%) rotateX(180deg);
            }
            75% {
                transform: translateY(-75%) rotateX(270deg);
            }
            100% { 
                transform: translateY(-100%) rotateX(360deg);
            }
        }
        
        @keyframes slotShake {
            0%, 100% {
                transform: translateX(0) scale(1);
            }
            25% {
                transform: translateX(-3px) scale(1.02);
            }
            75% {
                transform: translateX(3px) scale(0.98);
            }
        }
        
        @keyframes slotGlow {
            0%, 100% {
                box-shadow: 
                    inset 0 0 30px rgba(0,0,0,0.5),
                    0 10px 30px rgba(0,0,0,0.4),
                    0 0 20px rgba(16, 185, 129, 0.5);
            }
            50% {
                box-shadow: 
                    inset 0 0 30px rgba(0,0,0,0.5),
                    0 10px 30px rgba(0,0,0,0.4),
                    0 0 40px rgba(16, 185, 129, 1);
            }
        }
        
        @keyframes slotBounce {
            0% {
                transform: scale(1) rotateX(0deg);
            }
            30% {
                transform: scale(1.15) rotateX(10deg);
            }
            50% {
                transform: scale(0.95) rotateX(-5deg);
            }
            70% {
                transform: scale(1.05) rotateX(3deg);
            }
            100% {
                transform: scale(1) rotateX(0deg);
            }
        }
        
        @keyframes slotFlash {
            0%, 100% {
                background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            }
            50% {
                background: linear-gradient(145deg, rgba(16, 185, 129, 0.3), rgba(16, 185, 129, 0.2));
            }
        }
        
        @keyframes spin {
            0% { transform: translateY(0); }
            100% { transform: translateY(-100%); }
        }
        
        /* Wheel Specific */
        .wheel-container {
            width: 450px;
            height: 450px;
            margin: 40px auto;
            position: relative;
            filter: drop-shadow(0 10px 40px rgba(0,0,0,0.5));
        }
        
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 12px solid transparent;
            background: 
                linear-gradient(var(--bg-primary), var(--bg-primary)) padding-box,
                linear-gradient(45deg, #8b5cf6, #10b981, #eab308, #ef4444, #8b5cf6) border-box;
            position: relative;
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            background-image: conic-gradient(
                #ef4444 0deg 60deg,
                #eab308 60deg 120deg,
                #10b981 120deg 180deg,
                #3b82f6 180deg 240deg,
                #8b5cf6 240deg 300deg,
                #f59e0b 300deg 360deg
            );
            box-shadow: 
                inset 0 0 50px rgba(0,0,0,0.3),
                0 0 30px rgba(139, 92, 246, 0.5),
                0 0 60px rgba(139, 92, 246, 0.3);
            animation: wheelIdle 4s ease-in-out infinite;
        }
        
        @keyframes wheelIdle {
            0%, 100% {
                box-shadow: 
                    inset 0 0 50px rgba(0,0,0,0.3),
                    0 0 30px rgba(139, 92, 246, 0.5),
                    0 0 60px rgba(139, 92, 246, 0.3);
            }
            50% {
                box-shadow: 
                    inset 0 0 50px rgba(0,0,0,0.3),
                    0 0 40px rgba(16, 185, 129, 0.7),
                    0 0 80px rgba(16, 185, 129, 0.5);
            }
        }
        
        .wheel.spinning {
            animation: wheelSpin 4s cubic-bezier(0.17, 0.67, 0.12, 0.99) forwards,
                       wheelGlow 0.3s ease-in-out infinite;
        }
        
        @keyframes wheelSpin {
            0% {
                transform: rotate(0deg) scale(1);
            }
            20% {
                transform: rotate(720deg) scale(1.05);
            }
            40% {
                transform: rotate(1440deg) scale(1);
            }
            60% {
                transform: rotate(2160deg) scale(1.05);
            }
            80% {
                transform: rotate(2880deg) scale(1);
            }
            100% {
                transform: rotate(3600deg) scale(1);
            }
        }
        
        @keyframes wheelGlow {
            0%, 100% {
                filter: brightness(1) drop-shadow(0 0 20px rgba(139, 92, 246, 0.5));
            }
            50% {
                filter: brightness(1.3) drop-shadow(0 0 40px rgba(139, 92, 246, 1));
            }
        }
        
        .wheel::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, #8b5cf6, #6d28d9);
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.8);
            z-index: 10;
        }
        
        .wheel::after {
            content: '🎯';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2.5rem;
            z-index: 11;
            animation: centerSpin 2s linear infinite reverse;
        }
        
        @keyframes centerSpin {
            from {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }
        
        .wheel-pointer {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 25px solid transparent;
            border-right: 25px solid transparent;
            border-top: 50px solid #8b5cf6;
            z-index: 10;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.5));
            animation: pointerBounce 0.6s ease-in-out infinite;
        }
        
        @keyframes pointerBounce {
            0%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            50% {
                transform: translateX(-50%) translateY(-5px);
            }
        }
        
        /* Crash Specific */
        .crash-graph {
            width: 100%;
            height: 300px;
            background: var(--bg-secondary);
            border-radius: 12px;
            position: relative;
            overflow: hidden;
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
            height: 600px;
            background: linear-gradient(180deg, 
                #87CEEB 0%,    /* Sky blue (earth) */
                #4A90E2 20%,   /* Deep blue */
                #2C5AA0 40%,   /* Darker blue */
                #1a1a2e 70%,   /* Dark space */
                #0a0a1e 100%   /* Deep space */
            );
            border-radius: 16px;
            margin-bottom: 24px;
            overflow: hidden;
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
            opacity: 0.3;
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
        .danger-indicator {
            position: absolute;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(239, 68, 68, 0.95);
            color: white;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 900;
            font-size: 1.25rem;
            letter-spacing: 2px;
            animation: dangerPulse 0.5s ease-in-out infinite;
            border: 3px solid #fff;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.8);
            z-index: 100;
        }
        
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
    </style>

</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">PUSHING P</a>
            <nav class="nav">
                <a href="dashboard.php" class="nav-item">Dashboard</a>
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <a href="chat.php" class="nav-item">Chat</a>
                <a href="casino.php" class="nav-item" style="border-bottom: 2px solid var(--accent);">🎰 Casino</a>
                <?php if ($is_admin_user): ?>
                    <a href="admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>🎰 PUSHING P CASINO</h1>
            <p class="text-secondary">Setze dein Guthaben ein und gewinne groß!</p>
        </div>

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
            <div class="game-card" onclick="openGame('slots')">
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

            <!-- WHEEL -->
            <div class="game-card" onclick="openGame('wheel')">
                <span class="game-icon">🎪</span>
                <div class="game-title">Glücksrad</div>
                <div class="game-desc">Drehe das Rad! Bis zu 50x Multiplikator möglich!</div>
                <div class="game-stats">
                    <div class="game-stat">
                        <div class="game-stat-label">House Edge</div>
                        <div class="game-stat-value">8%</div>
                    </div>
                    <div class="game-stat">
                        <div class="game-stat-label">Max Win</div>
                        <div class="game-stat-value">50x</div>
                    </div>
                </div>
            </div>

            <!-- CRASH -->
            <div class="game-card" onclick="openGame('crash')">
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
        </div>

        <!-- Recent Big Wins -->
        <?php if (count($recent_wins) > 0): ?>
        <div class="recent-wins">
            <h2 class="section-title" style="margin-bottom: 20px;">🏆 Letzte Gewinne</h2>
            <?php foreach (array_slice($recent_wins, 0, 5) as $win): ?>
                <div class="win-item">
                    <div>
                        <div style="font-weight: 700;"><?= escape($win['name']) ?></div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?= ucfirst($win['game_type']) ?> • <?= date('d.m.Y H:i', strtotime($win['created_at'])) ?>
                        </div>
                    </div>
                    <div class="win-amount">
                        +<?= number_format($win['win_amount'] - $win['bet_amount'], 2, ',', '.') ?> €
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php endif; // End casino_locked check ?>
    </div>

    <?php if (!$casino_locked): ?>
    <!-- SLOTS MODAL -->
    <div class="game-modal" id="slotsModal">
        <div class="game-modal-content">
            <button class="modal-close" onclick="closeGame('slots')">×</button>
            <h2 style="font-size: 2rem; margin-bottom: 8px;">🎰 Slot Machine</h2>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">Drei gleiche Symbole gewinnen!</p>
            
            <div class="balance-display">
                <div class="balance-label">Dein Guthaben</div>
                <div class="balance-value" id="slotsBalance"><?= number_format($balance, 2, ',', '.') ?> €</div>
            </div>

            <div class="slots-reels" id="slotsReels">
                <div class="slot-reel" id="reel1">🍒</div>
                <div class="slot-reel" id="reel2">🍋</div>
                <div class="slot-reel" id="reel3">⭐</div>
            </div>

            <div class="quick-bet-btns">
                <button class="quick-bet-btn" onclick="setSlotsBet(1)">1€</button>
                <button class="quick-bet-btn" onclick="setSlotsBet(5)">5€</button>
                <button class="quick-bet-btn" onclick="setSlotsBet(10)">10€</button>
                <button class="quick-bet-btn" onclick="setSlotsBet(25)">25€</button>
                <button class="quick-bet-btn" onclick="setSlotsBet(50)">50€</button>
            </div>

            <div class="bet-input-group">
                <input type="number" class="bet-input" id="slotsBet" value="5" min="0.5" max="50" step="0.5">
                <button class="bet-btn" id="slotsSpin" onclick="spinSlots()">SPIN</button>
            </div>

            <div class="win-message" id="slotsWin"></div>
            <div class="loss-message" id="slotsLoss"></div>

            <div style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 12px; font-size: 0.875rem;">
                <div style="font-weight: 700; margin-bottom: 12px;">💰 Auszahlungstabelle:</div>
                <div style="display: grid; gap: 8px;">
                    <div>🍒🍒🍒 / 🍋🍋🍋 / ⭐⭐⭐ = <span style="color: var(--success); font-weight: 700;">10x</span></div>
                    <div>7️⃣7️⃣7️⃣ = <span style="color: var(--success); font-weight: 700;">50x</span></div>
                    <div>💎💎💎 = <span style="color: #f59e0b; font-weight: 700;">100x JACKPOT!</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- WHEEL MODAL -->
    <div class="game-modal" id="wheelModal">
        <div class="game-modal-content">
            <button class="modal-close" onclick="closeGame('wheel')">×</button>
            <h2 style="font-size: 2rem; margin-bottom: 8px;">🎪 Glücksrad</h2>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">Dreh das Rad und gewinne!</p>
            
            <div class="balance-display">
                <div class="balance-label">Dein Guthaben</div>
                <div class="balance-value" id="wheelBalance"><?= number_format($balance, 2, ',', '.') ?> €</div>
            </div>

            <div class="wheel-container">
                <div class="wheel-pointer"></div>
                <div class="wheel" id="wheelSpin"></div>
            </div>

            <div class="quick-bet-btns">
                <button class="quick-bet-btn" onclick="setWheelBet(1)">1€</button>
                <button class="quick-bet-btn" onclick="setWheelBet(5)">5€</button>
                <button class="quick-bet-btn" onclick="setWheelBet(10)">10€</button>
                <button class="quick-bet-btn" onclick="setWheelBet(25)">25€</button>
                <button class="quick-bet-btn" onclick="setWheelBet(50)">50€</button>
            </div>

            <div class="bet-input-group">
                <input type="number" class="bet-input" id="wheelBet" value="5" min="0.5" max="50" step="0.5">
                <button class="bet-btn" id="wheelSpinBtn" onclick="spinWheel()">SPIN</button>
            </div>

            <div class="win-message" id="wheelWin"></div>
            <div class="loss-message" id="wheelLoss"></div>

            <div style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 12px; font-size: 0.875rem;">
                <div style="font-weight: 700; margin-bottom: 12px;">🎯 Multiplier:</div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                    <div><span style="color: #ef4444;">●</span> 0.5x (40%)</div>
                    <div><span style="color: #eab308;">●</span> 1x (30%)</div>
                    <div><span style="color: #10b981;">●</span> 2x (20%)</div>
                    <div><span style="color: #3b82f6;">●</span> 5x (8%)</div>
                    <div><span style="color: #8b5cf6;">●</span> 10x (1.8%)</div>
                    <div><span style="color: #f59e0b;">●</span> 50x (0.2%)</div>
                </div>
            </div>
        </div>
    </div>

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
                        <div class="balance-value" id="crashBalance" style="font-size: 1.5rem;"><?= number_format($balance, 2, ',', '.') ?> €</div>
                    </div>
                </div>

            <div class="crash-graph" id="crashGraph">
                <div class="crash-sky">
                    <!-- Stars background -->
                    <div class="stars"></div>
                    <div class="stars2"></div>
                    
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
                    <div class="shooting-star shooting-star-3"></div>
                    
                    <!-- Asteroids -->
                    <div class="asteroid" id="asteroid1" style="top: 20%; left: 80%;">☄️</div>
                    <div class="asteroid" id="asteroid2" style="top: 70%; left: 10%;">🌑</div>
                    <div class="asteroid" id="asteroid3" style="top: 45%; right: 15%;">☄️</div>
                    
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
                    
                    <!-- Easter Eggs - repositioned for vertical -->
                    <div class="easter-egg" id="egg1" data-trigger="1.5" style="display: none; left: 20%; font-size: 2.5rem;">
                        ☁️
                    </div>
                    <div class="easter-egg" id="egg2" data-trigger="2.0" style="display: none; right: 20%; font-size: 2.5rem;">
                        🐦
                    </div>
                    <div class="easter-egg" id="egg3" data-trigger="3.0" style="display: none; left: 15%; font-size: 3rem;">
                        🛩️
                    </div>
                    <div class="easter-egg" id="egg4" data-trigger="4.0" style="display: none; right: 15%; font-size: 3rem;">
                        🦅
                    </div>
                    <div class="easter-egg" id="egg5" data-trigger="5.0" style="display: none; left: 50%; transform: translateX(-50%); font-size: 3.5rem;">
                        ✈️
                    </div>
                    <div class="easter-egg" id="egg6" data-trigger="7.0" style="display: none; left: 25%; font-size: 3.5rem;">
                        🛸
                    </div>
                    <div class="easter-egg" id="egg7" data-trigger="9.0" style="display: none; right: 25%; font-size: 4rem;">
                        👽
                    </div>
                    <div class="easter-egg" id="egg8" data-trigger="12.0" style="display: none; left: 30%; font-size: 4.5rem;">
                        🌟
                    </div>
                    <div class="easter-egg" id="egg9" data-trigger="15.0" style="display: none; left: 50%; transform: translateX(-50%); font-size: 6rem;">
                        👑
                    </div>
                </div>
                <div class="crash-multiplier" id="crashMultiplier">0.00x</div>
                
                <!-- Warning indicator -->
                <div class="danger-indicator" id="dangerIndicator" style="display: none;">
                    ⚠️ DANGER ZONE ⚠️
                </div>
            </div>


            <!-- Control Panel - Kompakt -->
            <div style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(16, 185, 129, 0.1)); 
                        border: 2px solid var(--border); 
                        border-radius: 16px; 
                        padding: 16px;">
                
                <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                    <div class="quick-bet-btns" style="display: flex; gap: 8px; flex-wrap: wrap; flex: 1;">
                        <button class="quick-bet-btn" onclick="setCrashBet(1)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #8b5cf6; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);">
                            💰 1€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(5)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #10b981; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);">
                            💵 5€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(10)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #f59e0b; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);">
                            💸 10€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(25)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #3b82f6; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);">
                            💎 25€
                        </button>
                        <button class="quick-bet-btn" onclick="setCrashBet(50)" style="flex: 1; min-width: 60px; padding: 10px 12px; background: linear-gradient(135deg, #1f2937, #374151); border: 2px solid #ef4444; transition: all 0.3s; font-size: 0.95rem; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);">
                            👑 50€
                        </button>
                    </div>
                </div>

                <div class="bet-input-group" style="display: flex; gap: 12px; align-items: stretch;">
                    <div style="flex: 1; position: relative;">
                        <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 1rem; color: var(--accent); font-weight: 900;">💵</div>
                        <input type="number" class="bet-input" id="crashBet" value="5" min="0.5" max="50" step="0.5" 
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

    <script>
    let userBalance = parseFloat(<?= $balance ?>) || 0;
    
    function openGame(game) {
        document.getElementById(game + 'Modal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeGame(game) {
        document.getElementById(game + 'Modal').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    // SLOTS GAME
    const slotSymbols = ['🍒', '🍋', '⭐', '7️⃣', '💎'];
    let slotsSpinning = false;
    
    function setSlotsBet(amount) {
        document.getElementById('slotsBet').value = amount;
    }
    
    async function spinSlots() {
        if (slotsSpinning) return;
        
        const bet = parseFloat(document.getElementById('slotsBet').value);
        if (bet < 0.5 || bet > 50) {
            alert('Einsatz muss zwischen 0.50€ und 50€ liegen!');
            return;
        }
        
        if (bet > userBalance) {
            alert('Nicht genug Guthaben!');
            return;
        }
        
        slotsSpinning = true;
        document.getElementById('slotsSpin').disabled = true;
        document.getElementById('slotsWin').classList.remove('show');
        document.getElementById('slotsLoss').classList.remove('show');
        
        // Spin animation
        const reels = ['reel1', 'reel2', 'reel3'];
        reels.forEach(id => {
            document.getElementById(id).classList.add('spinning');
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
                        reel.classList.remove('spinning');
                        reel.classList.add('stopping');
                        reel.textContent = data.result[index];
                        
                        // Remove stopping class after animation
                        setTimeout(() => {
                            reel.classList.remove('stopping');
                        }, 600);
                    }, 1500 + (index * 400)); // Stagger: 1.5s, 1.9s, 2.3s
                });
                
                // Update balance after all reels stopped
                setTimeout(() => {
                    userBalance = parseFloat(data.new_balance) || 0;
                    document.getElementById('slotsBalance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
                    
                    if (data.win_amount > 0) {
                        document.getElementById('slotsWin').textContent = `🎉 Gewonnen: ${data.win_amount.toFixed(2)}€ (${data.multiplier}x)`;
                        document.getElementById('slotsWin').classList.add('show');
                    } else {
                        document.getElementById('slotsLoss').textContent = `Verloren: ${bet.toFixed(2)}€`;
                        document.getElementById('slotsLoss').classList.add('show');
                    }
                    
                    setTimeout(() => {
                        document.getElementById('slotsWin').classList.remove('show');
                        document.getElementById('slotsLoss').classList.remove('show');
                    }, 3000);
                    
                    slotsSpinning = false;
                    document.getElementById('slotsSpin').disabled = false;
                }, 2700); // After all reels stopped (1500 + 400*3 = 2700ms)
            } else {
                alert('Fehler: ' + data.error);
                slotsSpinning = false;
                document.getElementById('slotsSpin').disabled = false;
            }
        } catch (error) {
            alert('Verbindungsfehler: ' + error.message);
            slotsSpinning = false;
            document.getElementById('slotsSpin').disabled = false;
        }
    }
    
    // WHEEL GAME
    let wheelSpinning = false;
    
    function setWheelBet(amount) {
        document.getElementById('wheelBet').value = amount;
    }
    
    async function spinWheel() {
        if (wheelSpinning) return;
        
        const bet = parseFloat(document.getElementById('wheelBet').value);
        if (bet < 0.5 || bet > 50) {
            alert('Einsatz muss zwischen 0.50€ und 50€ liegen!');
            return;
        }
        
        if (bet > userBalance) {
            alert('Nicht genug Guthaben!');
            return;
        }
        
        wheelSpinning = true;
        document.getElementById('wheelSpinBtn').disabled = true;
        document.getElementById('wheelWin').classList.remove('show');
        document.getElementById('wheelLoss').classList.remove('show');
        
        try {
            const response = await fetch('/api/casino/play_wheel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bet: bet })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                const wheel = document.getElementById('wheelSpin');
                const rotation = 360 * 5 + data.rotation; // 5 full spins + result
                wheel.style.transform = `rotate(${rotation}deg)`;
                
                setTimeout(() => {
                    userBalance = parseFloat(data.new_balance) || 0;
                    document.getElementById('wheelBalance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
                    
                    if (data.multiplier >= 1) {
                        document.getElementById('wheelWin').textContent = `🎉 ${data.multiplier}x! Gewonnen: ${data.win_amount.toFixed(2)}€`;
                        document.getElementById('wheelWin').classList.add('show');
                    } else {
                        document.getElementById('wheelLoss').textContent = `😢 ${data.multiplier}x - Verloren: ${(bet - data.win_amount).toFixed(2)}€`;
                        document.getElementById('wheelLoss').classList.add('show');
                    }
                    
                    wheelSpinning = false;
                    document.getElementById('wheelSpinBtn').disabled = false;
                }, 3000);
            } else {
                alert('Fehler: ' + data.error);
                wheelSpinning = false;
                document.getElementById('wheelSpinBtn').disabled = false;
            }
        } catch (error) {
            alert('Verbindungsfehler: ' + error.message);
            wheelSpinning = false;
            document.getElementById('wheelSpinBtn').disabled = false;
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
        if (bet < 0.5 || bet > 50) {
            alert('Einsatz muss zwischen 0.50€ und 50€ liegen!');
            return;
        }
        
        if (bet > userBalance) {
            alert('Nicht genug Guthaben!');
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
                
                // Reset rocket and explosion
                const airplane = document.getElementById('crashAirplane');
                const explosion = document.getElementById('crashExplosion');
                const ground = document.getElementById('ground');
                const crashGraph = document.getElementById('crashGraph');
                const dangerIndicator = document.getElementById('dangerIndicator');
                
                airplane.classList.remove('crashed');
                airplane.classList.add('flying');
                airplane.style.opacity = '1';
                airplane.style.bottom = '150px';
                explosion.style.display = 'none';
                dangerIndicator.style.display = 'none';
                ground.style.transform = 'translateY(0)';
                crashGraph.className = 'crash-graph';
                
                // Hide all easter eggs
                for (let i = 1; i <= 9; i++) {
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
                    
                    // Show easter eggs at specific multipliers
                    for (let i = 1; i <= 9; i++) {
                        const egg = document.getElementById('egg' + i);
                        if (egg) {
                            const trigger = parseFloat(egg.getAttribute('data-trigger'));
                            if (crashMultiplier >= trigger && egg.style.display === 'none') {
                                egg.style.display = 'block';
                                egg.style.bottom = (200 + (i * 30) - groundOffset/2) + 'px';
                            }
                        }
                    }
                    
                    // Show danger indicator when close to crash
                    if (crashMultiplier >= crashPoint * 0.8) {
                        dangerIndicator.style.display = 'block';
                    }
                    
                    if (crashMultiplier >= crashPoint) {
                        crashGame();
                    }
                }, 100);
                
                userBalance = parseFloat(data.balance) || 0;
                document.getElementById('crashBalance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
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
                userBalance = parseFloat(data.new_balance) || 0;
                document.getElementById('crashBalance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
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
                }, 3000);
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
        const dangerIndicator = document.getElementById('dangerIndicator');
        
        // Hide danger indicator
        dangerIndicator.style.display = 'none';
        
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
        
        // Reset after 3 seconds
        setTimeout(() => {
            airplane.classList.remove('crashed');
            airplane.style.bottom = "150px";
            // Rocket stays centered
            airplane.style.opacity = '1';
            explosion.style.display = 'none';
            debris.style.display = 'none';
            
            // Reset meters
            document.getElementById('altitudeValue').textContent = '0m';
            document.getElementById('speedValue').textContent = '0 km/h';
            
            document.getElementById('crashMultiplier').textContent = '0.00x';
            document.getElementById('crashMultiplier').style.color = 'var(--success)';
            document.getElementById('crashStartBtn').style.display = 'block';
            document.getElementById('crashCashoutBtn').style.display = 'none';
        }, 3000);
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
    </script>
    <?php endif; // End modals for unlocked casino ?>
</body>
</html>

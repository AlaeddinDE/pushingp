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
            padding: 40px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
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
            gap: 20px;
            justify-content: center;
            margin: 40px 0;
        }
        
        .slot-reel {
            width: 120px;
            height: 120px;
            background: var(--bg-secondary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            border: 3px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        
        .slot-reel.spinning {
            animation: spin 0.1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translateY(0); }
            100% { transform: translateY(-100%); }
        }
        
        /* Wheel Specific */
        .wheel-container {
            width: 400px;
            height: 400px;
            margin: 40px auto;
            position: relative;
        }
        
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 8px solid var(--border);
            position: relative;
            transition: transform 3s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            background: conic-gradient(
                #ef4444 0deg 72deg,
                #eab308 72deg 144deg,
                #10b981 144deg 216deg,
                #3b82f6 216deg 288deg,
                #8b5cf6 288deg 345deg,
                #f59e0b 345deg 360deg
            );
        }
        
        .wheel-pointer {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 40px solid var(--accent);
            z-index: 10;
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
        
        .crash-multiplier {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 4rem;
            font-weight: 900;
            color: var(--success);
        }
        
        .bet-input-group {
            display: flex;
            gap: 12px;
            margin: 24px 0;
        }
        
        .bet-input {
            flex: 1;
            padding: 16px;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1.125rem;
            font-weight: 700;
            text-align: center;
        }
        
        .bet-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .bet-btn {
            padding: 16px 32px;
            background: var(--accent);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            font-size: 1.125rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .bet-btn:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }
        
        .bet-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quick-bet-btns {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .quick-bet-btn {
            flex: 1;
            padding: 8px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-bet-btn:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .win-message {
            text-align: center;
            padding: 24px;
            background: linear-gradient(135deg, var(--success), #059669);
            border-radius: 12px;
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            margin-top: 24px;
            display: none;
        }
        
        .win-message.show {
            display: block;
            animation: bounceIn 0.6s;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .loss-message {
            text-align: center;
            padding: 24px;
            background: linear-gradient(135deg, var(--error), #b91c1c);
            border-radius: 12px;
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            margin-top: 24px;
            display: none;
        }
        
        .loss-message.show {
            display: block;
            animation: shake 0.6s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .recent-wins {
            margin-top: 40px;
        }
        
        .win-item {
            background: var(--bg-tertiary);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .win-amount {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--success);
        }
        
        @media (max-width: 768px) {
            .casino-grid {
                grid-template-columns: 1fr;
            }
            
            .wheel-container {
                width: 300px;
                height: 300px;
            }
            
            .slots-reels {
                gap: 10px;
            }
            
            .slot-reel {
                width: 80px;
                height: 80px;
                font-size: 3rem;
            }
        }
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
        <div class="game-modal-content">
            <button class="modal-close" onclick="closeGame('crash')">×</button>
            <h2 style="font-size: 2rem; margin-bottom: 8px;">🚀 Crash</h2>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">Cashout bevor es crasht!</p>
            
            <div class="balance-display">
                <div class="balance-label">Dein Guthaben</div>
                <div class="balance-value" id="crashBalance"><?= number_format($balance, 2, ',', '.') ?> €</div>
            </div>

            <div class="crash-graph">
                <div class="crash-multiplier" id="crashMultiplier">1.00x</div>
            </div>

            <div class="quick-bet-btns">
                <button class="quick-bet-btn" onclick="setCrashBet(1)">1€</button>
                <button class="quick-bet-btn" onclick="setCrashBet(5)">5€</button>
                <button class="quick-bet-btn" onclick="setCrashBet(10)">10€</button>
                <button class="quick-bet-btn" onclick="setCrashBet(25)">25€</button>
                <button class="quick-bet-btn" onclick="setCrashBet(50)">50€</button>
            </div>

            <div class="bet-input-group">
                <input type="number" class="bet-input" id="crashBet" value="5" min="0.5" max="50" step="0.5">
                <button class="bet-btn" id="crashStartBtn" onclick="startCrash()">START</button>
                <button class="bet-btn" id="crashCashoutBtn" onclick="cashoutCrash()" style="display: none; background: var(--success);">CASHOUT</button>
            </div>

            <div class="win-message" id="crashWin"></div>
            <div class="loss-message" id="crashLoss"></div>

            <div style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 12px; font-size: 0.875rem; color: var(--text-secondary);">
                ⚡ Der Multiplikator steigt jede Sekunde. Du kannst jederzeit aussteigen und deinen Gewinn sichern. Aber pass auf: Wenn es crasht, verlierst du alles!
            </div>
        </div>
    </div>

    <script>
    let userBalance = <?= $balance ?>;
    
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
                // Stop spinning after 2 seconds
                setTimeout(() => {
                    reels.forEach((id, index) => {
                        const reel = document.getElementById(id);
                        reel.classList.remove('spinning');
                        reel.textContent = data.result[index];
                    });
                    
                    userBalance = data.new_balance;
                    document.getElementById('slotsBalance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
                    
                    if (data.win_amount > 0) {
                        document.getElementById('slotsWin').textContent = `🎉 Gewonnen: ${data.win_amount.toFixed(2)}€ (${data.multiplier}x)`;
                        document.getElementById('slotsWin').classList.add('show');
                    } else {
                        document.getElementById('slotsLoss').textContent = `😢 Verloren: ${bet.toFixed(2)}€`;
                        document.getElementById('slotsLoss').classList.add('show');
                    }
                    
                    slotsSpinning = false;
                    document.getElementById('slotsSpin').disabled = false;
                }, 2000);
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
                    userBalance = data.new_balance;
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
    let crashMultiplier = 1.00;
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
                crashPoint = data.crash_point;
                crashMultiplier = 1.00;
                
                document.getElementById('crashStartBtn').style.display = 'none';
                document.getElementById('crashCashoutBtn').style.display = 'block';
                document.getElementById('crashWin').classList.remove('show');
                document.getElementById('crashLoss').classList.remove('show');
                
                // Animate multiplier
                crashInterval = setInterval(() => {
                    crashMultiplier += 0.01;
                    document.getElementById('crashMultiplier').textContent = crashMultiplier.toFixed(2) + 'x';
                    
                    if (crashMultiplier >= crashPoint) {
                        crashGame();
                    }
                }, 100);
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
        
        try {
            const response = await fetch('/api/casino/cashout_crash.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ multiplier: crashMultiplier })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                userBalance = data.new_balance;
                document.getElementById('crashBalance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
                document.getElementById('crashWin').textContent = `🎉 Cashed Out! ${crashMultiplier.toFixed(2)}x = ${data.win_amount.toFixed(2)}€`;
                document.getElementById('crashWin').classList.add('show');
                
                document.getElementById('crashStartBtn').style.display = 'block';
                document.getElementById('crashCashoutBtn').style.display = 'none';
                document.getElementById('crashMultiplier').textContent = '1.00x';
            }
        } catch (error) {
            alert('Verbindungsfehler: ' + error.message);
        }
    }
    
    function crashGame() {
        clearInterval(crashInterval);
        crashRunning = false;
        
        const bet = parseFloat(document.getElementById('crashBet').value);
        
        document.getElementById('crashMultiplier').textContent = 'CRASHED!';
        document.getElementById('crashMultiplier').style.color = 'var(--error)';
        document.getElementById('crashLoss').textContent = `💥 CRASHED bei ${crashPoint.toFixed(2)}x! Verloren: ${bet.toFixed(2)}€`;
        document.getElementById('crashLoss').classList.add('show');
        
        setTimeout(() => {
            document.getElementById('crashMultiplier').textContent = '1.00x';
            document.getElementById('crashMultiplier').style.color = 'var(--success)';
            document.getElementById('crashStartBtn').style.display = 'block';
            document.getElementById('crashCashoutBtn').style.display = 'none';
        }, 2000);
        
        userBalance -= bet;
        document.getElementById('crashBalance').textContent = userBalance.toFixed(2).replace('.', ',') + ' €';
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

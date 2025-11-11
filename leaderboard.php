<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/xp_system.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin_user = is_admin();
$page_title = 'Leaderboard';

$leaderboard = get_leaderboard(50);
$my_rank = 0;
foreach ($leaderboard as $index => $entry) {
    if ($entry['id'] == $user_id) {
        $my_rank = $index + 1;
        break;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
    <style>
        .leaderboard-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .podium {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
            align-items: end;
        }
        
        .podium-item {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            position: relative;
            transition: all 0.3s;
        }
        
        .podium-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
        }
        
        .podium-item.rank-1 {
            order: 2;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            padding: 32px 24px;
        }
        
        .podium-item.rank-2 {
            order: 1;
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: #000;
        }
        
        .podium-item.rank-3 {
            order: 3;
            background: linear-gradient(135deg, #cd7f32, #e09856);
            color: #000;
        }
        
        .rank-badge {
            font-size: 3rem;
            margin-bottom: 12px;
        }
        
        .podium-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .podium-xp {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .podium-level {
            margin-top: 8px;
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        .leaderboard-table {
            background: var(--bg-secondary);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .leaderboard-row {
            display: grid;
            grid-template-columns: 60px 1fr 120px 100px;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .leaderboard-row:hover {
            background: var(--bg-tertiary);
        }
        
        .leaderboard-row.highlight {
            background: rgba(139, 92, 246, 0.1);
            border-left: 4px solid var(--accent);
        }
        
        .rank-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-secondary);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .user-level {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .xp-display {
            font-weight: 700;
            color: var(--accent);
        }
        
        .badge-count {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .my-position {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
            text-align: center;
        }
        
        .my-position h3 {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .my-position .rank {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--accent);
        }
    </style>

    <div class="container leaderboard-container">
        <div class="welcome">
            <h1>🏆 XP Leaderboard</h1>
            <p style="color: var(--text-secondary);">Die Top-Crew-Members nach XP</p>
        </div>
        
        <?php if ($my_rank > 0): ?>
        <div class="my-position">
            <h3>Deine Position</h3>
            <div class="rank">#<?= $my_rank ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Top 3 Podium -->
        <?php if (count($leaderboard) >= 3): ?>
        <div class="podium">
            <?php for ($i = 0; $i < 3; $i++): 
                $entry = $leaderboard[$i];
                $rank = $i + 1;
                $medals = ['🥇', '🥈', '🥉'];
            ?>
            <div class="podium-item rank-<?= $rank ?>">
                <div class="rank-badge"><?= $medals[$i] ?></div>
                <?php if (!empty($entry['level_image'])): ?>
                    <img src="<?= $entry['level_image'] ?>" alt="<?= htmlspecialchars($entry['level_title']) ?>" style="width: 80px; height: 80px; object-fit: contain; margin: 12px auto;">
                <?php endif; ?>
                <div class="podium-name"><?= htmlspecialchars($entry['name']) ?></div>
                <div class="podium-xp"><?= number_format($entry['xp_total']) ?> XP</div>
                <div class="podium-level"><?= htmlspecialchars($entry['level_title']) ?></div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <!-- Full Leaderboard -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Alle Rankings</h2>
            </div>
            
            <div class="leaderboard-table">
                <?php foreach ($leaderboard as $index => $entry): 
                    $rank = $index + 1;
                    $is_me = $entry['id'] == $user_id;
                    $initial = strtoupper(substr($entry['name'], 0, 1));
                ?>
                <div class="leaderboard-row<?= $is_me ? ' highlight' : '' ?>">
                    <div class="rank-number">#<?= $rank ?></div>
                    
                    <div class="user-info">
                        <?php if (!empty($entry['level_image'])): ?>
                            <img src="<?= $entry['level_image'] ?>" alt="<?= htmlspecialchars($entry['level_title']) ?>" style="width: 40px; height: 40px; object-fit: contain; border-radius: 50%; background: var(--bg-tertiary); padding: 4px;">
                        <?php else: ?>
                            <div class="user-avatar"><?= $initial ?></div>
                        <?php endif; ?>
                        <div class="user-details">
                            <div class="user-name">
                                <?= htmlspecialchars($entry['name']) ?>
                                <?= $is_me ? ' <span style="color: var(--accent);">(Du)</span>' : '' ?>
                            </div>
                            <div class="user-level">
                                <?= htmlspecialchars($entry['level_title']) ?>
                                <?php if ($entry['badge_count'] > 0): ?>
                                    · <?= $entry['badge_count'] ?> Badge<?= $entry['badge_count'] != 1 ? 's' : '' ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="xp-display"><?= number_format($entry['xp_total']) ?> XP</div>
                    
                    <div class="badge-count">
                        <?php if ($entry['badge_count'] > 0): ?>
                            🏅 <?= $entry['badge_count'] ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

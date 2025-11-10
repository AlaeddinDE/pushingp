#!/usr/bin/php
<?php
/**
 * XP System Cron Job
 * Run daily for automated XP checks
 * 
 * Add to crontab:
 * 0 2 * * * /usr/bin/php /var/www/html/includes/xp_cron.php
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/xp_system.php';
require_once __DIR__ . '/kasse_xp_hooks.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting XP Cron Job\n";

// 1. Check inactivity penalties
echo "Checking inactivity penalties...\n";
check_inactivity_penalties();

// 2. Check payment streaks (run on 1st of month)
if (date('d') == '01') {
    echo "Checking payment streaks...\n";
    check_payment_streaks();
}

// 3. Check milestone badges for all active users
echo "Checking milestone badges...\n";
$result = $conn->query("SELECT id FROM users WHERE status = 'active'");
$count = 0;
while ($row = $result->fetch_assoc()) {
    check_milestone_badges($row['id']);
    $count++;
}
echo "Checked $count users for badges\n";

// 4. Award level-based badges
echo "Checking level badges...\n";
$result = $conn->query("SELECT id, level_id FROM users WHERE status = 'active'");
while ($row = $result->fetch_assoc()) {
    if ($row['level_id'] >= 5) {
        award_badge_if_not_exists($row['id'], 'LEVEL_5');
    }
    if ($row['level_id'] >= 10) {
        award_badge_if_not_exists($row['id'], 'LEVEL_10');
    }
}

// 5. Award XP milestones
echo "Checking XP milestones...\n";
$result = $conn->query("SELECT id, xp_total FROM users WHERE status = 'active' AND xp_total >= 10000");
while ($row = $result->fetch_assoc()) {
    award_badge_if_not_exists($row['id'], 'XP_MASTER');
}

echo "[" . date('Y-m-d H:i:s') . "] XP Cron Job completed\n";

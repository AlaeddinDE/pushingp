<?php
/**
 * Daily XP Maintenance Cron Job
 * Run this daily via cron: 0 0 * * * php /var/www/html/api/cron/daily_xp_maintenance.php
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/xp_system.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting daily XP maintenance...\n";

// 1. Check and apply inactivity penalties
echo "Checking inactivity penalties...\n";
check_inactivity_penalty();

// 2. Check and award badges for all users
echo "Checking badges for all users...\n";
$result = $conn->query("SELECT id FROM users WHERE status = 'active'");
while ($row = $result->fetch_assoc()) {
    check_and_award_badges($row['id']);
}

// 3. Check for payment streaks
echo "Checking payment streaks...\n";
$result = $conn->query("
    SELECT u.id, COUNT(*) as months_paid
    FROM users u
    JOIN transaktionen t ON t.mitglied_id = u.id
    WHERE t.typ = 'EINZAHLUNG'
    AND t.datum >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    AND u.status = 'active'
    GROUP BY u.id
    HAVING months_paid >= 3
");

while ($row = $result->fetch_assoc()) {
    // Check if already awarded this month
    $user_id = $row['id'];
    $check = $conn->query("
        SELECT id FROM xp_history 
        WHERE user_id = $user_id 
        AND action_code = 'no_debt_3months' 
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    
    if ($check->num_rows == 0) {
        add_xp($user_id, 'no_debt_3months', '3 Monate ohne Rückstand');
    }
}

// 4. Profile completion check
echo "Checking profile completion...\n";
$result = $conn->query("
    SELECT id FROM users 
    WHERE status = 'active'
    AND avatar IS NOT NULL 
    AND bio IS NOT NULL 
    AND team_role IS NOT NULL
    AND id NOT IN (
        SELECT user_id FROM xp_history WHERE action_code = 'profile_complete'
    )
");

while ($row = $result->fetch_assoc()) {
    add_xp($row['id'], 'profile_complete', 'Profil vollständig ausgefüllt');
}

echo "[" . date('Y-m-d H:i:s') . "] Daily XP maintenance completed!\n";

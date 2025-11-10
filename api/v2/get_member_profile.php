<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xp_system.php';

secure_session_start();

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid user_id']);
    exit;
}

// Get basic user info
$stmt = $conn->prepare("SELECT id, name, username, created_at, role FROM users WHERE id = ? AND status = 'active'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($id, $name, $username, $created_at, $role);

if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'error' => 'User not found']);
    exit;
}
$stmt->close();

// Get XP info
$xp_info = get_user_level_info($user_id);
$badges = get_user_badges($user_id);

// Check if user has active shift NOW
$has_active_shift = false;
$stmt_shift = $conn->prepare("
    SELECT COUNT(*) 
    FROM shifts 
    WHERE user_id = ? 
    AND date = CURDATE() 
    AND NOW() BETWEEN CONCAT(date, ' ', start_time) AND CONCAT(date, ' ', end_time)
    AND type != 'free'
");
$stmt_shift->bind_param('i', $user_id);
$stmt_shift->execute();
$stmt_shift->bind_result($shift_count);
$stmt_shift->fetch();
$stmt_shift->close();
$has_active_shift = ($shift_count > 0);

// Calculate initials
$name_parts = explode(' ', $name);
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($name, 0, 2));
}

// Calculate member since
$joined = new DateTime($created_at);
$now = new DateTime();
$member_diff = $joined->diff($now);
if ($member_diff->y > 0) {
    $member_since = $member_diff->y . ' Jahr' . ($member_diff->y > 1 ? 'e' : '');
} elseif ($member_diff->m > 0) {
    $member_since = $member_diff->m . ' Monat' . ($member_diff->m > 1 ? 'e' : '');
} else {
    $member_since = 'Neu';
}

// Get member role/rank
$user_level = $xp_info['level'] ?? 1;
$rank = 'Member';
if ($user_level >= 10) $rank = 'Elite';
elseif ($user_level >= 7) $rank = 'Veteran';
elseif ($user_level >= 5) $rank = 'Crew';
elseif ($user_level >= 3) $rank = 'Trusted';

echo json_encode([
    'status' => 'success',
    'data' => [
        'id' => $id,
        'name' => $name,
        'username' => $username,
        'initials' => $initials,
        'is_admin' => $role == 'admin',
        'created_at' => $created_at,
        'member_since' => $member_since,
        'level' => $xp_info['level'] ?? 1,
        'level_name' => $xp_info['level_name'] ?? 'Rookie',
        'level_icon' => $xp_info['level_icon'] ?? '🎖️',
        'xp' => $xp_info['xp'] ?? 0,
        'next_level_xp' => $xp_info['next_level_xp'] ?? 100,
        'progress_percent' => $xp_info['progress_percent'] ?? 0,
        'badges' => $badges,
        'rank' => $rank,
        'is_online' => !$has_active_shift,
        'has_active_shift' => $has_active_shift
    ]
]);

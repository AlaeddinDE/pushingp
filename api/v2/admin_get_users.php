<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? max(1, min(100, (int) $_GET['perPage'])) : 25;
$offset = ($page - 1) * $perPage;

$total = 0;
$stmt = $mysqli->prepare('SELECT COUNT(*) FROM members_v2');
if ($stmt && $stmt->execute()) {
    $stmt->bind_result($totalCount);
    if ($stmt->fetch()) {
        $total = (int) $totalCount;
    }
    $stmt->close();
}

$stmt = $mysqli->prepare('SELECT id, name, COALESCE(email, ""), COALESCE(discord_tag, ""), COALESCE(avatar_url, ""), COALESCE(roles, "member"), status, joined_at, left_at, is_locked FROM members_v2 ORDER BY created_at DESC LIMIT ?, ?');
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_param('ii', $offset, $perPage);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_result($id, $name, $email, $discord, $avatar, $roles, $status, $joinedAt, $leftAt, $isLocked);

$users = [];
while ($stmt->fetch()) {
    $users[] = [
        'id' => (int) $id,
        'name' => $name,
        'email' => $email,
        'discordTag' => $discord,
        'avatarUrl' => $avatar,
        'roles' => array_filter(array_map('trim', explode(',', $roles))),
        'status' => $status,
        'joinedAt' => $joinedAt,
        'leftAt' => $leftAt,
        'locked' => (bool) $isLocked,
    ];
}
$stmt->close();

api_send_response('success', [
    'users' => $users,
    'pagination' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ],
]);

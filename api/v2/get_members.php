<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$stmt = $conn->prepare(
    "SELECT id, name, COALESCE(discord_tag,''), COALESCE(avatar_url,''), COALESCE(roles,''), status, joined_at, left_at, flag, is_locked
     FROM members_v2
     ORDER BY status='active' DESC, name ASC"
);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_result($id, $name, $discord, $avatar, $roles, $status, $joinedAt, $leftAt, $flag, $isLocked);

$members = [];
while ($stmt->fetch()) {
    $roleList = array_filter(array_map('trim', explode(',', $roles)));
    $members[] = [
        'id' => (int) $id,
        'name' => $name,
        'discordTag' => $discord,
        'avatarUrl' => $avatar,
        'roles' => $roleList,
        'status' => $status,
        'joinedAt' => $joinedAt,
        'leftAt' => $leftAt,
        'flag' => $flag,
        'locked' => (bool) $isLocked,
    ];
}

$stmt->close();

api_send_response('success', ['members' => $members]);

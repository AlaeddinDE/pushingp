<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_method('GET');

$stmt = $conn->prepare(
    "SELECT m.id, m.name, COALESCE(m.avatar_url, ''), COALESCE(d.status, 'offline')
     FROM members_v2 m
     LEFT JOIN discord_status_cache d ON d.member_id = m.id
     WHERE m.status='active' AND m.is_locked = 0
     ORDER BY m.name ASC"
);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_result($id, $name, $avatar, $status);

$members = [];
while ($stmt->fetch()) {
    $members[] = [
        'id' => (int) $id,
        'name' => $name,
        'avatarUrl' => $avatar,
        'status' => $status,
    ];
}

$stmt->close();

api_send_response('success', ['members' => $members]);

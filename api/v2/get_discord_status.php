<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$stmt = $conn->prepare(
    "SELECT member_id, status, updated_at FROM discord_status_cache"
);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_result($memberId, $status, $updatedAt);
$items = [];
while ($stmt->fetch()) {
    $items[] = [
        'id' => (int) $memberId,
        'status' => $status,
        'updatedAt' => $updatedAt,
    ];
}
$stmt->close();

api_send_response('success', ['presence' => $items]);

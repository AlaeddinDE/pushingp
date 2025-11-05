<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

$eventId = isset($_GET['eventId']) ? api_safe_int($_GET['eventId']) : null;
if ($eventId === null) {
    api_send_response('error', null, 'Event-ID fehlt', 422);
}

$stmt = $mysqli->prepare('SELECT id FROM events WHERE id=?');
if (!$stmt) {
    api_send_response('error', null, 'Event nicht gefunden', 404);
}
$stmt->bind_param('i', $eventId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Event nicht gefunden', 404);
}
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    api_send_response('error', null, 'Event nicht gefunden', 404);
}
$stmt->close();

$stmt = $mysqli->prepare('SELECT ep.member_id, m.name, ep.state, ep.availability, ep.updated_at FROM event_participants ep LEFT JOIN members_v2 m ON m.id = ep.member_id WHERE ep.event_id=? ORDER BY m.name ASC');
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_param('i', $eventId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_result($memberId, $name, $state, $availability, $updatedAt);

$participants = [];
while ($stmt->fetch()) {
    $participants[] = [
        'memberId' => (int) $memberId,
        'name' => $name,
        'state' => $state,
        'availability' => $availability,
        'updatedAt' => $updatedAt,
    ];
}
$stmt->close();

api_send_response('success', ['participants' => $participants]);

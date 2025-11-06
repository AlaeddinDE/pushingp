<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$eventId = isset($data['eventId']) ? api_safe_int($data['eventId']) : null;
if ($eventId === null) {
    api_send_response('error', null, 'Event-ID fehlt', 422);
}

$memberId = api_current_user_id();
if (isset($data['memberId'])) {
    $candidate = api_safe_int($data['memberId']);
    if ($candidate === null) {
        api_send_response('error', null, 'Ungültiges Mitglied', 422);
    }
    if (!api_user_has_role($user, 'admin') && $candidate !== $memberId) {
        api_send_response('error', null, 'Keine Berechtigung', 403);
    }
    $memberId = $candidate;
}

if ($memberId === null) {
    api_send_response('error', null, 'Nutzer nicht gefunden', 400);
}

$state = isset($data['state']) ? strtolower(trim((string) $data['state'])) : 'pending';
if (!in_array($state, ['yes', 'no', 'pending'], true)) {
    api_send_response('error', null, 'Ungültiger Status', 422);
}

$availability = isset($data['availability']) ? strtolower(trim((string) $data['availability'])) : 'free';
if (!in_array($availability, ['free', 'vacation', 'shift', 'sick'], true)) {
    $availability = 'free';
}

$stmt = $conn->prepare('SELECT 1 FROM events WHERE id=? AND status!=\'canceled\'');
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

$stmt = $conn->prepare('INSERT INTO event_participants (event_id, member_id, state, availability) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state), availability=VALUES(availability), updated_at=CURRENT_TIMESTAMP');
if (!$stmt) {
    api_send_response('error', null, 'Teilnahmestatus konnte nicht gespeichert werden', 500);
}
$stmt->bind_param('iiss', $eventId, $memberId, $state, $availability);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Teilnahmestatus konnte nicht gespeichert werden', 500);
}
$stmt->close();

api_send_response('success', ['state' => $state, 'availability' => $availability]);

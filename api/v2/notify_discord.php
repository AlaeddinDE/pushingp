<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$eventId = isset($data['eventId']) ? api_safe_int($data['eventId']) : null;
$message = isset($data['message']) ? trim((string) $data['message']) : '';

if ($eventId === null && $message === '') {
    api_send_response('error', null, 'Event oder Nachricht erforderlich', 422);
}

$payload = [
    'message' => $message,
    'eventId' => $eventId,
    'createdAt' => date(DATE_ATOM),
];

if ($eventId !== null) {
    $stmt = $mysqli->prepare('SELECT title, start, location FROM events WHERE id=?');
    if ($stmt && $stmt->bind_param('i', $eventId) && $stmt->execute()) {
        $stmt->bind_result($title, $start, $location);
        if ($stmt->fetch()) {
            $payload['event'] = [
                'title' => $title,
                'start' => $start,
                'location' => $location,
            ];
        }
        $stmt->close();
    }
}

$jsonPayload = json_encode($payload);
$stmt = $mysqli->prepare('INSERT INTO discord_notifications (event_id, payload) VALUES (?,?)');
if (!$stmt) {
    api_send_response('error', null, 'Benachrichtigung konnte nicht erstellt werden', 500);
}
$stmt->bind_param('is', $eventId, $jsonPayload);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Benachrichtigung konnte nicht erstellt werden', 500);
}
$stmt->close();

api_send_response('success', ['queued' => true]);

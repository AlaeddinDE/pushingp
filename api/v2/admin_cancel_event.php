<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$eventId = isset($data['id']) ? api_safe_int($data['id']) : null;
$reason = isset($data['reason']) ? trim((string) $data['reason']) : '';

if ($eventId === null) {
    api_send_response('error', null, 'Event-ID fehlt', 422);
}

$stmt = $conn->prepare('UPDATE events SET status="canceled" WHERE id=?');
if (!$stmt) {
    api_send_response('error', null, 'Event konnte nicht aktualisiert werden', 500);
}
$stmt->bind_param('i', $eventId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Event konnte nicht aktualisiert werden', 500);
}
$stmt->close();

$stmt = $conn->prepare('UPDATE reservations_v2 SET status="cancelled" WHERE event_id=? AND status IN ("active","released")');
if ($stmt) {
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $stmt->close();
}

api_log_admin_action($conn, api_current_user_id() ?? 0, 'admin_cancel_event', 'event', (string) $eventId, [
    'reason' => $reason,
]);

api_send_response('success', ['canceled' => true]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$message = isset($data['message']) ? trim((string) $data['message']) : '';
$severity = isset($data['severity']) ? strtolower(trim((string) $data['severity'])) : 'info';
$channel = isset($data['channel']) ? trim((string) $data['channel']) : 'discord';

if ($message === '') {
    api_send_response('error', null, 'Nachricht darf nicht leer sein', 422);
}

$payload = [
    'message' => $message,
    'severity' => $severity,
    'channel' => $channel,
    'createdAt' => date(DATE_ATOM),
    'createdBy' => api_current_user_id(),
];

$stmt = $mysqli->prepare('INSERT INTO discord_notifications (event_id, payload) VALUES (NULL, ?)');
if (!$stmt) {
    api_send_response('error', null, 'Benachrichtigung konnte nicht erstellt werden', 500);
}
$json = json_encode($payload);
$stmt->bind_param('s', $json);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Benachrichtigung konnte nicht erstellt werden', 500);
}
$stmt->close();

api_log_admin_action($mysqli, api_current_user_id() ?? 0, 'admin_notify', 'notification', null, $payload);

api_send_response('success', ['queued' => true]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$action = isset($data['action']) ? strtolower(trim((string) $data['action'])) : 'upsert';
$date = isset($data['date']) ? trim((string) $data['date']) : '';
$type = isset($data['type']) ? strtolower(trim((string) $data['type'])) : '';
$targetId = api_current_user_id();

if (isset($data['userId'])) {
    $candidate = api_safe_int($data['userId']);
    if ($candidate === null) {
        api_send_response('error', null, 'Ungültiger Nutzer', 400);
    }
    if (!api_user_has_role($user, 'admin') && $candidate !== $targetId) {
        api_send_response('error', null, 'Keine Berechtigung', 403);
    }
    $targetId = $candidate;
}

if ($targetId === null) {
    api_send_response('error', null, 'Nutzer nicht gefunden', 400);
}

if ($action === 'delete') {
    $id = isset($data['id']) ? api_safe_int($data['id']) : null;
    if ($id === null) {
        api_send_response('error', null, 'ID fehlt', 422);
    }
    $stmt = $conn->prepare('DELETE FROM shifts WHERE id=? AND member_id=?');
    if (!$stmt) {
        api_send_response('error', null, 'Löschen fehlgeschlagen', 500);
    }
    $stmt->bind_param('ii', $id, $targetId);
    if (!$stmt->execute()) {
        $stmt->close();
        api_send_response('error', null, 'Löschen fehlgeschlagen', 500);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0 && api_user_has_role($user, 'admin')) {
        api_log_admin_action($conn, api_current_user_id() ?? 0, 'delete_shift', 'shift', (string) $id, []);
    }

    api_send_response('success', ['removed' => $affected > 0]);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    api_send_response('error', null, 'Ungültiges Datum', 422);
}

$allowedTypes = ['early', 'late', 'night', 'day', 'custom'];
if (!in_array($type, $allowedTypes, true)) {
    api_send_response('error', null, 'Ungültiger Schichttyp', 422);
}

$defaults = [
    'early' => ['06:00:00', '14:00:00'],
    'late' => ['14:00:00', '22:00:00'],
    'night' => ['22:00:00', '06:00:00'],
    'day' => ['07:00:00', '17:30:00'],
];

$startTime = isset($data['start']) ? trim((string) $data['start']) : ($defaults[$type][0] ?? null);
$endTime = isset($data['end']) ? trim((string) $data['end']) : ($defaults[$type][1] ?? null);

if ($startTime === null || $endTime === null) {
    api_send_response('error', null, 'Start- und Endzeit erforderlich', 422);
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
    api_send_response('error', null, 'Ungültige Uhrzeit', 422);
}

$startTime = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
$endTime = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;

$stmt = $conn->prepare(
    'INSERT INTO shifts (member_id, shift_date, type, start_time, end_time, created_by)
     VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE type=VALUES(type), start_time=VALUES(start_time), end_time=VALUES(end_time), updated_at=CURRENT_TIMESTAMP'
);
if (!$stmt) {
    api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
}

$creatorId = api_current_user_id() ?? $targetId;
$stmt->bind_param('issssi', $targetId, $date, $type, $startTime, $endTime, $creatorId);

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
}

$stmt->close();

api_log_admin_action($conn, $creatorId, 'save_shift', 'shift', $date . '#' . $targetId, [
    'type' => $type,
]);

api_send_response('success', ['saved' => true]);

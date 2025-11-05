<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$memberId = isset($data['memberId']) ? api_safe_int($data['memberId']) : null;
$action = isset($data['action']) ? strtolower(trim((string) $data['action'])) : 'upsert';
$date = isset($data['date']) ? trim((string) $data['date']) : '';
$type = isset($data['type']) ? strtolower(trim((string) $data['type'])) : '';
$startTime = isset($data['start']) ? trim((string) $data['start']) : null;
$endTime = isset($data['end']) ? trim((string) $data['end']) : null;

if ($memberId === null) {
    api_send_response('error', null, 'Mitglied-ID fehlt', 422);
}

if (!api_member_exists($mysqli, $memberId)) {
    api_send_response('error', null, 'Mitglied existiert nicht', 404);
}

if ($action === 'delete') {
    $shiftId = isset($data['id']) ? api_safe_int($data['id']) : null;
    if ($shiftId === null) {
        api_send_response('error', null, 'Schicht-ID fehlt', 422);
    }
    $stmt = $mysqli->prepare('DELETE FROM shifts WHERE id=? AND member_id=?');
    if (!$stmt) {
        api_send_response('error', null, 'Löschen fehlgeschlagen', 500);
    }
    $stmt->bind_param('ii', $shiftId, $memberId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    api_log_admin_action($mysqli, api_current_user_id() ?? 0, 'admin_delete_shift', 'shift', (string) $shiftId, []);
    api_send_response('success', ['removed' => $deleted]);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    api_send_response('error', null, 'Ungültiges Datum', 422);
}

$allowedTypes = ['early', 'late', 'night', 'day'];
if (!in_array($type, $allowedTypes, true)) {
    api_send_response('error', null, 'Ungültiger Schichttyp', 422);
}

$defaults = [
    'early' => ['06:00:00', '14:00:00'],
    'late' => ['14:00:00', '22:00:00'],
    'night' => ['22:00:00', '06:00:00'],
    'day' => ['07:00:00', '17:30:00'],
];

$start = $startTime ? (strlen($startTime) === 5 ? $startTime . ':00' : $startTime) : $defaults[$type][0];
$end = $endTime ? (strlen($endTime) === 5 ? $endTime . ':00' : $endTime) : $defaults[$type][1];

if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end)) {
    api_send_response('error', null, 'Ungültige Uhrzeit', 422);
}

$stmt = $mysqli->prepare(
    'INSERT INTO shifts (member_id, shift_date, type, start_time, end_time, created_by)
     VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE type=VALUES(type), start_time=VALUES(start_time), end_time=VALUES(end_time), updated_at=CURRENT_TIMESTAMP'
);
if (!$stmt) {
    api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
}
$adminId = api_current_user_id() ?? 0;
$stmt->bind_param('issssi', $memberId, $date, $type, $start, $end, $adminId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
}
$stmt->close();

api_log_admin_action($mysqli, $adminId, 'admin_upsert_shift', 'shift', $date . '#' . $memberId, [
    'type' => $type,
]);

api_send_response('success', ['saved' => true]);

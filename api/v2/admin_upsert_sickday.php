<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$memberId = isset($data['memberId']) ? api_safe_int($data['memberId']) : null;
$action = isset($data['action']) ? strtolower(trim((string) $data['action'])) : 'upsert';
$start = isset($data['start']) ? trim((string) $data['start']) : '';
$end = isset($data['end']) ? trim((string) $data['end']) : '';
$sickId = isset($data['id']) ? api_safe_int($data['id']) : null;

if ($memberId === null) {
    api_send_response('error', null, 'Mitglied-ID fehlt', 422);
}

if (!api_member_exists($conn, $memberId)) {
    api_send_response('error', null, 'Mitglied existiert nicht', 404);
}

if ($action === 'delete') {
    if ($sickId === null) {
        api_send_response('error', null, 'Krank-ID fehlt', 422);
    }
    $stmt = $conn->prepare('DELETE FROM sickdays WHERE id=? AND member_id=?');
    if (!$stmt) {
        api_send_response('error', null, 'Löschen fehlgeschlagen', 500);
    }
    $stmt->bind_param('ii', $sickId, $memberId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    api_log_admin_action($conn, api_current_user_id() ?? 0, 'admin_delete_sickday', 'sickday', (string) $sickId, []);
    api_send_response('success', ['removed' => $deleted]);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    api_send_response('error', null, 'Ungültiges Datum', 422);
}

$startDate = new DateTime($start);
$endDate = new DateTime($end);
if ($endDate < $startDate) {
    api_send_response('error', null, 'Enddatum darf nicht vor Startdatum liegen', 422);
}

$stmt = null;
if ($sickId !== null) {
    $query = 'SELECT id FROM sickdays WHERE member_id=? AND NOT (end_date < ? OR start_date > ?) AND id<>? LIMIT 1';
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        api_send_response('error', null, 'Validierung fehlgeschlagen', 500);
    }
    $stmt->bind_param('issi', $memberId, $start, $end, $sickId);
} else {
    $query = 'SELECT id FROM sickdays WHERE member_id=? AND NOT (end_date < ? OR start_date > ?) LIMIT 1';
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        api_send_response('error', null, 'Validierung fehlgeschlagen', 500);
    }
    $stmt->bind_param('iss', $memberId, $start, $end);
}
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Validierung fehlgeschlagen', 500);
}
if ($stmt->fetch()) {
    $stmt->close();
    api_send_response('error', null, 'Zeitraum überschneidet sich mit bestehender Krankmeldung', 409);
}
$stmt->close();

$stmt = $conn->prepare('SELECT id FROM vacations WHERE member_id=? AND NOT (end_date < ? OR start_date > ?) LIMIT 1');
if ($stmt) {
    $stmt->bind_param('iss', $memberId, $start, $end);
    if ($stmt->execute() && $stmt->fetch()) {
        $stmt->close();
        api_send_response('error', null, 'Urlaub überschneidet sich mit Krankmeldung', 409);
    }
    $stmt->close();
}

if ($sickId === null) {
    $stmt = $conn->prepare('INSERT INTO sickdays (member_id, start_date, end_date, created_by) VALUES (?,?,?,?)');
    if (!$stmt) {
        api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
    }
    $creator = api_current_user_id() ?? 0;
    $stmt->bind_param('issi', $memberId, $start, $end, $creator);
    if (!$stmt->execute()) {
        $stmt->close();
        api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
    }
    $newId = $stmt->insert_id;
    $stmt->close();
    api_log_admin_action($conn, $creator, 'admin_upsert_sickday', 'sickday', (string) $newId, $data);
    api_send_response('success', ['saved' => true, 'id' => (int) $newId]);
}

$stmt = $conn->prepare('UPDATE sickdays SET start_date=?, end_date=? WHERE id=? AND member_id=?');
if (!$stmt) {
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}
$stmt->bind_param('ssii', $start, $end, $sickId, $memberId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}
$stmt->close();

api_log_admin_action($conn, api_current_user_id() ?? 0, 'admin_upsert_sickday', 'sickday', (string) $sickId, $data);

api_send_response('success', ['saved' => true, 'id' => (int) $sickId]);

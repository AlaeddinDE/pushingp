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
$vacationId = isset($data['id']) ? api_safe_int($data['id']) : null;

if ($memberId === null) {
    api_send_response('error', null, 'Mitglied-ID fehlt', 422);
}

if (!api_member_exists($conn, $memberId)) {
    api_send_response('error', null, 'Mitglied existiert nicht', 404);
}

if ($action === 'delete') {
    if ($vacationId === null) {
        api_send_response('error', null, 'Urlaubs-ID fehlt', 422);
    }
    $stmt = $conn->prepare('DELETE FROM vacations WHERE id=? AND member_id=?');
    if (!$stmt) {
        api_send_response('error', null, 'Löschen fehlgeschlagen', 500);
    }
    $stmt->bind_param('ii', $vacationId, $memberId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    api_log_admin_action($conn, api_current_user_id() ?? 0, 'admin_delete_vacation', 'vacation', (string) $vacationId, []);
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

$duration = (int) $startDate->diff($endDate)->format('%a');
if ($duration > 31) {
    api_send_response('error', null, 'Urlaub darf maximal 32 Tage umfassen', 422);
}

$validateSql = 'SELECT id FROM vacations WHERE member_id=? AND NOT (end_date < ? OR start_date > ?)';
if ($vacationId !== null) {
    $validateSql .= ' AND id<>?';
}
$validateSql .= ' LIMIT 1';
$stmt = $conn->prepare($validateSql);
if (!$stmt) {
    api_send_response('error', null, 'Validierung fehlgeschlagen', 500);
}
if ($vacationId !== null) {
    $stmt->bind_param('issi', $memberId, $start, $end, $vacationId);
} else {
    $stmt->bind_param('iss', $memberId, $start, $end);
}
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Validierung fehlgeschlagen', 500);
}
if ($stmt->fetch()) {
    $stmt->close();
    api_send_response('error', null, 'Zeitraum überschneidet sich mit bestehendem Urlaub', 409);
}
$stmt->close();

if ($vacationId === null) {
    $stmt = $conn->prepare('INSERT INTO vacations (member_id, start_date, end_date, created_by) VALUES (?,?,?,?)');
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
    api_log_admin_action($conn, $creator, 'admin_upsert_vacation', 'vacation', (string) $newId, $data);
    api_send_response('success', ['saved' => true, 'id' => (int) $newId]);
}

$stmt = $conn->prepare('UPDATE vacations SET start_date=?, end_date=? WHERE id=? AND member_id=?');
if (!$stmt) {
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}
$stmt->bind_param('ssii', $start, $end, $vacationId, $memberId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}
$stmt->close();

api_log_admin_action($conn, api_current_user_id() ?? 0, 'admin_upsert_vacation', 'vacation', (string) $vacationId, $data);

api_send_response('success', ['saved' => true, 'id' => (int) $vacationId]);

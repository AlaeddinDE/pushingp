<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$action = isset($data['action']) ? strtolower(trim((string) $data['action'])) : 'upsert';
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
    $stmt = $mysqli->prepare('DELETE FROM sickdays WHERE id=? AND member_id=?');
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
    api_send_response('success', ['removed' => $affected > 0]);
}

$start = isset($data['start']) ? trim((string) $data['start']) : '';
$end = isset($data['end']) ? trim((string) $data['end']) : '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    api_send_response('error', null, 'Ungültiges Datum', 422);
}

$startDate = new DateTime($start);
$endDate = new DateTime($end);
if ($endDate < $startDate) {
    api_send_response('error', null, 'Enddatum darf nicht vor Startdatum liegen', 422);
}

$id = isset($data['id']) ? api_safe_int($data['id']) : null;

$stmt = $mysqli->prepare(
    'SELECT id FROM sickdays WHERE member_id=? AND (? IS NULL OR id<>?) AND NOT (end_date < ? OR start_date > ?) LIMIT 1'
);
if (!$stmt) {
    api_send_response('error', null, 'Validierung fehlgeschlagen', 500);
}
$stmt->bind_param('iisss', $targetId, $id, $id, $start, $end);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Validierung fehlgeschlagen', 500);
}
if ($stmt->fetch()) {
    $stmt->close();
    api_send_response('error', null, 'Zeitraum überschneidet sich mit bestehender Krankmeldung', 409);
}
$stmt->close();

// Urlaub prüfen
$stmt = $mysqli->prepare(
    'SELECT id FROM vacations WHERE member_id=? AND NOT (end_date < ? OR start_date > ?) LIMIT 1'
);
if ($stmt) {
    $stmt->bind_param('iss', $targetId, $start, $end);
    if ($stmt->execute() && $stmt->fetch()) {
        $stmt->close();
        api_send_response('error', null, 'Urlaub überschneidet sich mit Krankmeldung', 409);
    }
    $stmt->close();
}

if ($id === null) {
    $stmt = $mysqli->prepare('INSERT INTO sickdays (member_id, start_date, end_date, created_by) VALUES (?,?,?,?)');
    if (!$stmt) {
        api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
    }
    $creator = api_current_user_id() ?? $targetId;
    $stmt->bind_param('issi', $targetId, $start, $end, $creator);
    if (!$stmt->execute()) {
        $stmt->close();
        api_send_response('error', null, 'Speichern fehlgeschlagen', 500);
    }
    $stmt->close();
} else {
    $stmt = $mysqli->prepare('UPDATE sickdays SET start_date=?, end_date=? WHERE id=? AND member_id=?');
    if (!$stmt) {
        api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
    }
    $stmt->bind_param('ssii', $start, $end, $id, $targetId);
    if (!$stmt->execute()) {
        $stmt->close();
        api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
    }
    $stmt->close();
}

api_send_response('success', ['saved' => true]);

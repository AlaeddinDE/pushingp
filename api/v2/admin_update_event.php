<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$eventId = isset($data['id']) ? api_safe_int($data['id']) : null;
if ($eventId === null) {
    api_send_response('error', null, 'Event-ID fehlt', 422);
}

$stmt = $mysqli->prepare('SELECT title, description, start, end, location, COALESCE(cost,0), paid_by, status FROM events WHERE id=?');
if (!$stmt) {
    api_send_response('error', null, 'Event nicht gefunden', 404);
}
$stmt->bind_param('i', $eventId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Event nicht gefunden', 404);
}
$stmt->bind_result($currentTitle, $currentDescription, $currentStart, $currentEnd, $currentLocation, $currentCost, $currentPaidBy, $currentStatus);
if (!$stmt->fetch()) {
    $stmt->close();
    api_send_response('error', null, 'Event nicht gefunden', 404);
}
$stmt->close();

$title = isset($data['title']) ? trim((string) $data['title']) : $currentTitle;
$description = isset($data['description']) ? trim((string) $data['description']) : $currentDescription;
$start = isset($data['start']) ? trim((string) $data['start']) : $currentStart;
$end = array_key_exists('end', $data) ? trim((string) $data['end']) : $currentEnd;
$location = isset($data['location']) ? trim((string) $data['location']) : $currentLocation;
$cost = isset($data['cost']) ? (float) $data['cost'] : (float) $currentCost;
$paidBy = isset($data['paidBy']) ? strtolower(trim((string) $data['paidBy'])) : $currentPaidBy;
$status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : $currentStatus;

if (mb_strlen($title) < 3) {
    api_send_response('error', null, 'Titel muss mindestens 3 Zeichen lang sein', 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $start)) {
    api_send_response('error', null, 'Ungültiges Startdatum', 422);
}
if ($end !== null && $end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $end)) {
    api_send_response('error', null, 'Ungültiges Enddatum', 422);
}
if (!in_array($paidBy, ['pool', 'private'], true)) {
    api_send_response('error', null, 'Ungültige Kostenstelle', 422);
}
if (!in_array($status, ['active', 'canceled'], true)) {
    api_send_response('error', null, 'Ungültiger Status', 422);
}

if ($paidBy === 'pool' && $cost <= 0) {
    api_send_response('error', null, 'Kosten müssen > 0 sein, wenn die Kasse zahlt', 422);
}

if ($paidBy === 'pool') {
    $overview = api_get_cash_overview($mysqli);
    if ($overview['available'] < $cost) {
        api_send_response('error', null, 'Kasse deckt die Kosten nicht', 409);
    }
}

$stmt = $mysqli->prepare('UPDATE events SET title=?, description=?, start=?, end=?, location=?, cost=?, paid_by=?, status=? WHERE id=?');
if (!$stmt) {
    api_send_response('error', null, 'Event konnte nicht aktualisiert werden', 500);
}
$endValue = ($end === '') ? null : $end;
$stmt->bind_param('sssssdssi', $title, $description, $start, $endValue, $location, $cost, $paidBy, $status, $eventId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Event konnte nicht aktualisiert werden', 500);
}
$stmt->close();

if ($status === 'canceled') {
    $stmt = $mysqli->prepare('UPDATE reservations_v2 SET status="cancelled" WHERE event_id=? AND status="active"');
    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $stmt->close();
    }
} elseif ($paidBy === 'pool' && $cost > 0) {
    $stmt = $mysqli->prepare('SELECT id FROM reservations_v2 WHERE event_id=? AND status="active" LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        if ($stmt->execute()) {
            $stmt->bind_result($reservationId);
            if ($stmt->fetch()) {
                $stmt->close();
                $stmt = $mysqli->prepare('UPDATE reservations_v2 SET amount=?, status="active" WHERE id=?');
                if ($stmt) {
                    $stmt->bind_param('di', $cost, $reservationId);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt->close();
                $statusActive = 'active';
                $notes = 'Event-Reservierung';
                $stmt = $mysqli->prepare('INSERT INTO reservations_v2 (event_id, amount, status, notes) VALUES (?,?,?,?)');
                if ($stmt) {
                    $stmt->bind_param('idss', $eventId, $cost, $statusActive, $notes);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            $stmt->close();
        }
    }
} else {
    $stmt = $mysqli->prepare('UPDATE reservations_v2 SET status="released" WHERE event_id=? AND status="active"');
    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $stmt->close();
    }
}

api_log_admin_action($mysqli, api_current_user_id() ?? 0, 'admin_update_event', 'event', (string) $eventId, $data);

api_send_response('success', ['updated' => true]);

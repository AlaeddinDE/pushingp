<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$title = isset($data['title']) ? trim((string) $data['title']) : '';
$description = isset($data['description']) ? trim((string) $data['description']) : '';
$start = isset($data['start']) ? trim((string) $data['start']) : '';
$end = isset($data['end']) ? trim((string) $data['end']) : null;
$location = isset($data['location']) ? trim((string) $data['location']) : '';
$cost = isset($data['cost']) ? (float) $data['cost'] : 0.0;
$paidBy = isset($data['paidBy']) ? strtolower(trim((string) $data['paidBy'])) : 'private';
$participants = isset($data['participants']) && is_array($data['participants']) ? $data['participants'] : [];
$notifyDiscord = !empty($data['notifyDiscord']);

if (mb_strlen($title) < 3) {
    api_send_response('error', null, 'Titel muss mindestens 3 Zeichen lang sein', 422);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $start)) {
    api_send_response('error', null, 'Ungültiges Startdatum', 422);
}

$startDt = new DateTime($start);
if ($startDt < new DateTime()) {
    api_send_response('error', null, 'Startzeit muss in der Zukunft liegen', 422);
}

if ($end !== null && $end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $end)) {
    api_send_response('error', null, 'Ungültiges Enddatum', 422);
}

if (!in_array($paidBy, ['pool', 'private'], true)) {
    api_send_response('error', null, 'Ungültige Kostenstelle', 422);
}

if ($paidBy === 'pool') {
    if ($cost <= 0) {
        api_send_response('error', null, 'Kosten müssen > 0 sein, wenn die Kasse zahlt', 422);
    }
    $overview = api_get_cash_overview($mysqli);
    if ($overview['available'] < $cost) {
        api_send_response('error', null, 'Kasse deckt die Kosten nicht', 409);
    }
}

$creatorId = api_current_user_id();
if ($creatorId === null) {
    api_send_response('error', null, 'Nutzer nicht gefunden', 400);
}

$stmt = $mysqli->prepare(
    'INSERT INTO events (title, description, start, end, location, cost, paid_by, created_by) VALUES (?,?,?,?,?,?,?,?)'
);
if (!$stmt) {
    api_send_response('error', null, 'Event konnte nicht erstellt werden', 500);
}

$endValue = $end ?: null;
$stmt->bind_param('sssssdsi', $title, $description, $start, $endValue, $location, $cost, $paidBy, $creatorId);

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Event konnte nicht erstellt werden', 500);
}

$eventId = $stmt->insert_id;
$stmt->close();

if (!empty($participants)) {
    $stmt = $mysqli->prepare('INSERT INTO event_participants (event_id, member_id, state, availability) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state), availability=VALUES(availability)');
    if ($stmt) {
        foreach ($participants as $participant) {
            if (!is_array($participant)) {
                continue;
            }
            $memberId = isset($participant['memberId']) ? api_safe_int($participant['memberId']) : null;
            $state = isset($participant['state']) ? strtolower(trim((string) $participant['state'])) : 'pending';
            $availability = isset($participant['availability']) ? strtolower(trim((string) $participant['availability'])) : 'free';
            if ($memberId === null) {
                continue;
            }
            if (!in_array($state, ['yes', 'no', 'pending'], true)) {
                $state = 'pending';
            }
            if (!in_array($availability, ['free', 'vacation', 'shift', 'sick'], true)) {
                $availability = 'free';
            }
            $stmt->bind_param('iiss', $eventId, $memberId, $state, $availability);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if ($paidBy === 'pool' && $cost > 0) {
    $status = 'active';
    $stmt = $mysqli->prepare('INSERT INTO reservations_v2 (event_id, amount, status, notes) VALUES (?,?,?,?)');
    if ($stmt) {
        $notes = 'Event-Reservierung';
        $stmt->bind_param('idss', $eventId, $cost, $status, $notes);
        $stmt->execute();
        $stmt->close();
    }
}

if ($notifyDiscord) {
    $payload = json_encode([
        'title' => $title,
        'start' => $start,
        'location' => $location,
        'cost' => $cost,
        'paidBy' => $paidBy,
    ]);
    $stmt = $mysqli->prepare('INSERT INTO discord_notifications (event_id, payload) VALUES (?,?)');
    if ($stmt) {
        $stmt->bind_param('is', $eventId, $payload);
        $stmt->execute();
        $stmt->close();
    }
}

api_send_response('success', ['eventId' => (int) $eventId]);

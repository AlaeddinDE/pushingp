<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$userParam = $_GET['userId'] ?? 'me';
$targetId = api_current_user_id();

if ($userParam !== 'me') {
    $candidate = api_safe_int($userParam);
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

$range = $_GET['range'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $range)) {
    api_send_response('error', null, 'Ungültiger Zeitraum', 400);
}

$start = DateTime::createFromFormat('Y-m-d', $range . '-01');
if (!$start) {
    api_send_response('error', null, 'Ungültiger Zeitraum', 400);
}
$end = clone $start;
$end->modify('last day of this month');

$stmt = $mysqli->prepare(
    'SELECT id, shift_date, type, start_time, end_time FROM shifts WHERE member_id=? AND shift_date BETWEEN ? AND ? ORDER BY shift_date ASC'
);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$startStr = $start->format('Y-m-d');
$endStr = $end->format('Y-m-d');
$stmt->bind_param('iss', $targetId, $startStr, $endStr);

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_result($id, $shiftDate, $type, $startTime, $endTime);
$items = [];
while ($stmt->fetch()) {
    $items[] = [
        'id' => (int) $id,
        'date' => $shiftDate,
        'type' => $type,
        'start' => $startTime,
        'end' => $endTime,
    ];
}
$stmt->close();

api_send_response('success', ['shifts' => $items]);

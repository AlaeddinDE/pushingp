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

$stmt = $conn->prepare('SELECT id, start_date, end_date FROM vacations WHERE member_id=? ORDER BY start_date ASC');
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_param('i', $targetId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_result($id, $startDate, $endDate);
$items = [];
while ($stmt->fetch()) {
    $items[] = [
        'id' => (int) $id,
        'start' => $startDate,
        'end' => $endDate,
    ];
}
$stmt->close();

api_send_response('success', ['vacations' => $items]);

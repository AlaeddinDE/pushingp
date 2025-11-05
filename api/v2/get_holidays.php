<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$region = isset($_GET['region']) ? trim((string) $_GET['region']) : 'NRW';

$stmt = $mysqli->prepare('SELECT holiday_date, name FROM holidays_cache WHERE YEAR(holiday_date)=? AND region=? ORDER BY holiday_date ASC');
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_param('is', $year, $region);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_result($date, $name);
$items = [];
while ($stmt->fetch()) {
    $items[] = [
        'date' => $date,
        'name' => $name,
    ];
}
$stmt->close();

api_send_response('success', ['holidays' => $items]);

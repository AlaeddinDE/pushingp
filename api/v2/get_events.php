<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$range = isset($_GET['range']) ? strtolower(trim((string) $_GET['range'])) : 'upcoming';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$now = (new DateTime())->format('Y-m-d H:i:s');

if ($range === 'past') {
    $countSql = "SELECT COUNT(*) FROM events e WHERE e.status!='canceled' AND e.start < ?";
    $dataSql = "SELECT e.id, e.title, COALESCE(e.description,''), e.start, COALESCE(e.end,''), COALESCE(e.location,''),
                       COALESCE(e.cost,0), e.paid_by, e.created_by, e.status, e.created_at,
                       SUM(CASE WHEN ep.state='yes' THEN 1 ELSE 0 END) AS yes_count,
                       SUM(CASE WHEN ep.state='pending' THEN 1 ELSE 0 END) AS pending_count,
                       SUM(CASE WHEN ep.state='no' THEN 1 ELSE 0 END) AS no_count
                FROM events e
                LEFT JOIN event_participants ep ON ep.event_id = e.id
                WHERE e.status!='canceled' AND e.start < ?
                GROUP BY e.id, e.title, e.description, e.start, e.end, e.location, e.cost, e.paid_by, e.created_by, e.status, e.created_at
                ORDER BY e.start DESC
                LIMIT ?, ?";
    $dataParams = [$now, $offset, $perPage];
    $dataTypes = 'sii';
} else {
    $countSql = "SELECT COUNT(*) FROM events e WHERE e.status!='canceled' AND e.start >= ?";
    $dataSql = "SELECT e.id, e.title, COALESCE(e.description,''), e.start, COALESCE(e.end,''), COALESCE(e.location,''),
                       COALESCE(e.cost,0), e.paid_by, e.created_by, e.status, e.created_at,
                       SUM(CASE WHEN ep.state='yes' THEN 1 ELSE 0 END) AS yes_count,
                       SUM(CASE WHEN ep.state='pending' THEN 1 ELSE 0 END) AS pending_count,
                       SUM(CASE WHEN ep.state='no' THEN 1 ELSE 0 END) AS no_count
                FROM events e
                LEFT JOIN event_participants ep ON ep.event_id = e.id
                WHERE e.status!='canceled' AND e.start >= ?
                GROUP BY e.id, e.title, e.description, e.start, e.end, e.location, e.cost, e.paid_by, e.created_by, e.status, e.created_at
                ORDER BY e.start ASC
                LIMIT ?, ?";
    $dataParams = [$now, $offset, $perPage];
    $dataTypes = 'sii';
}

$stmt = $mysqli->prepare($countSql);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_param('s', $now);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_result($totalCount);
$stmt->fetch();
$stmt->close();

$stmt = $mysqli->prepare($dataSql);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_param($dataTypes, ...$dataParams);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_result($id, $title, $description, $startAt, $endAt, $location, $cost, $paidBy, $createdBy, $status, $createdAt, $yesCount, $pendingCount, $noCount);

$events = [];
$eventIds = [];
while ($stmt->fetch()) {
    $eventIds[] = (int) $id;
    $events[(int) $id] = [
        'id' => (int) $id,
        'title' => $title,
        'description' => $description,
        'start' => $startAt,
        'end' => $endAt,
        'location' => $location,
        'cost' => (float) $cost,
        'paidBy' => $paidBy,
        'createdBy' => (int) $createdBy,
        'status' => $status,
        'createdAt' => $createdAt,
        'participants' => [
            'yes' => (int) $yesCount,
            'pending' => (int) $pendingCount,
            'no' => (int) $noCount,
        ],
        'people' => [],
    ];
}
$stmt->close();

if (!empty($eventIds)) {
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $types = str_repeat('i', count($eventIds));
    $query = "SELECT ep.event_id, ep.member_id, ep.state, ep.availability, m.name
              FROM event_participants ep
              LEFT JOIN members_v2 m ON m.id = ep.member_id
              WHERE ep.event_id IN ($placeholders)";
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$eventIds);
        if ($stmt->execute()) {
            $stmt->bind_result($eventId, $memberId, $state, $availability, $name);
            while ($stmt->fetch()) {
                $events[(int) $eventId]['people'][] = [
                    'memberId' => (int) $memberId,
                    'name' => $name,
                    'state' => $state,
                    'availability' => $availability,
                ];
            }
        }
        $stmt->close();
    }
}

api_send_response('success', [
    'events' => array_values($events),
    'pagination' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => (int) $totalCount,
        'pages' => $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1,
    ],
]);

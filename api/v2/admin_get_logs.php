<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? max(1, min(200, (int) $_GET['perPage'])) : 50;
$offset = ($page - 1) * $perPage;

$total = 0;
$stmt = $conn->prepare('SELECT COUNT(*) FROM admin_logs');
if ($stmt && $stmt->execute()) {
    $stmt->bind_result($count);
    if ($stmt->fetch()) {
        $total = (int) $count;
    }
    $stmt->close();
}

$stmt = $conn->prepare('SELECT l.id, l.admin_id, m.name, l.action, l.entity_type, l.entity_id, l.payload_hash, l.details, l.created_at FROM admin_logs l LEFT JOIN members_v2 m ON m.id = l.admin_id ORDER BY l.created_at DESC LIMIT ?, ?');
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_param('ii', $offset, $perPage);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_result($id, $adminId, $adminName, $action, $entityType, $entityId, $payloadHash, $details, $createdAt);

$logs = [];
while ($stmt->fetch()) {
    $decoded = json_decode($details, true);
    $logs[] = [
        'id' => (int) $id,
        'adminId' => (int) $adminId,
        'adminName' => $adminName,
        'action' => $action,
        'entityType' => $entityType,
        'entityId' => $entityId,
        'payloadHash' => $payloadHash,
        'details' => is_array($decoded) ? $decoded : null,
        'createdAt' => $createdAt,
    ];
}
$stmt->close();

api_send_response('success', [
    'logs' => $logs,
    'pagination' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ],
]);

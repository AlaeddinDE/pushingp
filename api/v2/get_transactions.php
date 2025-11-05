<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$totalCount = 0;
$stmt = $mysqli->prepare("SELECT COUNT(*) FROM transactions_v2 WHERE status!='storniert'");
if ($stmt && $stmt->execute() && $stmt->bind_result($count) && $stmt->fetch()) {
    $totalCount = (int) $count;
}
if ($stmt) {
    $stmt->close();
}

$stmt = $mysqli->prepare(
    "SELECT t.id, t.type, t.amount, COALESCE(t.reason,''), t.status, t.created_at,
            COALESCE(m.name,''), COALESCE(t.event_id,0), COALESCE(t.metadata,'{}')
     FROM transactions_v2 t
     LEFT JOIN members_v2 m ON m.id = t.member_id
     WHERE t.status!='storniert'
     ORDER BY t.created_at DESC
     LIMIT ?, ?"
);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_param('ii', $offset, $perPage);

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_result($id, $type, $amount, $reason, $status, $createdAt, $memberName, $eventId, $metadataJson);

$transactions = [];
while ($stmt->fetch()) {
    $metadata = json_decode($metadataJson, true);
    if (!is_array($metadata)) {
        $metadata = [];
    }
    $transactions[] = [
        'id' => (int) $id,
        'type' => $type,
        'amount' => (float) $amount,
        'reason' => $reason,
        'status' => $status,
        'createdAt' => $createdAt,
        'member' => $memberName,
        'eventId' => (int) $eventId,
        'metadata' => $metadata,
    ];
}
$stmt->close();

// Statistik: letzte Ausgabe, offene Reservierungen
$lastExpense = null;
$stmt = $mysqli->prepare(
    "SELECT amount, created_at FROM transactions_v2
     WHERE status='gebucht' AND type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig')
     ORDER BY created_at DESC LIMIT 1"
);
if ($stmt && $stmt->execute() && $stmt->bind_result($expenseAmount, $expenseDate) && $stmt->fetch()) {
    $lastExpense = [
        'amount' => (float) $expenseAmount,
        'date' => $expenseDate,
    ];
}
if ($stmt) {
    $stmt->close();
}

$reserved = 0.0;
$stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount),0) FROM reservations_v2 WHERE status='active'");
if ($stmt && $stmt->execute() && $stmt->bind_result($resAmount) && $stmt->fetch()) {
    $reserved = (float) $resAmount;
}
if ($stmt) {
    $stmt->close();
}

api_send_response('success', [
    'items' => $transactions,
    'pagination' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $totalCount,
        'pages' => $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1,
    ],
    'stats' => [
        'lastExpense' => $lastExpense,
        'reserved' => round($reserved, 2),
    ],
]);

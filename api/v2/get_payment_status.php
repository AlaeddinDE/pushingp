<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$requestId = isset($_GET['id']) ? api_safe_int($_GET['id']) : null;
$token = isset($_GET['token']) ? trim((string) $_GET['token']) : null;

if ($requestId === null && $token === null) {
    api_send_response('error', null, 'Parameter id oder token erforderlich', 400);
}

if ($requestId !== null) {
    $stmt = $mysqli->prepare(
        "SELECT id, member_id, amount, status, external_reference, reason, created_at, expires_at, paid_at
         FROM payment_requests WHERE id=?"
    );
    if (!$stmt) {
        api_send_response('error', null, 'Datenbankfehler', 500);
    }
    $stmt->bind_param('i', $requestId);
} else {
    $stmt = $mysqli->prepare(
        "SELECT id, member_id, amount, status, external_reference, reason, created_at, expires_at, paid_at
         FROM payment_requests WHERE token=?"
    );
    if (!$stmt) {
        api_send_response('error', null, 'Datenbankfehler', 500);
    }
    $stmt->bind_param('s', $token);
}

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Zahlungsanfrage nicht gefunden', 404);
}

$stmt->bind_result($id, $memberId, $amount, $status, $external, $reason, $createdAt, $expiresAt, $paidAt);
if (!$stmt->fetch()) {
    $stmt->close();
    api_send_response('error', null, 'Zahlungsanfrage nicht gefunden', 404);
}
$stmt->close();

$memberId = (int) $memberId;
$currentUserId = api_current_user_id();
$isOwner = $currentUserId !== null && $currentUserId === $memberId;

if (!$isOwner && !api_user_has_role($user, 'kassenaufsicht') && !api_user_has_role($user, 'admin')) {
    api_send_response('error', null, 'Keine Berechtigung', 403);
}

api_send_response('success', [
    'payment' => [
        'id' => (int) $id,
        'memberId' => $memberId,
        'amount' => (float) $amount,
        'status' => $status,
        'externalReference' => $external,
        'reason' => $reason,
        'createdAt' => $createdAt,
        'expiresAt' => $expiresAt,
        'paidAt' => $paidAt,
    ],
]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$admin = api_require_role(['kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$requestId = isset($data['id']) ? api_safe_int($data['id']) : null;
$status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : '';
$external = isset($data['externalReference']) ? trim((string) $data['externalReference']) : null;

if ($requestId === null || !in_array($status, ['pending', 'paid', 'failed', 'cancelled'], true)) {
    api_send_response('error', null, 'Ungültige Parameter', 422);
}

$stmt = $conn->prepare(
    "SELECT member_id, amount, status, reason FROM payment_requests WHERE id=? LIMIT 1"
);
if (!$stmt) {
    api_send_response('error', null, 'Zahlungsanfrage existiert nicht', 404);
}

$stmt->bind_param('i', $requestId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Zahlungsanfrage existiert nicht', 404);
}

$stmt->bind_result($memberId, $amount, $currentStatus, $reason);
if (!$stmt->fetch()) {
    $stmt->close();
    api_send_response('error', null, 'Zahlungsanfrage existiert nicht', 404);
}
$stmt->close();

$memberId = (int) $memberId;

$now = (new DateTime())->format('Y-m-d H:i:s');

$stmt = $conn->prepare(
    "UPDATE payment_requests SET status=?, external_reference=?, paid_at=IF(?='paid', ?, paid_at) WHERE id=?"
);
if (!$stmt) {
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}

$stmt->bind_param('ssssi', $status, $external, $status, $now, $requestId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}
$stmt->close();

if ($status === 'paid' && $currentStatus !== 'paid') {
    // Prüfen, ob bereits eine Buchung existiert
    $stmt = $conn->prepare('SELECT 1 FROM transactions_v2 WHERE payment_request_id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } else {
        $exists = false;
    }

    if (!$exists) {
        $type = 'Einzahlung';
        $statusBooked = 'gebucht';
        $metadata = json_encode(['source' => 'payment_request']);
        $stmt = $conn->prepare(
            'INSERT INTO transactions_v2 (member_id, type, amount, reason, status, payment_request_id, metadata) VALUES (?,?,?,?,?,?,?)'
        );
        if ($stmt) {
            $stmt->bind_param('isdssis', $memberId, $type, $amount, $reason, $statusBooked, $requestId, $metadata);
            $stmt->execute();
            $stmt->close();
        }
    }
}

api_log_admin_action($conn, api_current_user_id() ?? 0, 'update_payment_status', 'payment_request', (string) $requestId, [
    'status' => $status,
]);

api_send_response('success', ['status' => $status]);

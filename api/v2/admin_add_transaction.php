<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$memberId = isset($data['memberId']) ? api_safe_int($data['memberId']) : null;
$type = isset($data['type']) ? trim((string) $data['type']) : '';
$amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
$reason = isset($data['reason']) ? trim((string) $data['reason']) : '';
$status = isset($data['status']) ? trim((string) $data['status']) : 'gebucht';
$eventId = isset($data['eventId']) ? api_safe_int($data['eventId']) : null;

$allowedTypes = ['Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Ausgleich','Korrektur','Umbuchung'];
$allowedStatus = ['gebucht','gesperrt','storniert'];

if ($memberId === null || $amount <= 0 || !in_array($type, $allowedTypes, true) || !in_array($status, $allowedStatus, true)) {
    api_send_response('error', null, 'Ungültige Angaben', 422);
}

if (!api_member_exists($conn, $memberId)) {
    api_send_response('error', null, 'Mitglied existiert nicht', 404);
}

$stmt = $conn->prepare('INSERT INTO transactions_v2 (member_id, type, amount, reason, status, event_id, metadata) VALUES (?,?,?,?,?,NULLIF(?,0),?)');
if (!$stmt) {
    api_send_response('error', null, 'Transaktion konnte nicht erstellt werden', 500);
}
$metadata = json_encode(['createdBy' => api_current_user_id(), 'source' => 'admin_panel']);
$eventIdParam = $eventId ?? 0;
$stmt->bind_param('isdssis', $memberId, $type, $amount, $reason, $status, $eventIdParam, $metadata);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Transaktion konnte nicht erstellt werden', 500);
}
$newId = $stmt->insert_id;
$stmt->close();

api_log_admin_action($conn, api_current_user_id() ?? 0, 'admin_add_transaction', 'transaction', (string) $newId, $data);

api_send_response('success', ['id' => (int) $newId]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$label = isset($data['label']) ? trim((string) $data['label']) : ('backup-' . date('Ymd-His'));

$stmt = $conn->prepare('INSERT INTO admin_logs (admin_id, action, entity_type, entity_id, payload_hash, details) VALUES (?,?,?,?,?,?)');
if (!$stmt) {
    api_send_response('error', null, 'Backup konnte nicht protokolliert werden', 500);
}
$adminId = api_current_user_id() ?? 0;
$payload = json_encode(['label' => $label, 'initiatedAt' => date(DATE_ATOM)]);
$hash = hash('sha256', $payload);
$entityType = 'backup';
$entityId = '';
$action = 'admin_backup';
$stmt->bind_param('isssss', $adminId, $action, $entityType, $entityId, $hash, $payload);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Backup konnte nicht protokolliert werden', 500);
}
$stmt->close();

api_send_response('success', ['queued' => true, 'label' => $label]);

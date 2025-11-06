<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$admin = api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$memberId = isset($data['id']) ? api_safe_int($data['id']) : null;
$lock = isset($data['lock']) ? (bool) $data['lock'] : true;
$reason = isset($data['reason']) ? trim((string) $data['reason']) : '';

if ($memberId === null) {
    api_send_response('error', null, 'Mitglied-ID fehlt', 422);
}

if (api_current_user_id() === $memberId) {
    api_send_response('error', null, 'Du kannst dich nicht selbst sperren', 403);
}

$stmt = $conn->prepare('UPDATE members_v2 SET is_locked=? WHERE id=?');
if (!$stmt) {
    api_send_response('error', null, 'Aktion fehlgeschlagen', 500);
}
$flag = $lock ? 1 : 0;
$stmt->bind_param('ii', $flag, $memberId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Aktion fehlgeschlagen', 500);
}
$stmt->close();

api_log_admin_action($conn, api_current_user_id() ?? 0, 'admin_lock_user', 'member', (string) $memberId, [
    'locked' => $lock,
    'reason' => $reason,
]);

api_send_response('success', ['locked' => $lock]);

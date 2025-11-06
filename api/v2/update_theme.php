<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$theme = isset($data['theme']) ? strtolower(trim((string) $data['theme'])) : '';
if (!in_array($theme, ['dark', 'light'], true)) {
    api_send_response('error', null, 'Ungültiges Theme', 422);
}

$memberId = api_current_user_id();
if ($memberId === null) {
    api_send_response('error', null, 'Nutzer nicht gefunden', 400);
}

$stmt = $conn->prepare(
    'INSERT INTO user_settings (member_id, theme) VALUES (?,?)
     ON DUPLICATE KEY UPDATE theme=VALUES(theme), updated_at=CURRENT_TIMESTAMP'
);
if (!$stmt) {
    api_send_response('error', null, 'Theme konnte nicht gespeichert werden', 500);
}
$stmt->bind_param('is', $memberId, $theme);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Theme konnte nicht gespeichert werden', 500);
}
$stmt->close();

api_send_response('success', ['theme' => $theme]);

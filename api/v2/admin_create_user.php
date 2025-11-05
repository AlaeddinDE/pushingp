<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$name = isset($data['name']) ? trim((string) $data['name']) : '';
$email = isset($data['email']) ? trim((string) $data['email']) : null;
$discord = isset($data['discordTag']) ? trim((string) $data['discordTag']) : null;
$roles = isset($data['roles']) && is_array($data['roles']) ? api_roles_to_string($data['roles']) : 'member';
$joinedAt = isset($data['joinedAt']) ? trim((string) $data['joinedAt']) : date('Y-m-d');

if (mb_strlen($name) < 2) {
    api_send_response('error', null, 'Name zu kurz', 422);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $joinedAt)) {
    api_send_response('error', null, 'Ungültiges Eintrittsdatum', 422);
}

$stmt = $mysqli->prepare('INSERT INTO members_v2 (name, email, discord_tag, roles, joined_at, status) VALUES (?,?,?,?,?,\'active\')');
if (!$stmt) {
    api_send_response('error', null, 'Mitglied konnte nicht angelegt werden', 500);
}
$stmt->bind_param('sssss', $name, $email, $discord, $roles, $joinedAt);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Mitglied konnte nicht angelegt werden', 500);
}
$newId = $stmt->insert_id;
$stmt->close();

api_log_admin_action($mysqli, api_current_user_id() ?? 0, 'admin_create_user', 'member', (string) $newId, $data);

api_send_response('success', ['id' => (int) $newId]);

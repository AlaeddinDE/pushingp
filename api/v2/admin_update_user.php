<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$admin = api_require_role(['admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$memberId = isset($data['id']) ? api_safe_int($data['id']) : null;
if ($memberId === null) {
    api_send_response('error', null, 'Mitglied-ID fehlt', 422);
}

$name = isset($data['name']) ? trim((string) $data['name']) : null;
$email = isset($data['email']) ? trim((string) $data['email']) : null;
$discord = isset($data['discordTag']) ? trim((string) $data['discordTag']) : null;
$roles = isset($data['roles']) && is_array($data['roles']) ? api_roles_to_string($data['roles']) : null;
$status = isset($data['status']) ? trim((string) $data['status']) : null;
$locked = isset($data['locked']) ? (bool) $data['locked'] : null;

$stmt = $mysqli->prepare('SELECT name FROM members_v2 WHERE id=?');
if (!$stmt) {
    api_send_response('error', null, 'Mitglied nicht gefunden', 404);
}
$stmt->bind_param('i', $memberId);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Mitglied nicht gefunden', 404);
}
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    api_send_response('error', null, 'Mitglied nicht gefunden', 404);
}
$stmt->close();

$fields = [];
$params = [];
$types = '';

if ($name !== null && $name !== '') {
    $fields[] = 'name=?';
    $params[] = $name;
    $types .= 's';
}
if ($email !== null) {
    $fields[] = 'email=?';
    $params[] = $email;
    $types .= 's';
}
if ($discord !== null) {
    $fields[] = 'discord_tag=?';
    $params[] = $discord;
    $types .= 's';
}
if ($roles !== null) {
    $fields[] = 'roles=?';
    $params[] = $roles;
    $types .= 's';
}
if ($status !== null) {
    $fields[] = 'status=?';
    $params[] = $status;
    $types .= 's';
}
if ($locked !== null) {
    $fields[] = 'is_locked=?';
    $params[] = $locked ? 1 : 0;
    $types .= 'i';
}

if (empty($fields)) {
    api_send_response('success', ['updated' => false]);
}

$types .= 'i';
$params[] = $memberId;

$query = 'UPDATE members_v2 SET ' . implode(', ', $fields) . ' WHERE id=?';
$stmt = $mysqli->prepare($query);
if (!$stmt) {
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Aktualisierung fehlgeschlagen', 500);
}
$stmt->close();

api_log_admin_action($mysqli, api_current_user_id() ?? 0, 'admin_update_user', 'member', (string) $memberId, $data);

api_send_response('success', ['updated' => true]);

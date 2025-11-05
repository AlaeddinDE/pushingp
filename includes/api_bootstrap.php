<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

const API_ALLOWED_ROLES = ['member', 'planer', 'kassenaufsicht', 'admin'];

function api_send_response(string $status, ?array $data = null, ?string $error = null, int $httpCode = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);

    $payload = [
        'status' => $status,
        'error' => $error,
    ];

    if ($data !== null) {
        $payload['data'] = $data;
    }

    echo json_encode($payload);
    exit;
}

function api_require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        api_send_response('error', null, 'Ungültige HTTP-Methode', 405);
    }
}

function api_get_session_user(): ?array
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }

    $user = $_SESSION['user'];
    if (!isset($user['roles'])) {
        $user['roles'] = ['member'];
    } elseif (is_string($user['roles'])) {
        $roles = array_filter(array_map('trim', explode(',', $user['roles'])));
        $user['roles'] = $roles ?: ['member'];
    }

    return $user;
}

function api_require_login(): array
{
    $user = api_get_session_user();
    if ($user === null) {
        api_send_response('error', null, 'Nicht angemeldet', 401);
    }
    return $user;
}

function api_user_has_role(array $user, string $role): bool
{
    if (!in_array($role, API_ALLOWED_ROLES, true)) {
        return false;
    }

    $roles = $user['roles'] ?? [];
    if (is_string($roles)) {
        $roles = array_filter(array_map('trim', explode(',', $roles)));
    }

    return in_array($role, $roles, true);
}

function api_require_role(array $requiredRoles): array
{
    $user = api_require_login();
    foreach ($requiredRoles as $role) {
        if (api_user_has_role($user, $role)) {
            return $user;
        }
    }

    api_send_response('error', null, 'Keine Berechtigung', 403);
    return $user;
}

function api_current_user_id(): ?int
{
    $user = api_get_session_user();
    if ($user === null) {
        return null;
    }
    if (isset($user['id'])) {
        return (int) $user['id'];
    }
    if (isset($user['member_id'])) {
        return (int) $user['member_id'];
    }
    return null;
}

function api_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_send_response('error', null, 'Ungültiges JSON', 400);
    }

    return $decoded;
}

function api_enforce_csrf(?array $payload = null): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return;
    }

    if (!isset($_SESSION['csrf_token'])) {
        api_send_response('error', null, 'CSRF-Token fehlt', 419);
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($token === null && isset($_POST['csrf_token'])) {
        $token = (string) $_POST['csrf_token'];
    }
    if ($token === null && $payload !== null && isset($payload['csrf_token'])) {
        $token = (string) $payload['csrf_token'];
    }

    if ($token === null || !hash_equals($_SESSION['csrf_token'], $token)) {
        api_send_response('error', null, 'Ungültiges CSRF-Token', 419);
    }
}

function api_rate_limit(string $key, int $limit, int $windowSeconds): void
{
    $now = time();
    $storeKey = 'api_rl_' . $key;

    if (function_exists('apcu_fetch')) {
        $entry = apcu_fetch($storeKey);
        if (!is_array($entry) || $entry['expires'] < $now) {
            $entry = ['count' => 0, 'expires' => $now + $windowSeconds];
        }
        if ($entry['count'] >= $limit) {
            api_send_response('error', null, 'Zu viele Anfragen, bitte später erneut versuchen', 429);
        }
        $entry['count']++;
        apcu_store($storeKey, $entry, $windowSeconds);
        return;
    }

    if (!isset($_SESSION['__rate_limit']) || !is_array($_SESSION['__rate_limit'])) {
        $_SESSION['__rate_limit'] = [];
    }

    $entry = $_SESSION['__rate_limit'][$storeKey] ?? ['count' => 0, 'expires' => $now + $windowSeconds];
    if ($entry['expires'] < $now) {
        $entry = ['count' => 0, 'expires' => $now + $windowSeconds];
    }

    if ($entry['count'] >= $limit) {
        api_send_response('error', null, 'Zu viele Anfragen, bitte später erneut versuchen', 429);
    }

    $entry['count']++;
    $_SESSION['__rate_limit'][$storeKey] = $entry;
}

function api_hash_ip(string $ip): string
{
    return hash('sha256', $ip . '::pushingp');
}

function api_log_admin_action(mysqli $mysqli, int $adminId, string $action, string $entityType, ?string $entityId, array $payload = []): void
{
    $jsonPayload = json_encode($payload);
    $payloadHash = hash('sha256', $jsonPayload ?? '');

    $stmt = $mysqli->prepare('INSERT INTO admin_logs (admin_id, action, entity_type, entity_id, payload_hash, details) VALUES (?,?,?,?,?,?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isssss', $adminId, $action, $entityType, $entityId, $payloadHash, $jsonPayload);
    $stmt->execute();
    $stmt->close();
}

function api_safe_int(mixed $value, ?int $default = null): ?int
{
    if ($value === null) {
        return $default;
    }
    if (is_int($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int) $value;
    }
    return $default;
}

function api_validate_roles(array $roles): array
{
    $valid = [];
    foreach ($roles as $role) {
        if (in_array($role, API_ALLOWED_ROLES, true)) {
            $valid[] = $role;
        }
    }
    if (empty($valid)) {
        $valid[] = 'member';
    }
    return array_values(array_unique($valid));
}

function api_roles_to_string(array $roles): string
{
    return implode(',', api_validate_roles($roles));
}

function api_member_exists(mysqli $mysqli, int $memberId): bool
{
    $stmt = $mysqli->prepare('SELECT 1 FROM members_v2 WHERE id=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function api_get_cash_overview(mysqli $mysqli): array
{
    $total = 0.0;
    $reserved = 0.0;

    $stmt = $mysqli->prepare(
        "SELECT COALESCE(SUM(CASE 
            WHEN type='Einzahlung' THEN amount
            WHEN type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -amount
            WHEN type IN ('Korrektur','Ausgleich') THEN amount
            ELSE 0 END),0)
         FROM transactions_v2
         WHERE status='gebucht'"
    );
    if ($stmt && $stmt->execute()) {
        $stmt->bind_result($sum);
        if ($stmt->fetch()) {
            $total = (float) $sum;
        }
        $stmt->close();
    } elseif ($stmt) {
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount),0) FROM reservations_v2 WHERE status='active'");
    if ($stmt && $stmt->execute()) {
        $stmt->bind_result($res);
        if ($stmt->fetch()) {
            $reserved = (float) $res;
        }
        $stmt->close();
    } elseif ($stmt) {
        $stmt->close();
    }

    return [
        'balance' => round($total, 2),
        'reserved' => round($reserved, 2),
        'available' => round($total - $reserved, 2),
    ];
}

?>

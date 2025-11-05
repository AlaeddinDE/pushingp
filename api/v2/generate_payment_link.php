<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

$user = api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
$reason = isset($data['reason']) ? trim((string) $data['reason']) : '';
$expiresIn = isset($data['expiresIn']) ? (int) $data['expiresIn'] : 3600;

if ($amount <= 0) {
    api_send_response('error', null, 'Betrag muss größer als 0 sein', 422);
}

$memberId = api_current_user_id();
if ($memberId === null) {
    api_send_response('error', null, 'Mitglied nicht gefunden', 400);
}

$token = bin2hex(random_bytes(16));
$expiresAt = (new DateTime())->modify('+' . $expiresIn . ' seconds')->format('Y-m-d H:i:s');

$stmt = $mysqli->prepare(
    'INSERT INTO payment_requests (member_id, amount, status, token, reason, expires_at) VALUES (?,?,?,?,?,?)'
);
if (!$stmt) {
    api_send_response('error', null, 'Konnte Zahlungslink nicht erzeugen', 500);
}

$status = 'pending';
$stmt->bind_param('idssss', $memberId, $amount, $status, $token, $reason, $expiresAt);

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Konnte Zahlungslink nicht erzeugen', 500);
}

$requestId = $stmt->insert_id;
$stmt->close();

$paypointBase = 'https://paypoint.example/pay';
$stmt = $mysqli->prepare("SELECT value FROM settings_v2 WHERE key_name='paypoint_base_url' LIMIT 1");
if ($stmt && $stmt->execute()) {
    $stmt->bind_result($url);
    if ($stmt->fetch() && filter_var($url, FILTER_VALIDATE_URL)) {
        $paypointBase = $url;
    }
    $stmt->close();
}

$link = rtrim($paypointBase, '/') . '?token=' . urlencode($token);

api_send_response('success', [
    'payment' => [
        'id' => (int) $requestId,
        'token' => $token,
        'link' => $link,
        'expiresAt' => $expiresAt,
        'amount' => round($amount, 2),
        'reason' => $reason,
    ],
]);

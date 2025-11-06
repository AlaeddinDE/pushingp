<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_method('POST');

$data = api_json_input();
api_enforce_csrf($data);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
api_rate_limit('feedback_' . $ip, 3, 300);

$name = isset($data['name']) ? trim((string) $data['name']) : null;
$message = isset($data['message']) ? trim((string) $data['message']) : '';

if (mb_strlen($message) < 10) {
    api_send_response('error', null, 'Nachricht ist zu kurz (mindestens 10 Zeichen)', 422);
}

$ipHash = api_hash_ip($ip);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmt = $conn->prepare('INSERT INTO feedback_entries (name, message, ip_hash, user_agent) VALUES (?,?,?,?)');
if (!$stmt) {
    api_send_response('error', null, 'Feedback konnte nicht gespeichert werden', 500);
}

$stmt->bind_param('ssss', $name, $message, $ipHash, $userAgent);

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Feedback konnte nicht gespeichert werden', 500);
}

$stmt->close();

api_send_response('success', ['message' => 'Danke! Wir melden uns bei Bedarf.']);

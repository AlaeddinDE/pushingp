<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

function send_response(string $status, ?array $data = null, ?string $error = null, int $httpCode = 200): void {
    header('Content-Type: application/json');
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

function read_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response('error', null, 'POST erforderlich', 405);
}

if (!($isAdmin ?? false)) {
    send_response('error', null, 'Zugriff verweigert', 403);
}

$input = read_input();
$memberId = isset($input['member_id']) ? (int) $input['member_id'] : 0;
$type = isset($input['type']) ? trim((string) $input['type']) : '';
$amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
$reason = isset($input['reason']) ? trim((string) $input['reason']) : '';

$allowedTypes = ['Einzahlung', 'Auszahlung', 'Gutschrift', 'Schaden'];
if ($memberId <= 0 || $amount <= 0 || !in_array($type, $allowedTypes, true)) {
    send_response('error', null, 'Ungültige Parameter', 400);
}

$memberStmt = $mysqli->prepare('SELECT name FROM members_v2 WHERE id = ? LIMIT 1');
if (!$memberStmt) {
    send_response('error', null, 'Datenbankfehler bei Mitgliedsabfrage', 500);
}
$memberStmt->bind_param('i', $memberId);
if (!$memberStmt->execute()) {
    $memberStmt->close();
    send_response('error', null, 'Fehler beim Prüfen des Mitglieds', 500);
}
if (!$memberStmt->bind_result($memberName)) {
    $memberStmt->close();
    send_response('error', null, 'Fehler beim Lesen des Mitglieds', 500);
}
if (!$memberStmt->fetch()) {
    $memberStmt->close();
    send_response('error', null, 'Mitglied nicht gefunden', 404);
}
$memberStmt->close();

$reasonParam = $reason !== '' ? $reason : null;
$insertStmt = $mysqli->prepare('INSERT INTO transactions_v2 (member_id, type, amount, reason) VALUES (?, ?, ?, ?)');
if (!$insertStmt) {
    send_response('error', null, 'Konnte Transaktion nicht vorbereiten', 500);
}
$insertStmt->bind_param('isds', $memberId, $type, $amount, $reasonParam);
if (!$insertStmt->execute()) {
    $insertStmt->close();
    send_response('error', null, 'Transaktion konnte nicht gespeichert werden', 500);
}
$transactionId = $insertStmt->insert_id;
$insertStmt->close();

send_response('success', [
    'transaction_id' => $transactionId,
    'member_id' => $memberId,
    'amount' => $amount,
    'type' => $type,
    'reason' => $reasonParam,
]);

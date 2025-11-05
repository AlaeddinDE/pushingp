<?php
require_once __DIR__ . '/../../includes/db.php';

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

$stmt = $mysqli->prepare(
    'SELECT member_id, name, real_balance FROM v2_member_real_balance ORDER BY name ASC'
);
if (!$stmt) {
    send_response('error', null, 'Datenbankfehler beim Vorbereiten der Abfrage', 500);
}

if (!$stmt->execute()) {
    $stmt->close();
    send_response('error', null, 'Datenbankfehler beim Ausführen der Abfrage', 500);
}

if (!$stmt->bind_result($memberId, $name, $realBalance)) {
    $stmt->close();
    send_response('error', null, 'Datenbankfehler beim Lesen der Ergebnisse', 500);
}

$balances = [];
while ($stmt->fetch()) {
    $balances[] = [
        'member_id' => (int) $memberId,
        'name' => $name,
        'real_balance' => (float) $realBalance,
    ];
}

$stmt->close();

send_response('success', ['members' => $balances], null, 200);

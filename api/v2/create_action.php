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
$type = isset($input['type']) ? trim((string) $input['type']) : '';
$totalAmount = isset($input['total_amount']) ? (float) $input['total_amount'] : 0.0;
$reason = isset($input['reason']) ? trim((string) $input['reason']) : '';

$allowedTypes = ['Einzahlung', 'Schaden'];
if (!in_array($type, $allowedTypes, true)) {
    send_response('error', null, 'Ungültiger Transaktionstyp', 400);
}

$totalAmount = round($totalAmount, 2);
if ($totalAmount <= 0) {
    send_response('error', null, 'Betrag muss größer als 0 sein', 400);
}

$membersStmt = $mysqli->prepare("SELECT id, name FROM members_v2 WHERE status = 'active' ORDER BY name ASC, id ASC");
if (!$membersStmt) {
    send_response('error', null, 'Konnte Mitgliederliste nicht laden', 500);
}

if (!$membersStmt->execute()) {
    $membersStmt->close();
    send_response('error', null, 'Fehler beim Laden der Mitgliederliste', 500);
}

if (!$membersStmt->bind_result($memberId, $memberName)) {
    $membersStmt->close();
    send_response('error', null, 'Fehler beim Lesen der Mitgliederliste', 500);
}

$members = [];
while ($membersStmt->fetch()) {
    $members[] = ['id' => (int) $memberId, 'name' => $memberName];
}
$membersStmt->close();

$memberCount = count($members);
if ($memberCount === 0) {
    send_response('error', null, 'Keine aktiven Mitglieder zum Verteilen gefunden', 409);
}

$totalCents = (int) round($totalAmount * 100);
if ($totalCents <= 0) {
    send_response('error', null, 'Verteilbarer Betrag ist zu klein', 400);
}

$baseShare = intdiv($totalCents, $memberCount);
$remainder = $totalCents - ($baseShare * $memberCount);

$shares = [];
$allocatedTotal = 0.0;
for ($i = 0; $i < $memberCount; $i++) {
    $shareCents = $baseShare;
    if ($remainder > 0) {
        $shareCents++;
        $remainder--;
    }
    $shareValue = $shareCents / 100;
    $shares[$i] = $shareValue;
    $allocatedTotal += $shareValue;
}

$reasonText = $reason !== '' ? $reason : null;
$typeParam = $type;
$memberParam = 0;
$amountParam = 0.0;

if (!$mysqli->begin_transaction()) {
    send_response('error', null, 'Konnte Datenbanktransaktion nicht starten', 500);
}

$insertStmt = $mysqli->prepare('INSERT INTO transactions_v2 (member_id, type, amount, reason) VALUES (?, ?, ?, ?)');
if (!$insertStmt) {
    $mysqli->rollback();
    send_response('error', null, 'Transaktionen konnten nicht vorbereitet werden', 500);
}

$insertStmt->bind_param('isds', $memberParam, $typeParam, $amountParam, $reasonText);

$createdEntries = [];
for ($idx = 0; $idx < $memberCount; $idx++) {
    $memberParam = $members[$idx]['id'];
    $amountParam = $shares[$idx];
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        $mysqli->rollback();
        send_response('error', null, 'Transaktionen konnten nicht gespeichert werden', 500);
    }
    $createdEntries[] = [
        'member_id' => $memberParam,
        'amount' => $amountParam,
        'type' => $type,
    ];
}

$insertStmt->close();
if (!$mysqli->commit()) {
    $mysqli->rollback();
    send_response('error', null, 'Konnte Datenbanktransaktion nicht abschließen', 500);
}

send_response('success', [
    'type' => $type,
    'total_amount' => $totalAmount,
    'total_allocated' => round($allocatedTotal, 2),
    'member_count' => $memberCount,
    'reason' => $reasonText,
    'entries' => $createdEntries,
]);

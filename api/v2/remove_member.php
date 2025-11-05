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
if ($memberId <= 0) {
    send_response('error', null, 'Ungültige Mitglieds-ID', 400);
}

if (!$mysqli->begin_transaction()) {
    send_response('error', null, 'Konnte Datenbanktransaktion nicht starten', 500);
}

$memberStmt = $mysqli->prepare(
    "SELECT m.name, m.status, COALESCE(rb.real_balance, 0) AS real_balance " .
    "FROM members_v2 m " .
    "LEFT JOIN v2_member_real_balance rb ON rb.member_id = m.id " .
    "WHERE m.id = ? FOR UPDATE"
);
if (!$memberStmt) {
    $mysqli->rollback();
    send_response('error', null, 'Mitglied konnte nicht geladen werden', 500);
}

$memberStmt->bind_param('i', $memberId);
if (!$memberStmt->execute()) {
    $memberStmt->close();
    $mysqli->rollback();
    send_response('error', null, 'Fehler beim Laden des Mitglieds', 500);
}

if (!$memberStmt->bind_result($memberName, $memberStatus, $realBalanceRaw)) {
    $memberStmt->close();
    $mysqli->rollback();
    send_response('error', null, 'Fehler beim Lesen des Mitglieds', 500);
}

if (!$memberStmt->fetch()) {
    $memberStmt->close();
    $mysqli->rollback();
    send_response('error', null, 'Mitglied nicht gefunden', 404);
}
$memberStmt->close();

if ($memberStatus !== 'active') {
    $mysqli->rollback();
    send_response('error', null, 'Mitglied ist nicht aktiv', 409);
}

$realBalance = round((float) $realBalanceRaw, 2);

$settingsStmt = $mysqli->prepare("SELECT value FROM settings_v2 WHERE key_name = 'transfer_on_leave' LIMIT 1");
if (!$settingsStmt) {
    $mysqli->rollback();
    send_response('error', null, 'Einstellung transfer_on_leave fehlt', 500);
}

if (!$settingsStmt->execute()) {
    $settingsStmt->close();
    $mysqli->rollback();
    send_response('error', null, 'Fehler beim Laden der Einstellung transfer_on_leave', 500);
}

if (!$settingsStmt->bind_result($transferSettingValue)) {
    $settingsStmt->close();
    $mysqli->rollback();
    send_response('error', null, 'Fehler beim Lesen der Einstellung transfer_on_leave', 500);
}

$transferSettingValue = $settingsStmt->fetch() ? $transferSettingValue : '1';
$settingsStmt->close();
$transferEnabled = $transferSettingValue === '1';

$updateStmt = $mysqli->prepare("UPDATE members_v2 SET status = 'inactive', left_at = CURRENT_DATE WHERE id = ?");
if (!$updateStmt) {
    $mysqli->rollback();
    send_response('error', null, 'Mitglied konnte nicht aktualisiert werden', 500);
}

$updateStmt->bind_param('i', $memberId);
if (!$updateStmt->execute()) {
    $updateStmt->close();
    $mysqli->rollback();
    send_response('error', null, 'Mitglied konnte nicht deaktiviert werden', 500);
}
$updateStmt->close();

$distribution = [];
$transferredAmount = 0.0;
$transferPerformed = false;

if ($transferEnabled && $realBalance > 0.009) {
    $othersStmt = $mysqli->prepare("SELECT id FROM members_v2 WHERE status = 'active' ORDER BY name ASC, id ASC");
    if (!$othersStmt) {
        $mysqli->rollback();
        send_response('error', null, 'Konnte verbleibende Mitglieder nicht laden', 500);
    }
    if (!$othersStmt->execute()) {
        $othersStmt->close();
        $mysqli->rollback();
        send_response('error', null, 'Fehler beim Laden der verbleibenden Mitglieder', 500);
    }
    if (!$othersStmt->bind_result($otherId)) {
        $othersStmt->close();
        $mysqli->rollback();
        send_response('error', null, 'Fehler beim Lesen der verbleibenden Mitglieder', 500);
    }
    $otherMembers = [];
    while ($othersStmt->fetch()) {
        $otherMembers[] = (int) $otherId;
    }
    $othersStmt->close();

    $otherCount = count($otherMembers);
    if ($otherCount > 0) {
        $totalCents = (int) round($realBalance * 100);
        if ($totalCents > 0) {
            $payoutAmount = $totalCents / 100;
            $baseShare = intdiv($totalCents, $otherCount);
            $remainder = $totalCents - ($baseShare * $otherCount);

            $txStmt = $mysqli->prepare('INSERT INTO transactions_v2 (member_id, type, amount, reason) VALUES (?, ?, ?, ?)');
            if (!$txStmt) {
                $mysqli->rollback();
                send_response('error', null, 'Transaktionsbuchung fehlgeschlagen', 500);
            }

            $memberParam = 0;
            $typeParam = '';
            $amountParam = 0.0;
            $reasonParam = null;
            $txStmt->bind_param('isds', $memberParam, $typeParam, $amountParam, $reasonParam);

            $memberParam = $memberId;
            $typeParam = 'Auszahlung';
            $amountParam = $payoutAmount;
            $reasonParam = 'Austritt – Guthaben verteilt';
            if (!$txStmt->execute()) {
                $txStmt->close();
                $mysqli->rollback();
                send_response('error', null, 'Auszahlung konnte nicht verbucht werden', 500);
            }

            $typeParam = 'Einzahlung';
            $reasonParam = 'Übernahme Guthaben von ' . $memberName;
            foreach ($otherMembers as $index => $otherMemberId) {
                $shareCents = $baseShare;
                if ($remainder > 0) {
                    $shareCents++;
                    $remainder--;
                }
                $shareAmount = $shareCents / 100;
                $memberParam = $otherMemberId;
                $amountParam = $shareAmount;
                if (!$txStmt->execute()) {
                    $txStmt->close();
                    $mysqli->rollback();
                    send_response('error', null, 'Einzahlung konnte nicht verbucht werden', 500);
                }
                $distribution[] = [
                    'member_id' => $otherMemberId,
                    'amount' => $shareAmount,
                ];
                $transferredAmount += $shareAmount;
            }

            $txStmt->close();
            $transferPerformed = true;
        }
    }
}

if (!$mysqli->commit()) {
    $mysqli->rollback();
    send_response('error', null, 'Konnte Datenbanktransaktion nicht abschließen', 500);
}

send_response('success', [
    'member_id' => $memberId,
    'member_name' => $memberName,
    'left_at' => date('Y-m-d'),
    'real_balance_before' => $realBalance,
    'transfer_enabled' => $transferEnabled,
    'transfer_performed' => $transferPerformed,
    'transferred_amount' => round($transferredAmount, 2),
    'distribution' => $distribution,
    'outstanding_debt' => $realBalance < -0.009 ? abs($realBalance) : 0.0,
]);

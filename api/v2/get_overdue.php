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

$settingsStmt = $mysqli->prepare("SELECT value FROM settings_v2 WHERE key_name = 'monthly_fee' LIMIT 1");
if (!$settingsStmt) {
    send_response('error', null, 'Konnte Monatsbeitrag nicht laden', 500);
}

if (!$settingsStmt->execute()) {
    $settingsStmt->close();
    send_response('error', null, 'Fehler beim Lesen der Einstellungen', 500);
}

if (!$settingsStmt->bind_result($monthlyFeeValue)) {
    $settingsStmt->close();
    send_response('error', null, 'Fehler beim Verarbeiten der Einstellungen', 500);
}

if (!$settingsStmt->fetch()) {
    $settingsStmt->close();
    send_response('error', null, 'Monatsbeitrag nicht definiert', 500);
}
$settingsStmt->close();

$monthlyFee = (float) $monthlyFeeValue;
if ($monthlyFee <= 0) {
    send_response('error', null, 'Ungültiger Monatsbeitrag konfiguriert', 500);
}

$membersStmt = $mysqli->prepare(
    "SELECT m.id, m.name, GREATEST(TIMESTAMPDIFF(MONTH, m.joined_at, CURRENT_DATE) + 1, 0) AS months_active, " .
    "COALESCE(rb.real_balance, 0) AS real_balance " .
    "FROM members_v2 m " .
    "LEFT JOIN v2_member_real_balance rb ON rb.member_id = m.id " .
    "WHERE m.status = 'active' " .
    "ORDER BY m.name ASC"
);
if (!$membersStmt) {
    send_response('error', null, 'Datenbankfehler beim Laden der Mitglieder', 500);
}

if (!$membersStmt->execute()) {
    $membersStmt->close();
    send_response('error', null, 'Fehler beim Ermitteln der Rückstände', 500);
}

if (!$membersStmt->bind_result($memberId, $name, $monthsActive, $realBalance)) {
    $membersStmt->close();
    send_response('error', null, 'Fehler beim Lesen der Mitgliedsdaten', 500);
}

$overdueMembers = [];
$totalOutstanding = 0.0;
while ($membersStmt->fetch()) {
    $months = max(0, (int) $monthsActive);
    $balance = (float) $realBalance;
    $expected = round($months * $monthlyFee, 2);
    $missing = round($expected - $balance, 2);

    if ($missing > 0.009) {
        $totalOutstanding += $missing;
        $overdueMembers[] = [
            'member_id' => (int) $memberId,
            'name' => $name,
            'months_active' => $months,
            'expected_contribution' => $expected,
            'real_balance' => $balance,
            'missing_amount' => $missing,
        ];
    }
}
$membersStmt->close();

send_response('success', [
    'monthly_fee' => $monthlyFee,
    'total_outstanding' => round($totalOutstanding, 2),
    'overdue_members' => $overdueMembers,
]);

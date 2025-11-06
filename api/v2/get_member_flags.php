<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

function months_between(string $startDate, ?string $leftAt, string $status): int
{
    $start = new DateTime($startDate);
    $start->modify('first day of this month');

    $current = new DateTime(date('Y-m-01'));

    $lastActive = clone $current;
    $includeCurrent = true;

    if ($leftAt !== null && $leftAt !== '') {
        $leftDate = new DateTime($leftAt);
        $leftMonthStart = (clone $leftDate)->modify('first day of this month');
        if ($leftDate <= $current) {
            $includeCurrent = false;
            $leftMonthStart->modify('-1 month');
            $lastActive = $leftMonthStart;
        }
    }

    if ($status !== 'active') {
        $includeCurrent = false;
    }

    if ($lastActive < $start) {
        return 0;
    }

    $months = (($lastActive->format('Y') - $start->format('Y')) * 12) + ((int) $lastActive->format('n') - (int) $start->format('n'));
    if ($includeCurrent) {
        $months += 1;
    } else {
        $months = max(0, $months + 1);
    }

    return max(0, $months);
}

$monthlyFee = 10.0;
$stmt = $conn->prepare("SELECT value FROM settings_v2 WHERE key_name='monthly_fee' LIMIT 1");
if ($stmt && $stmt->execute()) {
    $stmt->bind_result($feeValue);
    if ($stmt->fetch()) {
        $monthlyFee = (float) $feeValue;
    }
    $stmt->close();
}

$query = "SELECT m.id, m.joined_at, m.left_at, m.status,
                 COALESCE(SUM(CASE
                     WHEN t.type='Einzahlung' THEN t.amount
                     WHEN t.type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -t.amount
                     WHEN t.type IN ('Korrektur','Ausgleich') THEN t.amount
                     ELSE 0 END),0) AS real_balance
          FROM members_v2 m
          LEFT JOIN transactions_v2 t ON t.member_id = m.id AND t.status='gebucht'
          GROUP BY m.id, m.joined_at, m.left_at, m.status";

$stmt = $conn->prepare($query);
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}

if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}

$stmt->bind_result($id, $joinedAt, $leftAt, $status, $realBalance);

$flags = [];
while ($stmt->fetch()) {
    $monthsActive = months_between($joinedAt, $leftAt, $status);
    $expected = round($monthsActive * $monthlyFee, 2);
    $diff = (float) $realBalance - $expected;

    $flag = 'paid';
    if ($diff < -$monthlyFee) {
        $flag = 'overdue';
    } elseif ($diff < 0) {
        $flag = 'open';
    }

    $flags[] = [
        'id' => (int) $id,
        'dues' => $flag,
        'expected' => $expected,
        'realBalance' => round((float) $realBalance, 2),
        'difference' => round($diff, 2),
    ];
}

$stmt->close();

api_send_response('success', ['flags' => $flags, 'monthlyFee' => round($monthlyFee, 2)]);

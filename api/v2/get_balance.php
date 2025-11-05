<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_method('GET');

$historyDays = 30;

$totalBalance = 0.0;
$reservedBalance = 0.0;

// Gesamtkassenstand (nur gebuchte Bewegungen)
$stmt = $mysqli->prepare(
    "SELECT COALESCE(SUM(CASE 
        WHEN type='Einzahlung' THEN amount 
        WHEN type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -amount 
        WHEN type IN ('Korrektur','Ausgleich') THEN amount 
        ELSE 0 END),0)
     FROM transactions_v2
     WHERE status='gebucht'"
);
if ($stmt) {
    if ($stmt->execute() && $stmt->bind_result($sumBalance) && $stmt->fetch()) {
        $totalBalance = (float) $sumBalance;
    }
    $stmt->close();
}

// Reservierungen
$stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount),0) FROM reservations_v2 WHERE status='active'");
if ($stmt) {
    if ($stmt->execute() && $stmt->bind_result($reserved) && $stmt->fetch()) {
        $reservedBalance = (float) $reserved;
    }
    $stmt->close();
}

// Historie: aggregierte Tageswerte der letzten 30 Tage
$history = [];
$stmt = $mysqli->prepare(
    "SELECT DATE(created_at) AS tx_date,
            SUM(CASE 
                WHEN type='Einzahlung' THEN amount 
                WHEN type IN ('Auszahlung','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Umbuchung') THEN -amount 
                WHEN type IN ('Korrektur','Ausgleich') THEN amount 
                ELSE 0 END) AS delta
     FROM transactions_v2
     WHERE status='gebucht' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY tx_date
     ORDER BY tx_date ASC"
);
if ($stmt) {
    $stmt->bind_param('i', $historyDays);
    if ($stmt->execute()) {
        $stmt->bind_result($txDate, $delta);
        $entries = [];
        while ($stmt->fetch()) {
            $entries[] = [
                'date' => $txDate,
                'delta' => (float) $delta,
            ];
        }
        $history = [];
        $runningTotal = $totalBalance;
        $entriesCount = count($entries);
        if ($entriesCount > 0) {
            // Rekonstruiere Verlauf rückwärts
            for ($i = $entriesCount - 1; $i >= 0; $i--) {
                $runningTotal -= $entries[$i]['delta'];
            }
            $runningTotal = round($runningTotal, 2);
            foreach ($entries as $entry) {
                $runningTotal += $entry['delta'];
                $history[] = [
                    'ts' => $entry['date'],
                    'balance' => round($runningTotal, 2),
                ];
            }
        }
    }
    $stmt->close();
}

if (empty($history)) {
    $history[] = [
        'ts' => date('Y-m-d'),
        'balance' => round($totalBalance, 2),
    ];
}

api_send_response('success', [
    'balance' => round($totalBalance, 2),
    'reserved' => round($reservedBalance, 2),
    'available' => round($totalBalance - $reservedBalance, 2),
    'history' => $history,
]);

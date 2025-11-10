<?php
/**
 * API: Kassen-Verlauf der letzten 30 Tage für Stock-Chart
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
secure_session_start();
require_login();

$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$days = min(365, max(1, $days)); // Limit: 1-365 Tage

// Hole tägliche Kassen-Salden
$data = [];

$query = "
    SELECT 
        DATE(t.datum) as tag,
        SUM(CASE 
            WHEN t.typ = 'EINZAHLUNG' THEN t.betrag
            WHEN t.typ IN ('AUSZAHLUNG', 'MONATSBEITRAG', 'SCHADEN', 'GRUPPENAKTION_KASSE') THEN -t.betrag
            ELSE 0
        END) as tages_saldo
    FROM transaktionen t
    WHERE t.status = 'gebucht'
    AND t.datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    AND (t.beschreibung IS NULL OR t.beschreibung NOT LIKE '%Casino%')
    GROUP BY DATE(t.datum)
    ORDER BY tag ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $days);
$stmt->execute();
$result = $stmt->get_result();
$daily_changes = [];

while ($row = $result->fetch_assoc()) {
    $daily_changes[$row['tag']] = floatval($row['tages_saldo']);
}
$stmt->close();

// Hole GESAMTEN Saldo bis vor X Tagen (Startwert)
$start_balance_query = "
    SELECT COALESCE(SUM(CASE 
        WHEN t.typ = 'EINZAHLUNG' THEN t.betrag
        WHEN t.typ IN ('AUSZAHLUNG', 'MONATSBEITRAG', 'SCHADEN', 'GRUPPENAKTION_KASSE') THEN -t.betrag
        ELSE 0
    END), 0) as saldo
    FROM transaktionen t
    WHERE t.status = 'gebucht'
    AND t.datum < DATE_SUB(CURDATE(), INTERVAL ? DAY)
    AND (t.beschreibung IS NULL OR t.beschreibung NOT LIKE '%Casino%')
";

$stmt = $conn->prepare($start_balance_query);
$stmt->bind_param('i', $days);
$stmt->execute();
$stmt->bind_result($cumulative_balance);
$stmt->fetch();
$stmt->close();

// Berechne täglichen Verlauf über X Tage
$start_date = new DateTime("-{$days} days");
$end_date = new DateTime('tomorrow');
$interval = new DateInterval('P1D');
$period = new DatePeriod($start_date, $interval, $end_date);

$chart_data = [];

foreach ($period as $date) {
    $date_str = $date->format('Y-m-d');
    
    // Überspringe zukünftige Tage
    if ($date > new DateTime()) {
        break;
    }
    
    $day_change = $daily_changes[$date_str] ?? 0;
    $cumulative_balance += $day_change;
    
    $chart_data[] = [
        'date' => $date->format('d.m'),
        'full_date' => $date_str,
        'balance' => round($cumulative_balance, 2),
        'change' => round($day_change, 2)
    ];
}

// Statistiken
$current_balance = !empty($chart_data) ? end($chart_data)['balance'] : 0;
$start_balance = !empty($chart_data) ? $chart_data[0]['balance'] : 0;
$change = $current_balance - $start_balance;

// Prozentberechnung: wenn start_balance = 0, aber current > 0, dann 100% Wachstum
if ($start_balance == 0 && $current_balance > 0) {
    $change_percent = 100;
} elseif ($start_balance == 0) {
    $change_percent = 0;
} else {
    $change_percent = ($change / abs($start_balance)) * 100;
}

$high_30d = !empty($chart_data) ? max(array_column($chart_data, 'balance')) : 0;
$low_30d = !empty($chart_data) ? min(array_column($chart_data, 'balance')) : 0;

echo json_encode([
    'status' => 'success',
    'data' => $chart_data,
    'days' => $days,
    'stats' => [
        'current' => $current_balance,
        'change' => $change,
        'change_percent' => round($change_percent, 2),
        'high_30d' => $high_30d,
        'low_30d' => $low_30d,
        'start_balance' => $start_balance
    ]
]);

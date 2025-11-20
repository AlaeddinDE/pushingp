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

// Hole Historie aus balance_history
$history = [];
$stmt = $conn->prepare("SELECT date, balance FROM balance_history WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY date ASC");
$stmt->bind_param('i', $days);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history[$row['date']] = floatval($row['balance']);
}
$stmt->close();

// Hole Startwert (letzter bekannter Wert vor dem Zeitraum)
$last_balance = 0;
$stmt = $conn->prepare("SELECT balance FROM balance_history WHERE date < DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY date DESC LIMIT 1");
$stmt->bind_param('i', $days);
$stmt->execute();
$stmt->bind_result($last_balance);
if (!$stmt->fetch()) {
    // Fallback: Erster Wert im Zeitraum oder 0
    if (!empty($history)) {
        $last_balance = reset($history);
    } else {
        $last_balance = 0;
    }
}
$stmt->close();

// Berechne täglichen Verlauf
$start_date = new DateTime("-{$days} days");
$end_date = new DateTime('tomorrow');
$interval = new DateInterval('P1D');
$period = new DatePeriod($start_date, $interval, $end_date);

$chart_data = [];
$current_val = $last_balance;

foreach ($period as $date) {
    $date_str = $date->format('Y-m-d');
    
    // Überspringe zukünftige Tage
    if ($date > new DateTime()) {
        break;
    }
    
    if (isset($history[$date_str])) {
        $current_val = $history[$date_str];
    }
    
    $chart_data[] = [
        'date' => $date->format('d.m'),
        'full_date' => $date_str,
        'balance' => round($current_val, 2),
        'change' => 0 // Wird gleich berechnet
    ];
}

// Changes berechnen
for ($i = 0; $i < count($chart_data); $i++) {
    if ($i === 0) {
        $chart_data[$i]['change'] = $chart_data[$i]['balance'] - $last_balance;
    } else {
        $chart_data[$i]['change'] = $chart_data[$i]['balance'] - $chart_data[$i-1]['balance'];
    }
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

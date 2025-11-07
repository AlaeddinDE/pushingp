<?php
/**
 * Get Balance API
 * Returns current cash position and history for charts
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

secure_session_start();
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    // Get current balance from view
    $result = $conn->query("SELECT kassenstand_brutto, reserviert, kassenstand_verfuegbar FROM v_kasse_position");
    if ($result && $row = $result->fetch_assoc()) {
        $brutto = floatval($row['kassenstand_brutto'] ?? 0);
        $reserviert = floatval($row['reserviert'] ?? 0);
        $verfuegbar = floatval($row['kassenstand_verfuegbar'] ?? 0);
    } else {
        // Fallback to old tables
        $brutto_result = $conn->query("SELECT SUM(betrag) as total FROM transaktionen WHERE status='gebucht'");
        $brutto = $brutto_result ? floatval($brutto_result->fetch_row()[0] ?? 0) : 0;
        
        $res_result = $conn->query("SELECT SUM(betrag) as total FROM reservierungen WHERE aktiv=1");
        $reserviert = $res_result ? floatval($res_result->fetch_row()[0] ?? 0) : 0;
        
        $verfuegbar = $brutto - $reserviert;
    }
    
    // Get history for chart (last 30 days)
    $history = [];
    $stmt = $conn->prepare("SELECT snapshot_date, balance_verfuegbar FROM balance_snapshot WHERE snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY snapshot_date ASC");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($date, $balance);
        
        while ($stmt->fetch()) {
            $history[] = [
                'ts' => $date,
                'balance' => floatval($balance)
            ];
        }
        $stmt->close();
    }
    
    // If no history, create snapshot for today
    if (empty($history)) {
        $today = date('Y-m-d');
        $member_count_result = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $member_count = $member_count_result ? $member_count_result->fetch_row()[0] : 0;
        
        $stmt = $conn->prepare("INSERT IGNORE INTO balance_snapshot (snapshot_date, balance_brutto, balance_reserviert, balance_verfuegbar, member_count) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sdddi", $today, $brutto, $reserviert, $verfuegbar, $member_count);
            $stmt->execute();
            $stmt->close();
        }
        
        $history[] = [
            'ts' => $today,
            'balance' => $verfuegbar
        ];
    }
    
    json_response([
        'status' => 'success',
        'data' => [
            'balance' => $verfuegbar,
            'balance_brutto' => $brutto,
            'reserviert' => $reserviert,
            'history' => $history
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_balance.php: " . $e->getMessage());
    json_response(['status' => 'error', 'error' => 'Fehler beim Laden der Daten'], 500);
}
?>

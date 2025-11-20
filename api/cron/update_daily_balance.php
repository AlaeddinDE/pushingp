<?php
/**
 * Updates the daily balance history from the current PayPal pool status.
 * Can be called via cron or included in page loads (lazy cron).
 */

// Ensure DB connection
if (!isset($conn)) {
    require_once __DIR__ . '/../../includes/db.php';
}

function update_daily_balance() {
    global $conn;
    
    // Set Timezone to Europe/Berlin
    date_default_timezone_set('Europe/Berlin');
    
    // 1. Get current pool balance
    $result = $conn->query("SELECT pool_balance FROM paypal_pool_status ORDER BY last_updated DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $current_balance = floatval($row['pool_balance']);
        $today = date('Y-m-d');
        
        // 2. Update/Insert today's balance
        $stmt = $conn->prepare("
            INSERT INTO balance_history (date, balance) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE balance = VALUES(balance), last_updated = NOW()
        ");
        $stmt->bind_param('sd', $today, $current_balance);
        $stmt->execute();
        $stmt->close();
        
        // 3. Fill gaps (optional, simple version: just ensure yesterday exists if we missed it)
        // If we haven't visited in a while, we might want to fill gaps with the *current* balance 
        // (assuming it didn't change much, or better: use the last known balance).
        // For now, let's keep it simple. The chart logic handles gaps by using previous value.
    }
}

// Run if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    update_daily_balance();
    echo "Balance updated.";
}
?>

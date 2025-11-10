<?php
/**
 * Kasse XP Hooks
 * Automatically award XP for payment-related actions
 */

require_once __DIR__ . '/xp_system.php';
require_once __DIR__ . '/db.php';

/**
 * Hook: When transaction is added
 */
function transaction_added_hook($user_id, $amount, $type, $description) {
    // Award XP for payments
    if ($amount > 0) {
        award_payment_xp($user_id, $amount, $type);
    }
    
    // Check balance milestones
    award_balance_xp($user_id);
}

/**
 * Hook: Check for payment streaks (run monthly)
 */
function check_payment_streaks() {
    global $conn;
    
    // Find users with 3+ months no debt
    $result = $conn->query("
        SELECT DISTINCT t.mitglied_id, u.id
        FROM transaktionen t
        JOIN users u ON t.mitglied_id = u.id
        WHERE t.datum >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY t.mitglied_id
        HAVING SUM(t.betrag) >= 0
    ");
    
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        
        // Check if already awarded this month
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM xp_history 
            WHERE user_id = ? 
            AND action_code = 'PAYMENT_ONTIME' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($awarded);
        $stmt->fetch();
        $stmt->close();
        
        if ($awarded == 0) {
            // Award for staying debt-free
            add_xp($user_id, 'PAYMENT_ONTIME', '3+ Monate keine Rückstände');
        }
    }
}

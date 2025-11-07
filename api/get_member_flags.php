<?php
/**
 * Get Member Flags API
 * Returns payment status flags for all members
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

secure_session_start();
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $flags = [];
    
    // Get all active users
    $stmt = $conn->prepare("SELECT id FROM users WHERE status = 'active'");
    $stmt->execute();
    $stmt->bind_result($user_id);
    
    $user_ids = [];
    while ($stmt->fetch()) {
        $user_ids[] = $user_id;
    }
    $stmt->close();
    
    // Get payment status for each
    foreach ($user_ids as $uid) {
        $payment_status = get_payment_status($uid);
        $flags[] = [
            'id' => $uid,
            'dues' => $payment_status['status'] // 'paid', 'open', 'overdue'
        ];
    }
    
    json_response([
        'status' => 'success',
        'data' => $flags
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_member_flags.php: " . $e->getMessage());
    json_response(['status' => 'error', 'error' => 'Fehler beim Laden der Status-Flags'], 500);
}

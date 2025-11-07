<?php
/**
 * Get Live Status API
 * Returns current status of all members (shift, vacation, sick, available)
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

secure_session_start();
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $status_data = [];
    
    // Use the view we created
    $stmt = $conn->prepare("SELECT id, name, username, avatar, state FROM v_live_status ORDER BY state ASC, name ASC");
    $stmt->execute();
    $stmt->bind_result($id, $name, $username, $avatar, $state);
    
    $counters = [
        'shift' => 0,
        'available' => 0,
        'vacation' => 0,
        'sick' => 0
    ];
    
    while ($stmt->fetch()) {
        $counters[$state]++;
        
        $status_data[] = [
            'id' => $id,
            'name' => $name ?? $username,
            'avatarUrl' => $avatar ?? '/assets/img/default-avatar.png',
            'state' => $state,
            'presence' => 'offline' // Discord integration placeholder
        ];
    }
    $stmt->close();
    
    json_response([
        'status' => 'success',
        'data' => [
            'counters' => $counters,
            'members' => $status_data
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_live_status.php: " . $e->getMessage());
    json_response(['status' => 'error', 'error' => 'Fehler beim Laden des Status'], 500);
}

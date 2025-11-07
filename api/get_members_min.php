<?php
/**
 * Get Members Minimal API  
 * Returns minimal member info for startseite crew preview
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

secure_session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    $members = [];
    
    $stmt = $conn->prepare("
        SELECT id, username, name, avatar
        FROM users 
        WHERE status = 'active'
        ORDER BY RAND()
        LIMIT 10
    ");
    $stmt->execute();
    $stmt->bind_result($id, $username, $name, $avatar);
    
    while ($stmt->fetch()) {
        $members[] = [
            'id' => $id,
            'name' => $name ?? $username,
            'avatarUrl' => $avatar ?? '/assets/img/default-avatar.png',
            'presence' => 'offline' // Will be updated via Discord integration
        ];
    }
    $stmt->close();
    
    json_response([
        'status' => 'success',
        'data' => $members
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_members_min.php: " . $e->getMessage());
    json_response(['status' => 'error', 'error' => 'Fehler beim Laden'], 500);
}

<?php
/**
 * Get Members API
 * Returns list of all members with basic info
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

secure_session_start();
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $members = [];
    
    $stmt = $conn->prepare("
        SELECT id, username, name, email, discord_tag, avatar, role, roles, status, aktiv_ab, created_at
        FROM users 
        WHERE status != 'locked'
        ORDER BY name ASC, username ASC
    ");
    $stmt->execute();
    $stmt->bind_result($id, $username, $name, $email, $discord, $avatar, $role, $roles_json, $status, $aktiv_ab, $created_at);
    
    while ($stmt->fetch()) {
        $roles_array = $roles_json ? json_decode($roles_json, true) : [$role];
        if (!is_array($roles_array)) {
            $roles_array = [$role];
        }
        
        $members[] = [
            'id' => $id,
            'username' => $username,
            'name' => $name ?? $username,
            'email' => $email,
            'discordTag' => $discord,
            'avatarUrl' => $avatar ?? '/assets/img/default-avatar.png',
            'roles' => $roles_array,
            'status' => $status,
            'joinDate' => $aktiv_ab ?? $created_at
        ];
    }
    $stmt->close();
    
    json_response([
        'status' => 'success',
        'data' => $members
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_members.php: " . $e->getMessage());
    json_response(['status' => 'error', 'error' => 'Fehler beim Laden der Mitglieder'], 500);
}

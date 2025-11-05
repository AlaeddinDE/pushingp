<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

try {
    $result = $mysqli->query("SELECT pin, member_name FROM admins WHERE member_name IS NOT NULL ORDER BY member_name ASC");
    if (!$result) {
        json_response(['error' => 'Fehler beim Laden der Admins'], 500);
    }
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    json_response($admins);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>


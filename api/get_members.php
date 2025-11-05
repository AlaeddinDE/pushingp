<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Prüfe ob Admin (optional, für PIN-Anzeige)
    session_start();
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    
    // Wenn Admin, auch PIN zurückgeben
    if ($isAdmin) {
        $result = $mysqli->query("SELECT name, flag, pin, start_date FROM members ORDER BY name ASC");
    } else {
        $result = $mysqli->query("SELECT name, flag FROM members ORDER BY name ASC");
    }
    
    if (!$result) {
        json_response(['error' => 'Fehler beim Laden der Mitglieder'], 500);
    }
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    json_response($members);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

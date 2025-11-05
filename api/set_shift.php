<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);

try {
    $member_name = trim($_POST['member_name'] ?? '');
    $shift_date = trim($_POST['shift_date'] ?? '');
    $shift_start = trim($_POST['shift_start'] ?? '');
    $shift_end = trim($_POST['shift_end'] ?? '');
    
    // Wenn nicht Admin, nur eigene Schichten eintragen
    if (!$isAdmin && $member_name !== $user) {
        json_response(['error' => 'Nur eigene Schichten eintragbar'], 403);
    }
    
    // Validierung
    if (!$member_name || !$shift_date || !$shift_start || !$shift_end) {
        json_response(['error' => 'Alle Felder erforderlich'], 400);
    }
    
    // Prüfe ob Mitglied existiert
    $checkStmt = $mysqli->prepare("SELECT name FROM members WHERE name = ? LIMIT 1");
    $checkStmt->bind_param("s", $member_name);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        $checkStmt->close();
        json_response(['error' => 'Mitglied nicht gefunden'], 404);
    }
    $checkStmt->close();
    
    // Validierung: Endzeit muss nach Startzeit sein
    if (strtotime($shift_end) <= strtotime($shift_start)) {
        json_response(['error' => 'Endzeit muss nach Startzeit liegen'], 400);
    }
    
    // Füge Schicht hinzu
    $stmt = $mysqli->prepare("INSERT INTO shifts (member_name, shift_date, shift_start, shift_end) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $member_name, $shift_date, $shift_start, $shift_end);
    
    if ($stmt->execute()) {
        json_response(['status' => 'ok', 'id' => $mysqli->insert_id]);
    } else {
        json_response(['error' => 'Schicht konnte nicht gespeichert werden'], 500);
    }
    $stmt->close();
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);

try {
    $shift_id = intval($_POST['id'] ?? 0);
    
    if (!$shift_id) {
        json_response(['error' => 'Schicht-ID erforderlich'], 400);
    }
    
    // Prüfe ob Schicht existiert und ob User berechtigt ist
    $checkStmt = $mysqli->prepare("SELECT member_name FROM shifts WHERE id = ? LIMIT 1");
    $checkStmt->bind_param("i", $shift_id);
    $checkStmt->execute();
    $checkStmt->bind_result($member_name);
    $found = $checkStmt->fetch();
    $checkStmt->close();
    
    if (!$found) {
        json_response(['error' => 'Schicht nicht gefunden'], 404);
    }
    
    // Nur Admin oder eigener Name
    if (!$isAdmin && $member_name !== $user) {
        json_response(['error' => 'Zugriff verweigert'], 403);
    }
    
    // Lösche Schicht
    $stmt = $mysqli->prepare("DELETE FROM shifts WHERE id = ?");
    $stmt->bind_param("i", $shift_id);
    
    if ($stmt->execute()) {
        json_response(['status' => 'ok']);
    } else {
        json_response(['error' => 'Schicht konnte nicht gelöscht werden'], 500);
    }
    $stmt->close();
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>


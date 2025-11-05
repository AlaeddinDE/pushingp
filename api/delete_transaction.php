<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);

// Nur Admins können Transaktionen löschen
if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

try {
    $id = trim($_POST['id'] ?? '');
    
    if (!$id) {
        json_response(['error' => 'Transaktions-ID erforderlich'], 400);
    }
    
    // Prüfe ob Transaktion existiert
    $checkStmt = $mysqli->prepare("SELECT name FROM transactions WHERE id = ? LIMIT 1");
    $checkStmt->bind_param("s", $id);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        $checkStmt->close();
        json_response(['error' => 'Transaktion nicht gefunden'], 404);
    }
    $checkStmt->close();
    
    // Lösche Transaktion
    $stmt = $mysqli->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->bind_param("s", $id);
    
    if ($stmt->execute()) {
        json_response(['status' => 'ok']);
    } else {
        json_response(['error' => 'Transaktion konnte nicht gelöscht werden'], 500);
    }
    $stmt->close();
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>


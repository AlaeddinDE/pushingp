<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

try {
    $name = trim($_POST['name'] ?? '');
    $flag = trim($_POST['flag'] ?? '');
    $pin  = trim($_POST['pin'] ?? '');
    
    // Validierung
    if (!$name || !$pin) {
        json_response(['error' => 'Name und PIN erforderlich'], 400);
    }
    
    if (strlen($pin) < 4 || strlen($pin) > 6 || !preg_match('/^\d+$/', $pin)) {
        json_response(['error' => 'PIN muss 4–6 Ziffern lang sein'], 400);
    }
    
    // Prüfe ob Name bereits existiert
    $checkStmt = $mysqli->prepare("SELECT name FROM members WHERE name = ? LIMIT 1");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    if ($checkStmt->fetch()) {
        $checkStmt->close();
        json_response(['error' => 'Mitglied existiert bereits'], 400);
    }
    $checkStmt->close();
    
    // Füge Mitglied hinzu
    $stmt = $mysqli->prepare("INSERT INTO members (name, flag, start_date, pin) VALUES (?, ?, CURDATE(), ?)");
    $stmt->bind_param("sss", $name, $flag, $pin);
    
    if ($stmt->execute()) {
        json_response(['status' => 'ok', 'message' => 'Mitglied erfolgreich angelegt']);
    } else {
        json_response(['error' => 'Konnte Mitglied nicht speichern'], 500);
    }
    $stmt->close();
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

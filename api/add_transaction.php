<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);

// Nur Admins können Transaktionen hinzufügen
if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

try {
    $name = trim($_POST['name'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $type = trim($_POST['type'] ?? '');
    $reason = trim($_POST['note'] ?? ''); // note wird zu reason
    
    // Validierung
    if (!$name || $amount <= 0 || !in_array($type, ['Einzahlung', 'Auszahlung', 'Gutschrift'])) {
        json_response(['error' => 'Ungültige Parameter'], 400);
    }
    
    // Prüfe ob Mitglied existiert
    $checkStmt = $mysqli->prepare("SELECT name FROM members WHERE name = ? LIMIT 1");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        $checkStmt->close();
        json_response(['error' => 'Mitglied nicht gefunden'], 404);
    }
    $checkStmt->close();
    
    // Generiere eindeutige ID (wie in deiner DB)
    $id = uniqid('txn_', true);
    
    $stmt = $mysqli->prepare("INSERT INTO transactions (id, name, amount, type, reason, date) VALUES (?, ?, ?, ?, ?, CURDATE())");
    $stmt->bind_param("ssdss", $id, $name, $amount, $type, $reason);
    
    if ($stmt->execute()) {
        json_response(['status' => 'ok', 'id' => $id]);
    } else {
        json_response(['error' => 'Transaktion konnte nicht gespeichert werden'], 500);
    }
    $stmt->close();
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>


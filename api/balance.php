<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Veraltete API - verwendet get_balance.php und add_transaction.php stattdessen
// Diese Datei wird für Rückwärtskompatibilität beibehalten

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Redirect zu add_transaction.php
    require_once '../includes/auth.php';
    if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);
    
    $name = trim($_POST['name'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $type = trim($_POST['type'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    if (!$name || $amount <= 0 || !in_array($type, ['Einzahlung', 'Auszahlung', 'Gutschrift'])) {
        json_response(['error' => 'Ungültige Parameter'], 400);
    }
    
    // Verwende add_transaction.php Logik
    $id = uniqid('txn_', true);
    $stmt = $mysqli->prepare("INSERT INTO transactions (id, name, amount, type, reason, date) VALUES (?, ?, ?, ?, ?, CURDATE())");
    $stmt->bind_param("ssdss", $id, $name, $amount, $type, $note);
    
    if ($stmt->execute()) {
        json_response(['status' => 'success']);
    } else {
        json_response(['error' => 'Fehler beim Speichern'], 500);
    }
    $stmt->close();
} else {
    // GET: Redirect zu get_balance.php
    $sql = "
      SELECT name,
             ROUND(SUM(
               CASE WHEN type='Einzahlung' THEN amount
                    WHEN type='Gutschrift' THEN amount
                    ELSE -amount END
             ),2) AS balance
      FROM transactions
      GROUP BY name
      ORDER BY name ASC;
    ";
    $res = $mysqli->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);
    json_response($data);
}
?>

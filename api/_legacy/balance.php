<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Veraltete API - verwendet get_balance.php und add_transaction.php stattdessen
// Diese Datei wird für Rückwärtskompatibilität beibehalten

$method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

if ($method === 'POST') {
    // Redirect zu add_transaction.php
    require_once __DIR__ . '/../includes/auth.php';
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
    $stmt = $conn->prepare("INSERT INTO transactions (id, name, amount, type, reason, date) VALUES (?, ?, ?, ?, ?, CURDATE())");
    $stmt->bind_param("ssdss", $id, $name, $amount, $type, $note);

    if ($stmt->execute()) {
        json_response(['status' => 'success']);
    } else {
        json_response(['error' => 'Fehler beim Speichern'], 500);
    }
    $stmt->close();

} elseif ($method === 'GET') {
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
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);
    json_response($data);
} else {
    // CLI oder unbekannte Aufrufe -> einfach leer zurückgeben
    json_response([]);
}
?>


<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);

// Nur Admins können Transaktionen hinzufügen
if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

try {
    $name = trim($_POST['name'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $type = trim($_POST['type'] ?? '');
    $reason = trim($_POST['note'] ?? ''); // note wird zu reason

    $allowedTypes = ['Einzahlung', 'Auszahlung', 'Gutschrift', 'Schaden', 'Gruppenaktion'];

    // Validierung
    if ($amount <= 0 || !in_array($type, $allowedTypes, true)) {
        json_response(['error' => 'Ungültige Parameter'], 400);
    }

    if ($type === 'Gruppenaktion') {
        // Betrag pro Person auf alle aktiven Mitglieder verteilen
        $membersRes = $conn->query("SELECT name FROM members ORDER BY name ASC");
        if (!$membersRes) {
            json_response(['error' => 'Mitglieder konnten nicht geladen werden'], 500);
        }
        $members = [];
        while ($row = $membersRes->fetch_assoc()) {
            if (!empty($row['name'])) {
                $members[] = $row['name'];
            }
        }
        if (count($members) === 0) {
            json_response(['error' => 'Keine Mitglieder vorhanden'], 400);
        }

        $stmt = $conn->prepare("INSERT INTO transactions (id, name, amount, type, reason, date) VALUES (?, ?, ?, ?, ?, CURDATE())");
        if (!$stmt) {
            json_response(['error' => 'Statement konnte nicht vorbereitet werden'], 500);
        }

        $created = [];
        foreach ($members as $memberName) {
            $id = uniqid('txn_', true);
            $stmt->bind_param("ssdss", $id, $memberName, $amount, $type, $reason);
            if (!$stmt->execute()) {
                $stmt->close();
                json_response(['error' => 'Gruppenaktion konnte nicht gespeichert werden'], 500);
            }
            $created[] = $id;
        }
        $stmt->close();

        json_response([
            'status' => 'ok',
            'created' => $created,
            'count' => count($created)
        ]);
    }

    if (!$name) {
        json_response(['error' => 'Name erforderlich'], 400);
    }

    // Prüfe ob Mitglied existiert
    $checkStmt = $conn->prepare("SELECT 1 FROM members WHERE name = ? LIMIT 1");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows === 0) {
        $checkStmt->close();
        json_response(['error' => 'Mitglied nicht gefunden'], 404);
    }
    $checkStmt->close();

    // Generiere eindeutige ID (wie in deiner DB)
    $id = uniqid('txn_', true);

    $stmt = $conn->prepare("INSERT INTO transactions (id, name, amount, type, reason, date) VALUES (?, ?, ?, ?, ?, CURDATE())");
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


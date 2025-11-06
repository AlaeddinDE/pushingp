<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Prüfe ob Admin (optional, für PIN-Anzeige)
    session_start();
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

    // Wenn Admin, auch PIN zurückgeben
    if ($isAdmin) {
	$result = $conn->query("SELECT id, name, flag, pin, start_date, is_locked, locked_at, locked_reason FROM members_v2 ORDER BY name ASC");
    } else {
	$result = $conn->query("SELECT id, name, flag, is_locked FROM members_v2 ORDER BY name ASC");
    }

    if (!$result) {
        json_response(['error' => 'Fehler beim Laden der Mitglieder'], 500);
    }

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = isset($row['id']) ? (int) $row['id'] : null;
        if (isset($row['is_locked'])) {
            $row['is_locked'] = (int) $row['is_locked'] === 1;
        }
        $members[] = $row;
    }

    json_response($members);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

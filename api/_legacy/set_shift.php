<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

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
    $member = fetch_member_by_name($conn, $member_name);
    if ($member === null) {
        json_response(['error' => 'Mitglied nicht gefunden'], 404);
    }

    // Validierung: Endzeit muss nach Startzeit sein
    if (strtotime($shift_end) <= strtotime($shift_start)) {
        json_response(['error' => 'Endzeit muss nach Startzeit liegen'], 400);
    }

    $allowedTypes = ['early','late','night','day','custom'];
    $shift_type = strtolower(trim($_POST['shift_type'] ?? ''));
    if (!in_array($shift_type, $allowedTypes, true)) {
        $defaultMap = [
            '06:00' => 'early',
            '14:00' => 'late',
            '22:00' => 'night',
            '07:00' => 'day',
        ];
        $guessKey = substr($shift_start, 0, 5);
        $shift_type = $defaultMap[$guessKey] ?? 'custom';
    }

    $startTime = strlen($shift_start) === 5 ? $shift_start . ':00' : $shift_start;
    $endTime = strlen($shift_end) === 5 ? $shift_end . ':00' : $shift_end;

    $creator = fetch_member_by_name($conn, $user);
    $creatorId = $creator['id'] ?? $member['id'];

    // Füge Schicht hinzu
    $stmt = $conn->prepare("INSERT INTO shifts (member_id, member_name, shift_date, shift_start, shift_end, start_time, end_time, shift_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        json_response(['error' => 'Schicht konnte nicht gespeichert werden'], 500);
    }
    $stmt->bind_param("isssssssi", $member['id'], $member_name, $shift_date, $shift_start, $shift_end, $startTime, $endTime, $shift_type, $creatorId);

    if ($stmt->execute()) {
        json_response(['status' => 'ok', 'id' => $conn->insert_id]);
    } else {
        json_response(['error' => 'Schicht konnte nicht gespeichert werden'], 500);
    }
    $stmt->close();
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

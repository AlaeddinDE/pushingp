<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $sql = "SELECT s.id, s.member_name, s.shift_date, COALESCE(s.start_time, s.shift_start) AS start_time, COALESCE(s.end_time, s.shift_end) AS end_time, s.shift_start, s.shift_end, s.shift_type, m.flag
            FROM shifts s
            LEFT JOIN members m ON s.member_id = m.id
            ORDER BY s.shift_date DESC, COALESCE(s.start_time, s.shift_start) DESC, s.id DESC";
    $res = $mysqli->query($sql);
    if (!$res) {
        json_response(['error' => 'Fehler beim Laden der Schichten'], 500);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'member_name' => $row['member_name'] ?? null,
            'shift_date' => $row['shift_date'] ?? null,
            'shift_start' => $row['shift_start'] ?? $row['start_time'] ?? null,
            'shift_end' => $row['shift_end'] ?? $row['end_time'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'shift_type' => $row['shift_type'] ?? 'custom',
            'flag' => $row['flag'] ?? null,
        ];
    }
    json_response($rows);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

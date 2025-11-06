<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Veraltete API - verwendet get_shifts.php stattdessen
// Diese Datei wird für Rückwärtskompatibilität beibehalten
$res = $conn->query("SELECT member_name as name, shift_date, shift_start, shift_end FROM shifts ORDER BY shift_date DESC LIMIT 20");
$data = $res->fetch_all(MYSQLI_ASSOC);
json_response($data);
?>

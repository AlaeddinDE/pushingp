<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Veraltete API - verwendet get_members.php stattdessen
// Diese Datei wird für Rückwärtskompatibilität beibehalten
$res = $conn->query("SELECT name, flag, pin FROM members ORDER BY name ASC");
$data = $res->fetch_all(MYSQLI_ASSOC);
json_response($data);
?>

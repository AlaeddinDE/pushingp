<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $res = $mysqli->query("SELECT * FROM shifts ORDER BY shift_date DESC, shift_start DESC");
    if (!$res) {
        json_response(['error' => 'Fehler beim Laden der Schichten'], 500);
    }
    $data = $res->fetch_all(MYSQLI_ASSOC);
    json_response($data);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

<?php
require_once __DIR__ . '/../env.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $mysqli->connect_error]);
    exit;
}

$mysqli->set_charset('utf8mb4');
?>

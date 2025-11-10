<?php
/**
 * Apple Wallet Web Service - Get Updatable Passes
 * GET /v1/devices/:deviceLibraryIdentifier/registrations/:passTypeIdentifier?passesUpdatedSince=:tag
 * Returns list of passes that have been updated since the given tag
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/db.php';

// Parse URL parameters
$url_parts = explode('/', $_SERVER['REQUEST_URI']);
$device_id = $url_parts[array_search('devices', $url_parts) + 1] ?? null;
$pass_type = $url_parts[array_search('registrations', $url_parts) + 1] ?? null;

// Get passesUpdatedSince parameter
$passes_updated_since = $_GET['passesUpdatedSince'] ?? null;

if (!$device_id || !$pass_type) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Get registered passes for this device
$query = "
    SELECT DISTINCT wr.serial_number, u.wallet_last_updated
    FROM wallet_registrations wr
    JOIN users u ON wr.user_id = u.id
    WHERE wr.device_library_identifier = ?
    AND wr.pass_type_identifier = ?
";

$params = [$device_id, $pass_type];
$types = 'ss';

if ($passes_updated_since) {
    $query .= " AND u.wallet_last_updated > ?";
    $params[] = $passes_updated_since;
    $types .= 's';
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$serial_numbers = [];
$last_updated = null;

while ($row = $result->fetch_assoc()) {
    $serial_numbers[] = $row['serial_number'];
    if (!$last_updated || $row['wallet_last_updated'] > $last_updated) {
        $last_updated = $row['wallet_last_updated'];
    }
}

$stmt->close();

if (empty($serial_numbers)) {
    http_response_code(204); // No Content
    exit;
}

http_response_code(200);
echo json_encode([
    'serialNumbers' => $serial_numbers,
    'lastUpdated' => strtotime($last_updated)
]);

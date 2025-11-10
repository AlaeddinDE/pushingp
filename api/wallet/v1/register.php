<?php
/**
 * Apple Wallet Web Service - Device Registration
 * POST /v1/devices/:deviceLibraryIdentifier/registrations/:passTypeIdentifier/:serialNumber
 * Registers a device to receive push notifications for pass updates
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/db.php';

// Parse URL parameters
$url_parts = explode('/', $_SERVER['REQUEST_URI']);
$device_id = $url_parts[array_search('devices', $url_parts) + 1] ?? null;
$pass_type = $url_parts[array_search('registrations', $url_parts) + 1] ?? null;
$serial_number = $url_parts[array_search($pass_type, $url_parts) + 1] ?? null;

// Get Authorization token
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
preg_match('/ApplePass (.+)/', $auth_header, $matches);
$auth_token = $matches[1] ?? null;

if (!$device_id || !$pass_type || !$serial_number || !$auth_token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Verify auth token
$stmt = $conn->prepare("SELECT user_id FROM wallet_tokens WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())");
$stmt->bind_param('s', $auth_token);
$stmt->execute();
$stmt->bind_result($user_id);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(401);
    echo json_encode(['error' => 'Invalid authentication token']);
    exit;
}
$stmt->close();

// Get push token from request body
$input = json_decode(file_get_contents('php://input'), true);
$push_token = $input['pushToken'] ?? null;

// Register device
$stmt = $conn->prepare("
    INSERT INTO wallet_registrations 
    (device_library_identifier, pass_type_identifier, serial_number, user_id, push_token) 
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE push_token = ?, last_updated = NOW()
");
$stmt->bind_param('sssiss', $device_id, $pass_type, $serial_number, $user_id, $push_token, $push_token);

if ($stmt->execute()) {
    http_response_code(201); // Created
    echo json_encode(['status' => 'registered']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to register device']);
}

$stmt->close();

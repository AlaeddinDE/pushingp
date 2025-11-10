<?php
/**
 * Apple Wallet Web Service - Unregister Device
 * DELETE /v1/devices/:deviceLibraryIdentifier/registrations/:passTypeIdentifier/:serialNumber
 * Unregisters a device from receiving push notifications
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

// Unregister device
$stmt = $conn->prepare("
    DELETE FROM wallet_registrations 
    WHERE device_library_identifier = ? 
    AND pass_type_identifier = ? 
    AND serial_number = ?
    AND user_id = ?
");
$stmt->bind_param('sssi', $device_id, $pass_type, $serial_number, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'unregistered']);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Registration not found']);
}

$stmt->close();

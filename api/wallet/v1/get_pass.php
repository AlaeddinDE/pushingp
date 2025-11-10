<?php
/**
 * Apple Wallet Web Service - Get Latest Pass
 * GET /v1/passes/:passTypeIdentifier/:serialNumber
 * Returns the latest version of the pass
 */

require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/apple_wallet.php';

// Parse URL parameters
$url_parts = explode('/', $_SERVER['REQUEST_URI']);
$pass_type = $url_parts[array_search('passes', $url_parts) + 1] ?? null;
$serial_number = $url_parts[array_search($pass_type, $url_parts) + 1] ?? null;

// Get Authorization token
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
preg_match('/ApplePass (.+)/', $auth_header, $matches);
$auth_token = $matches[1] ?? null;

if (!$pass_type || !$serial_number || !$auth_token) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Verify auth token and get user
$stmt = $conn->prepare("SELECT user_id FROM wallet_tokens WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())");
$stmt->bind_param('s', $auth_token);
$stmt->execute();
$stmt->bind_result($user_id);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid authentication token']);
    exit;
}
$stmt->close();

// Verify serial number matches user
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND wallet_serial = ?");
$stmt->bind_param('is', $user_id, $serial_number);
$stmt->execute();
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Pass not found']);
    exit;
}
$stmt->close();

// Check if pass has been modified
$stmt = $conn->prepare("SELECT wallet_last_updated FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($last_updated);
$stmt->fetch();
$stmt->close();

// Check If-Modified-Since header
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    $if_modified_since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    $last_updated_ts = strtotime($last_updated);
    
    if ($last_updated_ts <= $if_modified_since) {
        http_response_code(304); // Not Modified
        exit;
    }
}

try {
    // Generate fresh pass
    $wallet = new AppleWalletPass($conn);
    $pkpass_path = $wallet->generatePass($user_id);
    
    if (!file_exists($pkpass_path)) {
        throw new Exception('Failed to generate pass');
    }
    
    // Send updated pass
    header('Content-Type: application/vnd.apple.pkpass');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime($last_updated)) . ' GMT');
    header('Content-Length: ' . filesize($pkpass_path));
    
    readfile($pkpass_path);
    
} catch (Exception $e) {
    error_log('Wallet pass retrieval error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to retrieve pass']);
}

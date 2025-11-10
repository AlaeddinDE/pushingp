<?php
/**
 * API: Generate Apple Wallet Pass
 * Creates and downloads a .pkpass file for the authenticated user
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/apple_wallet.php';
secure_session_start();

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}

$user_id = get_current_user_id();

try {
    $wallet = new AppleWalletPass($conn);
    $pkpass_path = $wallet->generatePass($user_id);
    
    if (!file_exists($pkpass_path)) {
        throw new Exception('Failed to generate pass');
    }
    
    // Send file
    header('Content-Type: application/vnd.apple.pkpass');
    header('Content-Disposition: attachment; filename="PushingP_Crew.pkpass"');
    header('Content-Length: ' . filesize($pkpass_path));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($pkpass_path);
    
    // Optionally delete file after sending
    // unlink($pkpass_path);
    
} catch (Exception $e) {
    error_log('Wallet pass generation error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to generate wallet pass: ' . $e->getMessage()
    ]);
}

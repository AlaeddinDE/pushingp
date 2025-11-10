<?php
/**
 * Apple Wallet Web Service - Log
 * POST /v1/log
 * Receives error logs from Apple Wallet
 */

header('Content-Type: application/json');

$log_dir = __DIR__ . '/log/';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$input = file_get_contents('php://input');
$log_data = json_decode($input, true);

$log_entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'data' => $log_data,
    'raw' => $input
];

file_put_contents(
    $log_dir . 'wallet_' . date('Y-m-d') . '.log',
    json_encode($log_entry, JSON_PRETTY_PRINT) . "\n\n",
    FILE_APPEND
);

http_response_code(200);
echo json_encode(['status' => 'logged']);

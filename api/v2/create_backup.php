<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/config.php';
secure_session_start();
require_login();

if (!is_admin()) {
    http_response_code(403);
    die("Unauthorized");
}

$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$filename = 'backup_pushingp_' . date('Y-m-d_H-i-s') . '.sql';
$filepath = $backupDir . $filename;

// Command to create backup
// Note: Using password in command line is generally discouraged but used here for simplicity in this environment.
$command = sprintf(
    'mysqldump -h %s -u %s -p%s %s > %s',
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($filepath)
);

// Execute command
exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    http_response_code(500);
    die("Backup failed. Error code: $returnVar");
}

// Download file
if (file_exists($filepath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    http_response_code(404);
    die("Backup file not found.");
}

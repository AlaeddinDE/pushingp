<?php
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/../../includes/db.php';
    echo json_encode(['status' => 'success', 'message' => 'DB loaded']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

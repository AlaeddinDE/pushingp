<?php
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/../../includes/db.php';
    require_once __DIR__ . '/../../includes/xp_system.php';
    echo json_encode(['status' => 'success', 'message' => 'XP system loaded']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}

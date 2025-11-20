<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
secure_session_start();

if (!isset($_SESSION['user_id']) || !is_admin()) {
    echo json_encode(['status' => 'error', 'error' => 'Keine Berechtigung']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
    echo json_encode(['status' => 'error', 'error' => 'Keine Transaktions-IDs angegeben']);
    exit;
}

$ids = array_map('intval', $data['ids']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$conn->begin_transaction();

try {
    // Transaktionen auf 'storniert' setzen statt zu löschen
    $stmt = $conn->prepare("UPDATE transaktionen SET status = 'storniert' WHERE id IN ($placeholders)");
    
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Stornieren der Transaktionen');
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    // XP für alle stornierten Transaktionen entfernen
    $ids_str = implode(',', $ids);
    $xp_result = $conn->query("SELECT id, user_id, xp_change FROM xp_history WHERE source_table = 'transaktionen' AND source_id IN ($ids_str)");
    if ($xp_result) {
        while ($xp_row = $xp_result->fetch_assoc()) {
            $uid = $xp_row['user_id'];
            $xp_change = $xp_row['xp_change'];
            $conn->query("UPDATE users SET xp_total = GREATEST(0, xp_total - $xp_change) WHERE id = $uid");
            $conn->query("DELETE FROM xp_history WHERE id = " . $xp_row['id']);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => "$affected Transaktionen wurden storniert",
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

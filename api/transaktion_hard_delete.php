<?php
// API: Transaktion ENDGÜLTIG löschen (Hard Delete)
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$transaction_id = intval($data['id'] ?? 0);

if ($transaction_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid transaction ID']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. XP entfernen
    $xp_result = $conn->query("SELECT id, user_id, xp_change FROM xp_history WHERE source_table = 'transaktionen' AND source_id = $transaction_id");
    if ($xp_result) {
        while ($xp_row = $xp_result->fetch_assoc()) {
            $uid = $xp_row['user_id'];
            $xp_change = $xp_row['xp_change'];
            $conn->query("UPDATE users SET xp_total = GREATEST(0, xp_total - $xp_change) WHERE id = $uid");
            $conn->query("DELETE FROM xp_history WHERE id = " . $xp_row['id']);
        }
    }

    // 2. Transaktion löschen
    $stmt = $conn->prepare("DELETE FROM transaktionen WHERE id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Transaktion nicht gefunden');
    }
    $stmt->close();

    // 3. Guthaben neu berechnen (falls nötig)
    // Hier verlassen wir uns darauf, dass die Transaktion weg ist und Views/Berechnungen das beim nächsten Mal berücksichtigen.
    // Optional: Member Payment Status updaten
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Transaktion endgültig gelöscht']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}

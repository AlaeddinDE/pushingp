<?php
// API: Transaktion endgültig löschen (nur Admin, mit Vorsicht!)
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

// Mitglied-ID vor dem Löschen holen für Neuberechnung
$check = $conn->query("SELECT mitglied_id, typ FROM transaktionen WHERE id = $transaction_id");
$mitglied_id = null;
$typ = null;
if ($row = $check->fetch_assoc()) {
    $mitglied_id = $row['mitglied_id'];
    $typ = $row['typ'];
}

// Transaktion ENDGÜLTIG löschen
$stmt = $conn->prepare("DELETE FROM transaktionen WHERE id = ?");
$stmt->bind_param("i", $transaction_id);

if ($stmt->execute()) {
    $stmt->close();
    
    // Guthaben neu berechnen
    if ($mitglied_id && in_array($typ, ['EINZAHLUNG', 'GRUPPENAKTION_ANTEILIG'])) {
        $total = $conn->query("SELECT SUM(betrag) as total FROM transaktionen WHERE mitglied_id = $mitglied_id AND typ IN ('EINZAHLUNG', 'GRUPPENAKTION_ANTEILIG') AND status = 'gebucht'")->fetch_assoc()['total'] ?? 0;
        
        $update = $conn->prepare("UPDATE member_payment_status SET guthaben = ?, gedeckt_bis = DATE_ADD(CURDATE(), INTERVAL FLOOR(? / monatsbeitrag) MONTH) WHERE mitglied_id = ?");
        $update->bind_param("ddi", $total, $total, $mitglied_id);
        $update->execute();
        $update->close();
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Transaktion endgültig gelöscht']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Transaktion nicht gefunden']);
}

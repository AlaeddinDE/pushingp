<?php
// API: Transaktion bearbeiten (nur Admin)
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$transaction_id = intval($data['id'] ?? 0);
$betrag = floatval($data['betrag'] ?? 0);
$beschreibung = trim($data['beschreibung'] ?? '');

if ($transaction_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid transaction ID']);
    exit;
}

// Transaktion aktualisieren
$stmt = $conn->prepare("UPDATE transaktionen SET betrag = ?, beschreibung = ? WHERE id = ?");
$stmt->bind_param("dsi", $betrag, $beschreibung, $transaction_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $stmt->close();
    
    // Guthaben neu berechnen (wenn EINZAHLUNG oder GRUPPENAKTION_ANTEILIG)
    $check = $conn->query("SELECT typ, mitglied_id FROM transaktionen WHERE id = $transaction_id");
    if ($row = $check->fetch_assoc()) {
        if ($row['mitglied_id'] && in_array($row['typ'], ['EINZAHLUNG', 'GRUPPENAKTION_ANTEILIG'])) {
            // Guthaben neu berechnen
            $mitglied_id = $row['mitglied_id'];
            $total = $conn->query("SELECT SUM(betrag) as total FROM transaktionen WHERE mitglied_id = $mitglied_id AND typ IN ('EINZAHLUNG', 'GRUPPENAKTION_ANTEILIG') AND status = 'gebucht'")->fetch_assoc()['total'];
            
            $update = $conn->prepare("UPDATE member_payment_status SET guthaben = ?, gedeckt_bis = DATE_ADD(CURDATE(), INTERVAL FLOOR(? / monatsbeitrag) MONTH) WHERE mitglied_id = ?");
            $update->bind_param("ddi", $total, $total, $mitglied_id);
            $update->execute();
            $update->close();
        }
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Transaktion aktualisiert']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Transaktion nicht gefunden oder keine Änderung']);
}

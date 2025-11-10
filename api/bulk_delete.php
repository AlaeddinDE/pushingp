<?php
// API: Bulk Delete (Storno)
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/berechne_zahlungsstatus.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'error' => 'No IDs provided']);
    exit;
}

// Alle auf "storniert" setzen
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "UPDATE transaktionen SET status = 'storniert' WHERE id IN ($placeholders)";

$stmt = $conn->prepare($sql);
$types = str_repeat('i', count($ids));
$stmt->bind_param($types, ...$ids);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    // Zahlungsstatus für alle betroffenen Mitglieder neu berechnen
    $mitglieder = $conn->query("
        SELECT DISTINCT mitglied_id 
        FROM transaktionen 
        WHERE id IN ($placeholders)
        AND mitglied_id IS NOT NULL
    ");
    
    while ($row = $mitglieder->fetch_assoc()) {
        berechneZahlungsstatus($row['mitglied_id']);
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => "$affected Transaktionen storniert"
    ]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Stornierung fehlgeschlagen']);
}

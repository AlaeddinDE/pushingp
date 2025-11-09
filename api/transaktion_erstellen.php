<?php
// API: Neue Transaktion erstellen
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$typ = trim($data['typ'] ?? '');
$mitglied_id = !empty($data['mitglied_id']) ? intval($data['mitglied_id']) : null;
$betrag = floatval($data['betrag'] ?? 0);
$beschreibung = trim($data['beschreibung'] ?? '');
$status = trim($data['status'] ?? 'gebucht');
$datum = trim($data['datum'] ?? '');

// Validate typ
$valid_types = ['EINZAHLUNG', 'AUSZAHLUNG', 'GRUPPENAKTION_KASSE', 'GRUPPENAKTION_ANTEILIG', 'SCHADEN', 'AUSGLEICH', 'RESERVIERUNG', 'UMBUCHUNG', 'KORREKTUR'];
if (!in_array($typ, $valid_types)) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Typ']);
    exit;
}

// Validate status
if (!in_array($status, ['gebucht', 'storniert', 'gesperrt'])) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Status']);
    exit;
}

// Datum formatieren
if (empty($datum)) {
    $datum = date('Y-m-d H:i:s');
} else {
    $datum = date('Y-m-d H:i:s', strtotime($datum));
}

// Transaktion erstellen
if ($mitglied_id) {
    $stmt = $conn->prepare("INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, status, datum, erstellt_von, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sdisssi", $typ, $betrag, $mitglied_id, $beschreibung, $status, $datum, $_SESSION['user_id']);
} else {
    $stmt = $conn->prepare("INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, status, datum, erstellt_von, erstellt_am) VALUES (?, ?, NULL, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sdssi", $typ, $betrag, $beschreibung, $status, $datum, $_SESSION['user_id']);
}

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    $stmt->close();
    
    // Guthaben aktualisieren für betroffenes Mitglied
    if ($mitglied_id && in_array($typ, ['EINZAHLUNG', 'GRUPPENAKTION_ANTEILIG']) && $status === 'gebucht') {
        $total = $conn->query("SELECT SUM(betrag) as total FROM transaktionen WHERE mitglied_id = $mitglied_id AND typ IN ('EINZAHLUNG', 'GRUPPENAKTION_ANTEILIG') AND status = 'gebucht'")->fetch_assoc()['total'] ?? 0;
        
        $update = $conn->prepare("UPDATE member_payment_status SET guthaben = ?, gedeckt_bis = DATE_ADD(CURDATE(), INTERVAL FLOOR(? / monatsbeitrag) MONTH) WHERE mitglied_id = ?");
        $update->bind_param("ddi", $total, $total, $mitglied_id);
        $update->execute();
        $update->close();
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Transaktion erstellt', 'id' => $new_id]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Fehler beim Erstellen']);
}

<?php
// API: Einzahlung buchen und Deckungsstatus aktualisieren
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$mitglied_id = intval($data['mitglied_id'] ?? $_SESSION['user_id']);
$betrag = floatval($data['betrag'] ?? 0);
$beschreibung = trim($data['beschreibung'] ?? 'Monatliche Einzahlung');

if ($betrag <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Betrag muss größer 0 sein']);
    exit;
}

// Nur Admins können für andere einzahlen
if ($mitglied_id != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Keine Berechtigung']);
    exit;
}

// Transaktion buchen
$stmt = $conn->prepare("INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, erstellt_von, datum, status) VALUES ('EINZAHLUNG', ?, ?, ?, ?, NOW(), 'gebucht')");
$stmt->bind_param("disi", $betrag, $mitglied_id, $beschreibung, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

// Guthaben und Deckung aktualisieren
$stmt = $conn->prepare("
    UPDATE member_payment_status 
    SET 
        guthaben = guthaben + ?,
        gedeckt_bis = DATE_ADD(CURDATE(), INTERVAL FLOOR((guthaben + ?) / monatsbeitrag) MONTH),
        naechste_zahlung_faellig = DATE_ADD(DATE_ADD(CURDATE(), INTERVAL FLOOR((guthaben + ?) / monatsbeitrag) MONTH), INTERVAL 1 DAY)
    WHERE mitglied_id = ?
");
$stmt->bind_param("dddi", $betrag, $betrag, $betrag, $mitglied_id);
$stmt->execute();
$stmt->close();

// Neuen Status holen
$stmt = $conn->prepare("SELECT gedeckt_bis, naechste_zahlung_faellig, guthaben FROM member_payment_status WHERE mitglied_id = ?");
$stmt->bind_param("i", $mitglied_id);
$stmt->execute();
$stmt->bind_result($gedeckt_bis, $naechste_zahlung, $guthaben);
$stmt->fetch();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Einzahlung erfolgreich',
    'data' => [
        'betrag' => $betrag,
        'guthaben' => $guthaben,
        'gedeckt_bis' => $gedeckt_bis,
        'naechste_zahlung_faellig' => $naechste_zahlung
    ]
]);

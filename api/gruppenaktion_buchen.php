<?php
// API: Gruppenaktion aus Kasse zahlen + Fair-Share für Nicht-Teilnehmer
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Nur Admins dürfen Gruppenaktionen buchen']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$betrag = floatval($data['betrag'] ?? 0);
$beschreibung = trim($data['beschreibung'] ?? '');
$teilnehmer_ids = $data['teilnehmer_ids'] ?? []; // Array von User-IDs, die dabei waren

if ($betrag <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Betrag muss größer 0 sein']);
    exit;
}

if (empty($beschreibung)) {
    echo json_encode(['status' => 'error', 'error' => 'Beschreibung erforderlich']);
    exit;
}

// Alle aktiven Mitglieder holen
$result = $conn->query("SELECT id FROM users WHERE status = 'active'");
$alle_mitglieder = [];
while ($row = $result->fetch_assoc()) {
    $alle_mitglieder[] = $row['id'];
}

// Nur GEDECKTE Mitglieder bekommen Gutschriften!
$gedeckte_mitglieder = [];
$result2 = $conn->query("SELECT mitglied_id FROM member_payment_status WHERE gedeckt_bis >= CURDATE()");
while ($row = $result2->fetch_assoc()) {
    $gedeckte_mitglieder[] = $row['mitglied_id'];
}

$anzahl_gesamt = count($alle_mitglieder);
if ($anzahl_gesamt == 0) {
    echo json_encode(['status' => 'error', 'error' => 'Keine aktiven Mitglieder']);
    exit;
}

// Fair-Share berechnen: Betrag / Anzahl TEILNEHMER (nicht alle!)
$anzahl_teilnehmer = count($teilnehmer_ids);
if ($anzahl_teilnehmer == 0) {
    echo json_encode(['status' => 'error', 'error' => 'Mindestens 1 Teilnehmer erforderlich']);
    exit;
}

$fair_share = round($betrag / $anzahl_teilnehmer, 2);

// Nicht-Teilnehmer identifizieren, aber nur die GEDECKTEN!
$nicht_teilnehmer_alle = array_diff($alle_mitglieder, $teilnehmer_ids);
$nicht_teilnehmer = array_intersect($nicht_teilnehmer_alle, $gedeckte_mitglieder);

// 1. Auszahlung aus Kasse buchen (negativ)
$stmt = $conn->prepare("INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, erstellt_von, datum, status) VALUES ('GRUPPENAKTION_KASSE', ?, NULL, ?, ?, NOW(), 'gebucht')");
$betrag_negativ = -$betrag;
$stmt->bind_param("dsi", $betrag_negativ, $beschreibung, $_SESSION['user_id']);
$stmt->execute();
$transaction_id = $stmt->insert_id;
$stmt->close();

// 2. Nicht-Teilnehmern Fair-Share gutschreiben
$gutschriften = [];
$stmt = $conn->prepare("INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, erstellt_von, datum, status) VALUES ('GRUPPENAKTION_ANTEILIG', ?, ?, ?, ?, NOW(), 'gebucht')");

foreach ($nicht_teilnehmer as $mitglied_id) {
    $beschreibung_gutschrift = "Nicht dabei: " . $beschreibung;
    $stmt->bind_param("disi", $fair_share, $mitglied_id, $beschreibung_gutschrift, $_SESSION['user_id']);
    $stmt->execute();
    
    // Guthaben aktualisieren
    $update = $conn->prepare("UPDATE member_payment_status SET guthaben = guthaben + ?, gedeckt_bis = DATE_ADD(CURDATE(), INTERVAL FLOOR((guthaben + ?) / monatsbeitrag) MONTH) WHERE mitglied_id = ?");
    $update->bind_param("ddi", $fair_share, $fair_share, $mitglied_id);
    $update->execute();
    $update->close();
    
    $gutschriften[] = $mitglied_id;
}
$stmt->close();

// Namen der Nicht-Teilnehmer holen
$nicht_teilnehmer_namen = [];
if (!empty($nicht_teilnehmer)) {
    $ids = implode(',', $nicht_teilnehmer);
    $result = $conn->query("SELECT name FROM users WHERE id IN ($ids)");
    while ($row = $result->fetch_assoc()) {
        $nicht_teilnehmer_namen[] = $row['name'];
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Gruppenaktion gebucht',
    'data' => [
        'betrag' => $betrag,
        'fair_share' => $fair_share,
        'anzahl_gesamt' => $anzahl_gesamt,
        'anzahl_teilnehmer' => count($teilnehmer_ids),
        'anzahl_nicht_teilnehmer' => count($nicht_teilnehmer),
        'anzahl_nicht_gedeckt' => count($nicht_teilnehmer_alle) - count($nicht_teilnehmer),
        'nicht_teilnehmer' => $nicht_teilnehmer_namen,
        'transaction_id' => $transaction_id
    ]
]);

<?php
/**
 * API: Kassen-Dashboard (vereinfacht)
 * Zeigt nur die wichtigsten Infos
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

// Dashboard-Stats
$stmt = $conn->prepare("SELECT * FROM v_kasse_dashboard LIMIT 1");
$stmt->execute();
$stmt->bind_result($kassenstand, $aktive, $ueberfaellig, $letzte_tx, $tx_monat);
$stmt->fetch();
$stmt->close();

// Letzte 10 Transaktionen
$tx_query = "
    SELECT 
        t.id, t.typ, t.betrag, t.beschreibung, t.datum,
        u.name AS mitglied_name
    FROM transaktionen t
    LEFT JOIN users u ON t.mitglied_id = u.id
    WHERE t.status = 'gebucht'
    ORDER BY t.datum DESC
    LIMIT 10
";

$stmt = $conn->prepare($tx_query);
$stmt->execute();
$stmt->bind_result($tx_id, $typ, $betrag, $beschreibung, $datum, $mitglied_name);

$transactions = [];
while ($stmt->fetch()) {
    $transactions[] = [
        'id' => $tx_id,
        'typ' => $typ,
        'betrag' => floatval($betrag),
        'beschreibung' => $beschreibung,
        'datum' => $datum,
        'mitglied_name' => $mitglied_name
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'status' => 'success',
    'data' => [
        'kassenstand' => floatval($kassenstand),
        'aktive_mitglieder' => intval($aktive),
        'ueberfaellig_count' => intval($ueberfaellig),
        'letzte_transaktion' => $letzte_tx,
        'transaktionen_monat' => intval($tx_monat),
        'recent_transactions' => $transactions
    ]
]);

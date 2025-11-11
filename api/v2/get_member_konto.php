<?php
/**
 * API: Mitglieder-Konto (vereinfacht)
 * Zeigt: Konto-Saldo, nächste Zahlung, Status
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

$query = "SELECT * FROM v_member_konto_simple ORDER BY 
    CASE zahlungsstatus
        WHEN 'ueberfaellig' THEN 1
        WHEN 'gedeckt' THEN 2
        WHEN 'inactive' THEN 3
    END,
    name ASC";

$stmt = $conn->prepare($query);
$stmt->execute();
$stmt->bind_result(
    $mitglied_id, $name, $username, $avatar, $status, 
    $aktiv_ab, $inaktiv_ab, $pflicht_monatlich,
    $konto_saldo, $letzte_einzahlung, $naechste_faelligkeit,
    $zahlungsstatus, $monate_gedeckt
);

$members = [];
while ($stmt->fetch()) {
    $members[] = [
        'id' => $mitglied_id,
        'name' => $name,
        'username' => $username,
        'avatar' => $avatar,
        'status' => $status,
        'pflicht_monatlich' => floatval($pflicht_monatlich),
        'konto_saldo' => floatval($konto_saldo),
        'letzte_einzahlung' => $letzte_einzahlung,
        'naechste_faelligkeit' => $naechste_faelligkeit,
        'zahlungsstatus' => $zahlungsstatus,
        'monate_gedeckt' => intval($monate_gedeckt),
        'emoji' => $zahlungsstatus === 'ueberfaellig' ? '🔴' : ($zahlungsstatus === 'gedeckt' ? '🟢' : '⚪')
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'status' => 'success',
    'data' => $members
]);

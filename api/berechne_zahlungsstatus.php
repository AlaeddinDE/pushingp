<?php
// API: Monatliches Zahlungssystem - Guthaben verwalten
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

/**
 * MONATLICHES ZAHLUNGSSYSTEM:
 * 
 * 1. Guthaben wird NUR am 1. des Monats abgezogen (monatsbeitrag)
 * 2. "Gedeckt bis" wird IMMER berechnet basierend auf: Guthaben / Monatsbeitrag
 * 3. "Nächste Zahlung fällig" ist IMMER der 1. des nächsten Monats (wenn nicht gedeckt)
 * 
 * Beispiel:
 * - Guthaben: 35€, Monatsbeitrag: 10€
 * - Gedeckt bis: 01.12 + 3 Monate = 01.03.2026
 * - Nächste Zahlung: 01.04.2026
 */

function berechneZahlungsstatus($mitglied_id) {
    global $conn;
    
    // 1. Aktuelles Guthaben aus Transaktionen holen
    $result = $conn->query("
        SELECT 
            SUM(CASE 
                WHEN typ IN ('EINZAHLUNG', 'GRUPPENAKTION_ANTEILIG', 'GUTSCHRIFT') THEN betrag 
                WHEN typ IN ('AUSZAHLUNG', 'SCHADEN') THEN -betrag 
                ELSE 0 
            END) as guthaben
        FROM transaktionen
        WHERE mitglied_id = $mitglied_id 
        AND status = 'gebucht'
    ");
    
    $row = $result->fetch_assoc();
    $guthaben = floatval($row['guthaben'] ?? 0);
    
    // 2. Monatsbeitrag holen
    $payment_status = $conn->query("
        SELECT monatsbeitrag 
        FROM member_payment_status 
        WHERE mitglied_id = $mitglied_id
    ")->fetch_assoc();
    
    $monatsbeitrag = floatval($payment_status['monatsbeitrag'] ?? 10.00);
    
    // 3. Gedeckt bis berechnen
    // Anzahl der gedeckten Monate = floor(Guthaben / Monatsbeitrag)
    $gedeckte_monate = $monatsbeitrag > 0 ? floor($guthaben / $monatsbeitrag) : 0;
    
    // Start ist immer der 1. des aktuellen Monats
    $heute = new DateTime();
    $erster_des_monats = new DateTime($heute->format('Y-m-01'));
    
    // Gedeckt bis = Erster des Monats + gedeckte Monate
    $gedeckt_bis = clone $erster_des_monats;
    if ($gedeckte_monate > 0) {
        $gedeckt_bis->modify("+{$gedeckte_monate} months");
    }
    
    // 4. Nächste Zahlung fällig
    // Immer der 1. des Monats NACH "gedeckt_bis"
    $naechste_zahlung = clone $gedeckt_bis;
    $naechste_zahlung->modify('+1 month');
    
    // 5. Update in DB
    $stmt = $conn->prepare("
        UPDATE member_payment_status 
        SET 
            guthaben = ?,
            gedeckt_bis = ?,
            naechste_zahlung_faellig = ?
        WHERE mitglied_id = ?
    ");
    
    $gedeckt_bis_str = $gedeckt_bis->format('Y-m-d');
    $naechste_zahlung_str = $naechste_zahlung->format('Y-m-d');
    
    $stmt->bind_param('dssi', $guthaben, $gedeckt_bis_str, $naechste_zahlung_str, $mitglied_id);
    $stmt->execute();
    $stmt->close();
    
    return [
        'guthaben' => $guthaben,
        'monatsbeitrag' => $monatsbeitrag,
        'gedeckte_monate' => $gedeckte_monate,
        'gedeckt_bis' => $gedeckt_bis_str,
        'naechste_zahlung_faellig' => $naechste_zahlung_str
    ];
}

// Wenn als API aufgerufen
if (isset($_GET['mitglied_id'])) {
    $mitglied_id = intval($_GET['mitglied_id']);
    $result = berechneZahlungsstatus($mitglied_id);
    echo json_encode(['status' => 'success', 'data' => $result]);
}

<?php
// CRON: Monatliche Abbuchung am 1. des Monats
require_once __DIR__ . '/../includes/db.php';

/**
 * Dieser Cronjob läuft JEDEN TAG um 00:01 Uhr
 * Aber führt nur am 1. des Monats Abbuchungen durch
 */

$heute = new DateTime();
$tag = intval($heute->format('d'));

// Nur am 1. des Monats ausführen
if ($tag !== 1) {
    echo "Kein Monatserster - übersprungen\n";
    exit;
}

echo "=== MONATLICHE ABBUCHUNG: " . $heute->format('Y-m-d') . " ===\n";

// Alle aktiven Mitglieder holen
$result = $conn->query("
    SELECT 
        u.id,
        u.name,
        mps.guthaben,
        mps.monatsbeitrag,
        mps.gedeckt_bis
    FROM users u
    JOIN member_payment_status mps ON mps.mitglied_id = u.id
    WHERE u.status = 'active'
");

$abgebucht = 0;
$fehler = 0;

while ($member = $result->fetch_assoc()) {
    $mitglied_id = $member['id'];
    $name = $member['name'];
    $guthaben = floatval($member['guthaben']);
    $monatsbeitrag = floatval($member['monatsbeitrag']);
    
    // Prüfen ob Guthaben ausreicht
    if ($guthaben >= $monatsbeitrag) {
        // Abbuchung als Transaktion eintragen
        $beschreibung = "Monatsbeitrag " . $heute->format('F Y');
        
        $stmt = $conn->prepare("
            INSERT INTO transaktionen 
            (mitglied_id, typ, betrag, beschreibung, status, datum)
            VALUES
            (?, 'AUSZAHLUNG', ?, ?, 'gebucht', NOW())
        ");
        
        $stmt->bind_param('ids', $mitglied_id, $monatsbeitrag, $beschreibung);
        
        if ($stmt->execute()) {
            echo "✅ $name: {$monatsbeitrag}€ abgebucht\n";
            $abgebucht++;
        } else {
            echo "❌ $name: Fehler beim Abbuchen\n";
            $fehler++;
        }
        
        $stmt->close();
        
        // Zahlungsstatus neu berechnen
        require_once __DIR__ . '/berechne_zahlungsstatus.php';
        berechneZahlungsstatus($mitglied_id);
        
    } else {
        echo "⚠️  $name: Guthaben zu niedrig ({$guthaben}€ < {$monatsbeitrag}€)\n";
    }
}

echo "\n=== FERTIG ===\n";
echo "Abgebucht: $abgebucht\n";
echo "Fehler: $fehler\n";

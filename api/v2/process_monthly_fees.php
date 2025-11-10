<?php
/**
 * API: Monatliche Beiträge abbuchen
 * Wird automatisch am 1. eines jeden Monats ausgeführt (via Cronjob)
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Nur für Admins oder via Cronjob (Server-Secret)
session_start();
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$cron_secret = $_GET['secret'] ?? '';
$valid_secret = 'pushingp_cron_2025'; // TODO: In env.php auslagern

if (!$is_admin && $cron_secret !== $valid_secret) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

// Startdatum der Kasse prüfen
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'kasse_start_date'");
$stmt->execute();
$stmt->bind_result($start_date);
$stmt->fetch();
$stmt->close();

if (!$start_date) {
    echo json_encode(['status' => 'error', 'error' => 'Kassen-Startdatum nicht konfiguriert']);
    exit;
}

$current_month = date('Y-m-01');
if (strtotime($current_month) < strtotime($start_date)) {
    echo json_encode(['status' => 'info', 'message' => 'Kasse noch nicht gestartet']);
    exit;
}

// Monatsbeitrag holen
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'monthly_fee'");
$stmt->execute();
$stmt->bind_result($monthly_fee);
$stmt->fetch();
$stmt->close();

$monthly_fee = floatval($monthly_fee);

// Alle aktiven Mitglieder holen
$stmt = $conn->prepare("SELECT id, name FROM users WHERE status = 'active'");
$stmt->execute();
$stmt->bind_result($user_id, $user_name);

$processed = [];
$skipped = [];
$errors = [];
$members = [];

while ($stmt->fetch()) {
    $members[] = ['id' => $user_id, 'name' => $user_name];
}
$stmt->close();

foreach ($members as $member) {
    $member_id = $member['id'];
    $member_name = $member['name'];
    
    // Prüfen ob für diesen Monat bereits abgebucht
    $check_stmt = $conn->prepare("SELECT id FROM monthly_fee_tracking WHERE mitglied_id = ? AND monat = ?");
    $check_stmt->bind_param('is', $member_id, $current_month);
    $check_stmt->execute();
    $check_stmt->bind_result($existing_id);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($existing_id) {
        $skipped[] = $member_name . ' (bereits abgebucht)';
        continue;
    }
    
    // Aktuelles Konto-Saldo prüfen
    $balance_stmt = $conn->prepare("SELECT konto_saldo FROM v_member_konto WHERE mitglied_id = ?");
    $balance_stmt->bind_param('i', $member_id);
    $balance_stmt->execute();
    $balance_stmt->bind_result($konto_saldo);
    $balance_stmt->fetch();
    $balance_stmt->close();
    
    if ($konto_saldo < $monthly_fee) {
        // Nicht genug Guthaben
        $track_stmt = $conn->prepare(
            "INSERT INTO monthly_fee_tracking (mitglied_id, monat, betrag, status, notiz) 
             VALUES (?, ?, ?, 'übersprungen', 'Nicht genug Guthaben')"
        );
        $track_stmt->bind_param('isd', $member_id, $current_month, $monthly_fee);
        $track_stmt->execute();
        $track_stmt->close();
        
        $skipped[] = $member_name . ' (unzureichendes Guthaben: €' . number_format($konto_saldo, 2) . ')';
        continue;
    }
    
    // Transaktion erstellen
    $beschreibung = "Monatsbeitrag " . date('m/Y', strtotime($current_month));
    $trans_stmt = $conn->prepare(
        "INSERT INTO transaktionen (typ, betrag, mitglied_id, beschreibung, datum, status) 
         VALUES ('MONATSBEITRAG', ?, ?, ?, NOW(), 'gebucht')"
    );
    $trans_stmt->bind_param('dis', $monthly_fee, $member_id, $beschreibung);
    $trans_stmt->execute();
    $transaction_id = $conn->insert_id;
    $trans_stmt->close();
    
    // Tracking eintragen
    $track_stmt = $conn->prepare(
        "INSERT INTO monthly_fee_tracking (mitglied_id, monat, betrag, abgebucht_am, transaktion_id, status) 
         VALUES (?, ?, ?, NOW(), ?, 'abgebucht')"
    );
    $track_stmt->bind_param('isdi', $member_id, $current_month, $monthly_fee, $transaction_id);
    $track_stmt->execute();
    $track_stmt->close();
    
    $processed[] = $member_name . ' (€' . number_format($monthly_fee, 2) . ')';
}

echo json_encode([
    'status' => 'success',
    'monat' => $current_month,
    'monatsbeitrag' => $monthly_fee,
    'processed' => $processed,
    'skipped' => $skipped,
    'errors' => $errors,
    'summary' => [
        'abgebucht' => count($processed),
        'übersprungen' => count($skipped),
        'fehler' => count($errors)
    ]
]);

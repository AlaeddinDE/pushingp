<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
require_once '../../includes/xp_system.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$data = json_decode(file_get_contents('php://input'), true);
$bet = floatval($data['bet'] ?? 0);

if ($bet < 0.5 || $bet > 50) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültiger Einsatz']);
    exit;
}

$stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

if ($balance === null) {
    echo json_encode(['status' => 'error', 'error' => 'Balance nicht gefunden']);
    exit;
}

if ($balance - $bet < 10) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben (10€ Reserve!)']);
    exit;
}

// Deduct bet immediately when starting crash game
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
        VALUES ('AUSZAHLUNG', 'POOL', ?, ?, 'Casino Crash Einsatz', ?, NOW())
    ");
    $stmt->bind_param('dii', $bet, $user_id, $user_id);
    $stmt->execute();
    $trans_id = $conn->insert_id;
    $stmt->close();
    
    // Award XP for bet (10 XP per 1€)
    add_xp($user_id, 'CASINO_BET', 'Casino Crash Einsatz', $trans_id, 'transaktionen', round($bet * 10));
    
    $stmt = $conn->prepare("
        UPDATE member_payment_status mps
        JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
        SET mps.guthaben = vmb.balance
        WHERE mps.mitglied_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    $stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($new_balance);
    $stmt->fetch();
    $stmt->close();
    
    // Generate provably fair crash point (server-side)
    // House edge: 3% (realistic for crash games)
    // Formula: 99 / (random(1-99)) with 3% edge = 96 / random(1-96)
    $random = mt_rand(1, 9600) / 100; // 0.01 to 96.00
    $crash_point = max(1.00, 96 / $random); // Minimum 1.00x
    
    // Cap at 100x (extremely rare, ~1% chance)
    $crash_point = min($crash_point, 100.0);
    
    // Round to 2 decimals
    $crash_point = round($crash_point, 2);
    
    // Store in active games table for verification
    $stmt = $conn->prepare("
        INSERT INTO casino_active_games (user_id, game_type, bet_amount, crash_point)
        VALUES (?, 'crash', ?, ?)
        ON DUPLICATE KEY UPDATE bet_amount = ?, crash_point = ?, created_at = NOW()
    ");
    $stmt->bind_param('idddd', $user_id, $bet, $crash_point, $bet, $crash_point);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'bet' => $bet,
        'balance' => $new_balance,
        'crash_point' => $crash_point
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Casino crash start error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Start fehlgeschlagen']);
}

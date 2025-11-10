<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$data = json_decode(file_get_contents('php://input'), true);
$bet = floatval($data['bet'] ?? 0);
$multiplier = floatval($data['multiplier'] ?? 0);

if ($bet <= 0 || $multiplier <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Daten']);
    exit;
}

$win_amount = $bet * $multiplier;

$conn->begin_transaction();

try {
    // Verify crash point from active game
    $stmt = $conn->prepare("SELECT crash_point FROM casino_active_games WHERE user_id = ? AND game_type = 'crash'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($server_crash_point);
    $stmt->fetch();
    $stmt->close();
    
    if ($server_crash_point === null) {
        throw new Exception('Kein aktives Spiel gefunden');
    }
    
    // Prevent cashout if multiplier exceeds server crash point
    if ($multiplier > $server_crash_point) {
        throw new Exception('Ungültiger Multiplikator');
    }
    
    // Add winnings
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
        VALUES ('EINZAHLUNG', 'POOL', ?, ?, 'Casino Crash Gewinn', ?, NOW())
    ");
    $stmt->bind_param('dii', $win_amount, $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("
        UPDATE member_payment_status mps
        JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
        SET mps.guthaben = vmb.balance
        WHERE mps.mitglied_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    $result = ['multiplier' => $multiplier, 'win' => $win_amount];
    $result_json = json_encode($result);
    $stmt = $conn->prepare("
        INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result)
        VALUES (?, 'crash', ?, ?, ?, ?)
    ");
    $stmt->bind_param('iddds', $user_id, $bet, $win_amount, $multiplier, $result_json);
    $stmt->execute();
    $stmt->close();
    
    // Remove from active games
    $stmt = $conn->prepare("DELETE FROM casino_active_games WHERE user_id = ? AND game_type = 'crash'");
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
    
    echo json_encode([
        'status' => 'success',
        'win_amount' => $win_amount,
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Casino crash cashout error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Cashout fehlgeschlagen']);
}

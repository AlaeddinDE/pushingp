<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();

header('Content-Type: application/json');

try {
    if (!is_logged_in()) {
        echo json_encode(['status' => 'error', 'error' => 'Nicht eingeloggt']);
        exit;
    }

    $user_id = get_current_user_id();
    $username = $_SESSION['username'];

    // Get balance from transaktionen
    $stmt = $conn->prepare("SELECT 
        COALESCE(SUM(CASE 
            WHEN typ = 'EINZAHLUNG' THEN betrag
            WHEN typ IN ('AUSZAHLUNG', 'SCHADEN') THEN -betrag
            ELSE 0 
        END), 0) as balance 
        FROM transaktionen 
        WHERE mitglied_id = ? AND status = 'gebucht'");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();

    // 10€ Reserve abziehen
    $available_balance = max(0, $balance - 10);

    echo json_encode([
        'status' => 'success',
        'balance' => round($available_balance, 2)
    ]);
} catch (Exception $e) {
    error_log("get_balance.php Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Datenbankfehler']);
}

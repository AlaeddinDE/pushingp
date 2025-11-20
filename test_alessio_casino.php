<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();

echo "<h1>Casino Debug für Alessio</h1>";
echo "<pre>";

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'NONE';

echo "SESSION INFO:\n";
echo "user_id: " . $user_id . "\n";
echo "username: " . $username . "\n\n";

// Get balance
$balance = 0.0;
$stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

$balance = floatval($balance ?? 0);
$casino_available_balance = max(0, $balance - 10.00);
$casino_locked = ($balance <= 10.00);

echo "BALANCE INFO:\n";
echo "Gesamtguthaben: " . number_format($balance, 2) . "€\n";
echo "Casino verfügbar: " . number_format($casino_available_balance, 2) . "€\n";
echo "Casino gesperrt: " . ($casino_locked ? 'JA ❌' : 'NEIN ✅') . "\n";
echo "Bedingung (\$balance <= 10.00): " . ($balance <= 10.00 ? 'TRUE' : 'FALSE') . "\n";

echo "</pre>";

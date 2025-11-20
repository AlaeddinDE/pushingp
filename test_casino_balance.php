<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'Unknown';

echo "<h1>Casino Balance Debug</h1>";
echo "<p><strong>Session User ID:</strong> $user_id</p>";
echo "<p><strong>Session Username:</strong> $username</p>";

// Get user balance
$balance = 0.0;
$stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

$balance = floatval($balance ?? 0);
$casino_locked = ($balance < 10.00);

echo "<p><strong>Balance:</strong> " . number_format($balance, 2, ',', '.') . " €</p>";
echo "<p><strong>Casino Locked:</strong> " . ($casino_locked ? 'JA 🔒' : 'NEIN ✅') . "</p>";

// Test query directly
$result = $conn->query("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = $user_id");
$row = $result->fetch_assoc();
echo "<p><strong>Direct Query Balance:</strong> " . ($row['balance'] ?? 'NULL') . "</p>";
?>

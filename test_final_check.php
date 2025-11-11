<?php
/**
 * FINAL CHECK - Tests APIs without browser cache
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();

// Create/login test user
$test_user = 'test_final';
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param('s', $test_user);
$stmt->execute();
$stmt->bind_result($uid);
if (!$stmt->fetch()) {
    $uid = null;
}
$stmt->close();

if (!$uid) {
    $hash = password_hash('test', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, name, password) VALUES (?, 'Test', ?)");
    $stmt->bind_param('ss', $test_user, $hash);
    $stmt->execute();
    $uid = $conn->insert_id;
    $stmt->close();
}

$_SESSION['user_id'] = $uid;
$_SESSION['username'] = $test_user;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Final API Check</title>
    <style>
        body { background: #0f172a; color: white; font-family: monospace; padding: 20px; }
        .test { padding: 10px; margin: 10px 0; border-radius: 8px; }
        .pass { background: #065f46; border-left: 4px solid #10b981; }
        .fail { background: #7f1d1d; border-left: 4px solid #ef4444; }
        pre { background: #1e293b; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Final API Check (No Cache)</h1>
    <p>Timestamp: <?= time() ?></p>
    
    <?php
    function testAPI($name, $endpoint, $data) {
        echo "<div class='test-item'><h3>$name</h3>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://pushingp.de" . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . "=" . session_id());
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $json = json_decode($response, true);
        $success = ($http_code == 200 && $json !== null && !isset($json['error']));
        
        $class = $success ? 'pass' : 'fail';
        echo "<div class='test $class'>";
        echo "<strong>" . ($success ? "✅ PASS" : "❌ FAIL") . "</strong> - HTTP $http_code<br>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 300)) . "</pre>";
        echo "</div></div>";
        
        return $success;
    }
    
    $total = 0;
    $passed = 0;
    
    // Test Mines
    testAPI('Mines - Start', '/api/casino/play_mines.php', ['action' => 'start', 'bet' => 1.0, 'mines' => 3]) && $passed++;
    $total++;
    
    testAPI('Mines - Reveal', '/api/casino/play_mines.php', ['action' => 'reveal', 'position' => 0]) && $passed++;
    $total++;
    
    // Test Slots
    testAPI('Slots - Spin', '/api/casino/play_slots.php', ['bet' => 1.0]) && $passed++;
    $total++;
    
    // Test Blackjack
    testAPI('Blackjack - Deal', '/api/casino/play_blackjack.php', ['action' => 'deal', 'bet' => 1.0]) && $passed++;
    $total++;
    
    // Test Chicken
    testAPI('Chicken - Start', '/api/casino/chicken_cross.php', ['action' => 'start', 'bet' => 1.0]) && $passed++;
    $total++;
    
    echo "<h2>Result: $passed / $total Tests Passed</h2>";
    ?>
</body>
</html>

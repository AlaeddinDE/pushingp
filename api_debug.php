<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();

// Login test user
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';

echo "<html><body style='background: #0f172a; color: white; font-family: monospace; padding: 20px;'>";
echo "<h1>🔍 API Debug Test</h1>";
echo "<p>Testing all APIs with real requests...</p><hr>";

function testAPI($name, $endpoint, $data) {
    echo "<h3>Testing: $name</h3>";
    
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
    
    echo "HTTP Code: <strong>$http_code</strong><br>";
    echo "Response Length: <strong>" . strlen($response) . " bytes</strong><br>";
    
    if (strlen($response) == 0) {
        echo "<div style='background: #7f1d1d; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ EMPTY RESPONSE - This will cause JSON parse error!";
        echo "</div>";
    } else {
        $json = json_decode($response, true);
        if ($json === null) {
            echo "<div style='background: #7f1d1d; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ INVALID JSON<br>";
            echo "Raw: <pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
            echo "</div>";
        } else {
            echo "<div style='background: #065f46; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "✅ VALID JSON<br>";
            echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
            echo "</div>";
        }
    }
    echo "<hr>";
}

// Test all APIs
testAPI('Mines - Start', '/api/casino/play_mines.php', ['action' => 'start', 'bet' => 1.0, 'mines' => 3]);
testAPI('Mines - Reveal', '/api/casino/play_mines.php', ['action' => 'reveal', 'position' => 0]);
testAPI('Slots - Spin', '/api/casino/play_slots.php', ['bet' => 1.0]);
testAPI('Plinko - Drop', '/api/casino/play_plinko.php', ['bet' => 1.0]);
testAPI('Crash - Start', '/api/casino/start_crash.php', ['bet' => 1.0]);
testAPI('Blackjack - Deal', '/api/casino/play_blackjack.php', ['action' => 'deal', 'bet' => 1.0]);
testAPI('Chicken - Start', '/api/casino/chicken_cross.php', ['action' => 'start', 'bet' => 1.0]);

echo "</body></html>";

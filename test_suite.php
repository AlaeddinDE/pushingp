<?php
/**
 * CASINO TEST SUITE - Automatisches Testing aller Games & Features
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();

// Test-User erstellen falls nicht vorhanden
$test_username = 'test_auto';
$test_user_id = null;

// Prüfe ob Test-User existiert
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param('s', $test_username);
$stmt->execute();
$stmt->bind_result($test_user_id);
if (!$stmt->fetch()) {
    $test_user_id = null;
}
$stmt->close();

// Erstelle Test-User falls nötig
if (!$test_user_id) {
    $password_hash = password_hash('test123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, name, password, is_admin) VALUES (?, 'Test User', ?, 0)");
    $stmt->bind_param('ss', $test_username, $password_hash);
    $stmt->execute();
    $test_user_id = $conn->insert_id;
    $stmt->close();
    
    // Gebe Test-User 100€ Guthaben
    $stmt = $conn->prepare("INSERT INTO transactions_v2 (username, amount, type, description, created_by) VALUES (?, 100.00, 'admin', 'Test-Guthaben', 'system')");
    $stmt->bind_param('s', $test_username);
    $stmt->execute();
    $stmt->close();
}

// Login als Test-User
$_SESSION['user_id'] = $test_user_id;
$_SESSION['username'] = $test_username;
$_SESSION['name'] = 'Test User';
$_SESSION['is_admin'] = false;

$results = [];
$errors = [];

function test_page($url, $name) {
    global $results, $errors;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://pushingp.de" . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . "=" . session_id());
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $success = ($http_code == 200);
    
    if ($success) {
        // Prüfe auf PHP-Fehler im Output
        if (stripos($response, 'Fatal error') !== false || 
            stripos($response, 'Parse error') !== false ||
            stripos($response, 'Warning:') !== false) {
            $success = false;
            $errors[] = "$name: PHP Fehler gefunden";
        }
        
        // Prüfe auf kritische fehlende Elemente
        if ($url !== '/casino.php' && stripos($response, 'balance') === false) {
            $errors[] = "$name: Balance Display fehlt";
        }
    } else {
        $errors[] = "$name: HTTP $http_code";
    }
    
    $results[] = [
        'name' => $name,
        'url' => $url,
        'status' => $success ? 'OK' : 'FEHLER',
        'http_code' => $http_code
    ];
    
    return $success;
}

function test_api($endpoint, $data, $name) {
    global $results, $errors;
    
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
    $success = ($http_code == 200 && $json !== null);
    
    if (!$success) {
        $errors[] = "$name: API Fehler - HTTP $http_code";
    } elseif (isset($json['status']) && $json['status'] === 'error') {
        $errors[] = "$name: " . ($json['error'] ?? 'Unbekannter Fehler');
    }
    
    $results[] = [
        'name' => $name,
        'endpoint' => $endpoint,
        'status' => $success ? 'OK' : 'FEHLER',
        'response' => $json
    ];
    
    return [$success, $json];
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casino Test Suite</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { background: #0f172a; color: white; padding: 20px; font-family: monospace; }
        .test-container { max-width: 1200px; margin: 0 auto; }
        .test-section { background: #1e293b; padding: 20px; margin: 20px 0; border-radius: 12px; }
        .test-result { padding: 12px; margin: 8px 0; border-radius: 8px; display: flex; justify-content: space-between; }
        .test-ok { background: #065f46; border-left: 4px solid #10b981; }
        .test-error { background: #7f1d1d; border-left: 4px solid #ef4444; }
        .test-warning { background: #78350f; border-left: 4px solid #f59e0b; }
        .progress { width: 100%; height: 30px; background: #1e293b; border-radius: 8px; overflow: hidden; margin: 20px 0; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #10b981, #3b82f6); transition: width 0.3s; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        pre { background: #0f172a; padding: 12px; border-radius: 8px; overflow-x: auto; }
        .btn { padding: 12px 24px; background: linear-gradient(135deg, #8b5cf6, #ec4899); border: none; border-radius: 8px; color: white; font-weight: bold; cursor: pointer; margin: 8px; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Casino Test Suite</h1>
        <p>Automatisches Testing aller Games & Features</p>
        
        <div class="progress">
            <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
        </div>
        
        <div class="test-section">
            <h2>📄 Seiten Tests</h2>
            <?php
            $total_tests = 0;
            $passed_tests = 0;
            
            echo "<div class='test-result " . (test_page('/casino.php', 'Casino Hauptseite') ? 'test-ok' : 'test-error') . "'>";
            echo "<span>Casino Hauptseite</span><span>" . ($results[count($results)-1]['status']) . "</span></div>";
            
            $games = [
                '/games/slots.php' => 'Slots',
                '/games/plinko.php' => 'Plinko',
                '/games/crash.php' => 'Crash',
                '/games/blackjack.php' => 'Blackjack',
                '/games/chicken.php' => 'Chicken',
                '/games/mines.php' => 'Mines'
            ];
            
            foreach ($games as $url => $name) {
                $success = test_page($url, $name);
                echo "<div class='test-result " . ($success ? 'test-ok' : 'test-error') . "'>";
                echo "<span>$name Game</span><span>" . ($success ? 'OK' : 'FEHLER') . "</span></div>";
                $total_tests++;
                if ($success) $passed_tests++;
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>🎮 API Tests</h2>
            <?php
            // Test Mines API
            list($success, $data) = test_api('/api/casino/play_mines.php', ['action' => 'start', 'bet' => 1.0, 'mines' => 3], 'Mines Start');
            echo "<div class='test-result " . ($success ? 'test-ok' : 'test-error') . "'>";
            echo "<span>Mines API - Start</span><span>" . ($success ? 'OK' : 'FEHLER') . "</span></div>";
            $total_tests++;
            if ($success) $passed_tests++;
            
            // Wenn Mines Start erfolgreich, teste reveal
            if ($success) {
                list($success2, $data2) = test_api('/api/casino/play_mines.php', ['action' => 'reveal', 'position' => 0], 'Mines Reveal');
                echo "<div class='test-result " . ($success2 ? 'test-ok' : 'test-error') . "'>";
                echo "<span>Mines API - Reveal</span><span>" . ($success2 ? 'OK' : 'FEHLER') . "</span></div>";
                $total_tests++;
                if ($success2) $passed_tests++;
            }
            
            // Test Balance API
            list($success, $data) = test_api('/api/casino/get_balance.php', [], 'Balance Check');
            echo "<div class='test-result " . ($success ? 'test-ok' : 'test-error') . "'>";
            echo "<span>Balance API</span><span>" . ($success ? 'OK' : 'FEHLER') . "</span></div>";
            $total_tests++;
            if ($success) $passed_tests++;
            ?>
        </div>
        
        <div class="test-section">
            <h2>❌ Gefundene Fehler (<?= count($errors) ?>)</h2>
            <?php
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    echo "<div class='test-result test-error'><span>$error</span></div>";
                }
            } else {
                echo "<div class='test-result test-ok'><span>Keine Fehler gefunden! 🎉</span></div>";
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>📊 Zusammenfassung</h2>
            <div class='test-result test-<?= $passed_tests === $total_tests ? 'ok' : 'warning' ?>'>
                <span>Tests bestanden: <?= $passed_tests ?> / <?= $total_tests ?></span>
                <span><?= round(($passed_tests / $total_tests) * 100) ?>%</span>
            </div>
        </div>
        
        <div class="test-section">
            <h2>🔧 Aktionen</h2>
            <a href="/casino.php"><button class="btn">🎰 Zum Casino</button></a>
            <button class="btn" onclick="location.reload()">🔄 Tests neu ausführen</button>
        </div>
        
        <script>
            document.getElementById('progressBar').style.width = '<?= round(($passed_tests / $total_tests) * 100) ?>%';
            document.getElementById('progressBar').textContent = '<?= round(($passed_tests / $total_tests) * 100) ?>%';
        </script>
    </div>
</body>
</html>

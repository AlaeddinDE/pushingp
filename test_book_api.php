<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Simulate logged in user for testing
$_SESSION['user_id'] = 1; // Change to your user ID
$_SESSION['username'] = 'test';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Book API Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
        pre { background: #000; padding: 10px; border: 1px solid #0f0; margin: 10px 0; }
        .error { color: #f00; }
        .success { color: #0f0; }
    </style>
</head>
<body>
    <h1>🅿️ Book of P API Test</h1>
    
    <button onclick="testAPI()">Test API (1€ Bet)</button>
    <button onclick="clearLog()">Clear Log</button>
    
    <div id="log"></div>

    <script>
        async function testAPI() {
            const log = document.getElementById('log');
            log.innerHTML += '<hr><h3>🎰 Starting new test...</h3>';
            
            try {
                log.innerHTML += '<p>📤 Sending request to /api/casino/play_book.php...</p>';
                
                const response = await fetch('/api/casino/play_book.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        bet: 1.0,
                        freespin: false
                    })
                });
                
                log.innerHTML += `<p class="success">✅ Response Status: ${response.status}</p>`;
                
                const responseText = await response.text();
                log.innerHTML += '<p>📥 Raw Response:</p>';
                log.innerHTML += `<pre>${responseText}</pre>`;
                
                try {
                    const data = JSON.parse(responseText);
                    log.innerHTML += '<p class="success">✅ Valid JSON!</p>';
                    log.innerHTML += '<p>📊 Parsed Data:</p>';
                    log.innerHTML += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
                    
                    if (data.status === 'success') {
                        log.innerHTML += '<p class="success">🎉 API CALL SUCCESSFUL!</p>';
                        log.innerHTML += `<p>Result: ${data.result.join(' ')}</p>`;
                        log.innerHTML += `<p>Win: ${data.win_amount}€ (${data.multiplier}x)</p>`;
                    } else {
                        log.innerHTML += `<p class="error">❌ API returned error: ${data.error}</p>`;
                    }
                } catch (e) {
                    log.innerHTML += `<p class="error">❌ JSON Parse Error: ${e.message}</p>`;
                }
                
            } catch (error) {
                log.innerHTML += `<p class="error">❌ Fetch Error: ${error.message}</p>`;
            }
        }
        
        function clearLog() {
            document.getElementById('log').innerHTML = '';
        }
    </script>
</body>
</html>

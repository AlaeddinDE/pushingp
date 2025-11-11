<?php
/**
 * Save Casino Game History
 * Records game results for statistics
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Nicht eingeloggt']);
    exit;
}

$user_id = get_current_user_id();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['game']) || !isset($input['bet_amount']) || !isset($input['win_amount'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Parameter']);
    exit;
}

$game = strval($input['game']);
$bet_amount = floatval($input['bet_amount']);
$win_amount = floatval($input['win_amount']);

// Validate
$valid_games = ['slots', 'plinko', 'crash', 'blackjack', 'chicken'];
if (!in_array($game, $valid_games)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Ungültiges Spiel']);
    exit;
}

if ($bet_amount < 0 || $win_amount < 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Beträge']);
    exit;
}

try {
    // Check if casino_history table exists
    $result = $conn->query("SHOW TABLES LIKE 'casino_history'");
    
    if ($result->num_rows === 0) {
        // Create table if not exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS casino_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                game VARCHAR(50) NOT NULL,
                bet_amount DECIMAL(10,2) NOT NULL,
                win_amount DECIMAL(10,2) NOT NULL,
                profit DECIMAL(10,2) NOT NULL,
                multiplier DECIMAL(10,2) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_game (game),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Calculate profit and multiplier
    $profit = $win_amount - $bet_amount;
    $multiplier = $bet_amount > 0 ? ($win_amount / $bet_amount) : 0;

    // Insert history
    $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game, bet_amount, win_amount, profit, multiplier) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdddd", $user_id, $game, $bet_amount, $win_amount, $profit, $multiplier);
    
    if (!$stmt->execute()) {
        throw new Exception("Fehler beim Speichern der Historie");
    }
    
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'message' => 'Historie gespeichert'
    ]);

} catch (Exception $e) {
    error_log("save_history.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Datenbankfehler']);
}

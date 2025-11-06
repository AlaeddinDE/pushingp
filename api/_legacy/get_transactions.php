<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    $name = $_GET['name'] ?? $user;
    $limit = intval($_GET['limit'] ?? 50);
    $limit = min(max($limit, 1), 200); // Max 200, Min 1
    
    // Nur eigene Transaktionen oder Admin
    if (!$isAdmin && $name !== $user) {
        json_response(['error' => 'Zugriff verweigert'], 403);
    }
    
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE name = ? ORDER BY date DESC, uid DESC LIMIT ?");
    $stmt->bind_param("si", $name, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    json_response($data);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>

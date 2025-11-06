<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Nur für Admins - alle Transaktionen
if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

try {
    $limit = intval($_GET['limit'] ?? 100);
    $limit = min(max($limit, 1), 500);
    
    $stmt = $conn->prepare("SELECT * FROM transactions ORDER BY date DESC, uid DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    json_response($data);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>


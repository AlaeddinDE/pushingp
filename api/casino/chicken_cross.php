<?php
/**
 * Chicken Game - Cross Street API
 * Determines if the chicken survives crossing the street
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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['street']) || !isset($input['survival_rate'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Parameter']);
    exit;
}

$street = intval($input['street']);
$survivalRate = floatval($input['survival_rate']);

// Validate parameters
if ($street < 1 || $street > 50) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Straßennummer']);
    exit;
}

if ($survivalRate <= 0 || $survivalRate >= 1) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Überlebensrate']);
    exit;
}

// Determine if chicken survives
// Random number between 0 and 1
// If random < survival_rate, chicken survives
$randomValue = mt_rand() / mt_getrandmax();
$survived = ($randomValue < $survivalRate);

// Log for transparency (optional)
error_log("Chicken Cross - Street: $street, Random: $randomValue, Survival Rate: $survivalRate, Survived: " . ($survived ? 'YES' : 'NO'));

echo json_encode([
    'status' => 'success',
    'survived' => $survived,
    'street' => $street,
    'random_value' => round($randomValue, 4)
]);

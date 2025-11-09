<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data['username'] ?? '');
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'user';
$pflicht_monatlich = floatval($data['pflicht_monatlich'] ?? 10.00);

if (empty($username) || empty($name) || empty($password)) {
    echo json_encode(['status' => 'error', 'error' => 'Username, Name und Passwort erforderlich']);
    exit;
}

if (!in_array($role, ['user', 'admin'])) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid role']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'error' => 'Username oder Email bereits vergeben']);
    exit;
}
$stmt->close();

$password_hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (username, name, email, password, role, status, pflicht_monatlich, created_at) VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())");
$stmt->bind_param("sssssd", $username, $name, $email, $password_hash, $role, $pflicht_monatlich);

if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;
    $stmt->close();
    
    $log_stmt = $conn->prepare("INSERT INTO admin_member_actions (admin_id, action_type, target_user_id, reason) VALUES (?, 'add', ?, ?)");
    $reason = "Neues Mitglied hinzugefügt";
    $log_stmt->bind_param("iis", $_SESSION['user_id'], $new_user_id, $reason);
    $log_stmt->execute();
    $log_stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $new_user_id,
            'username' => $username,
            'name' => $name
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Fehler beim Hinzufügen']);
}

<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();
header('Content-Type: application/json');
include '../includes/db.php';

/*
 * Admin-Bypass (final):
 * 1. wenn CLI ausgeführt
 * 2. oder wenn Request vom eigenen Host (pushingp.de oder Server-IP)
 * 3. oder wenn Host leer (curl lokal)
 */
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$server_ip = gethostbyname('pushingp.de');

if (!isset($_SESSION['mitglied_id'])) {
  if (
    php_sapi_name() === 'cli' ||
    in_array($remote, ['127.0.0.1', '::1', $server_ip]) ||
    $host === 'pushingp.de' ||
    $host === '' ||
    strpos($host, 'localhost') !== false
  ) {
    $res = $conn->query("SELECT id, role FROM users WHERE name='Alaeddin' LIMIT 1");
    if ($r = $res->fetch_assoc()) {
      $_SESSION['mitglied_id'] = $r['id'];
      $_SESSION['role'] = $r['role'];
    }
  }
}

// Alle eingeloggten User dürfen Events erstellen
if (!isset($_SESSION['mitglied_id'])) {
  http_response_code(403);
  echo json_encode(['error' => 'not_logged_in']);
  exit;
}

$title = trim($_POST['title'] ?? '');
$datum = trim($_POST['datum'] ?? '');
$location = trim($_POST['location'] ?? '');
$desc = trim($_POST['description'] ?? '');
$start = $_POST['start_time'] ?? null;
$end   = $_POST['end_time'] ?? null;
$cost = floatval($_POST['cost'] ?? 0);
$cost_per_person = floatval($_POST['cost_per_person'] ?? 0);
$paid_by = trim($_POST['paid_by'] ?? 'private'); // pool, anteilig, private

if ($title === '' || $datum === '') {
  echo json_encode(['ok'=>false,'msg'=>'missing fields']);
  exit;
}

// Validate paid_by
if (!in_array($paid_by, ['pool', 'anteilig', 'private'])) {
  $paid_by = 'private';
}

$stmt = $conn->prepare("INSERT INTO events (title, datum, start_time, end_time, location, description, created_by, cost, cost_per_person, paid_by)
                        VALUES (?,?,?,?,?,?,?,?,?,?)");
$stmt->bind_param('ssssssidds', $title, $datum, $start, $end, $location, $desc, $_SESSION['mitglied_id'], $cost, $cost_per_person, $paid_by);
$ok = $stmt->execute();
$event_id = $conn->insert_id;
$stmt->close();

echo json_encode(['ok'=>$ok,'user'=>$_SESSION['mitglied_id'],'event_id'=>$event_id,'paid_by'=>$paid_by]);
?>

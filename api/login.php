<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__.'/../includes/db.php';

$name = trim($_POST['name'] ?? '');
$pin  = trim($_POST['pin'] ?? '');

if ($name === '' || $pin === '') {
  echo json_encode(['error'=>'Name und PIN erforderlich']); exit;
}

$stmt = $mysqli->prepare('SELECT id, pin, flag, is_locked FROM members WHERE name=? LIMIT 1');
if(!$stmt){
  echo json_encode(['error'=>'Datenbankfehler']);
  exit;
}
$stmt->bind_param('s',$name);
$stmt->execute();
$stmt->bind_result($member_id, $db_pin, $flag, $is_locked);
$found = $stmt->fetch();
$stmt->close();

if (!$found) { echo json_encode(['error'=>'Mitglied nicht gefunden']); exit; }
$is_locked = (int)$is_locked === 1;
if ($is_locked) { echo json_encode(['error'=>'Mitglied ist gesperrt']); exit; }
if ($db_pin !== $pin) { echo json_encode(['error'=>'Falsche PIN']); exit; }

// Prüfe ob Mitglied Admin ist (entweder über member_name oder über PIN für Rückwärtskompatibilität)
$isAdmin = false;
$as = $mysqli->prepare('SELECT 1 FROM admins WHERE member_name=? OR pin=? LIMIT 1');
$as->bind_param('ss', $name, $pin);
$as->execute();
$as->bind_result($dummy);
if ($as->fetch()) $isAdmin = true;
$as->close();

$roles = ['member'];
if ($isAdmin) {
  $roles[] = 'admin';
}

$_SESSION['user'] = [
  'id' => (int)$member_id,
  'name' => $name,
  'display_name' => $name,
  'flag' => $flag,
  'roles' => $roles,
];
$_SESSION['is_admin'] = $isAdmin;

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['status'=>'ok','admin'=>$isAdmin]);

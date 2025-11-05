<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__.'/../includes/db.php';

$name = trim($_POST['name'] ?? '');
$pin  = trim($_POST['pin'] ?? '');

if ($name === '' || $pin === '') {
  echo json_encode(['error'=>'Name und PIN erforderlich']); exit;
}

$stmt = $mysqli->prepare('SELECT pin FROM members WHERE name=? LIMIT 1');
$stmt->bind_param('s',$name);
$stmt->execute();
$stmt->bind_result($db_pin);
$found = $stmt->fetch();
$stmt->close();

if (!$found) { echo json_encode(['error'=>'Mitglied nicht gefunden']); exit; }
if ($db_pin !== $pin) { echo json_encode(['error'=>'Falsche PIN']); exit; }

// Prüfe ob Mitglied Admin ist (entweder über member_name oder über PIN für Rückwärtskompatibilität)
$isAdmin = false;
$as = $mysqli->prepare('SELECT 1 FROM admins WHERE member_name=? OR pin=? LIMIT 1');
$as->bind_param('ss', $name, $pin);
$as->execute();
$as->bind_result($dummy);
if ($as->fetch()) $isAdmin = true;
$as->close();

$_SESSION['user'] = $name;
$_SESSION['is_admin'] = $isAdmin;

echo json_encode(['status'=>'ok','admin'=>$isAdmin]);

<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__.'/../includes/db.php';

function respond_error(string $message, int $statusCode = 400): void {
  http_response_code($statusCode);
  echo json_encode(['error' => $message]);
  exit;
}

$name = trim($_POST['name'] ?? '');
$pin  = trim($_POST['pin'] ?? '');

if ($name === '' || $pin === '') {
  respond_error('Name und PIN erforderlich');
}

$memberId = null;
$dbPin     = null;
$flag      = null;
$status    = 'active';
$isLocked  = false;

// Versuche bevorzugt die neue v2-Tabelle auszulesen
$stmtV2 = $conn->prepare('SELECT id, pin_plain, flag, status FROM members_v2 WHERE name=? LIMIT 1');
if ($stmtV2) {
  $stmtV2->bind_param('s', $name);
  $stmtV2->execute();
  $stmtV2->bind_result($memberId, $dbPin, $flag, $status);
  $foundV2 = $stmtV2->fetch();
  $stmtV2->close();

  if ($foundV2) {
    $status = $status ?? 'active';
    $isLocked = in_array($status, ['inactive', 'banned'], true);
  }
}

// Fallback auf Legacy-Struktur, falls kein Treffer in v2
if ($memberId === null) {
  $stmtLegacy = $conn->prepare('SELECT id, pin, flag, is_locked FROM members WHERE name=? LIMIT 1');
  if (!$stmtLegacy) {
    respond_error('Datenbankfehler', 500);
  }
  $stmtLegacy->bind_param('s', $name);
  $stmtLegacy->execute();
  $stmtLegacy->bind_result($memberId, $dbPin, $flag, $legacyLocked);
  $foundLegacy = $stmtLegacy->fetch();
  $stmtLegacy->close();

  if (!$foundLegacy) {
    respond_error('Mitglied nicht gefunden');
  }

  $isLocked = (int)$legacyLocked === 1;
}

if ($isLocked) {
  respond_error('Mitglied ist gesperrt');
}

if ($dbPin === null || $dbPin === '') {
  respond_error('PIN ist nicht gesetzt', 500);
}

if ($dbPin !== $pin) {
  respond_error('Falsche PIN');
}

// Prüfe Admin-Rechte – zuerst v2, dann Legacy als Fallback
$isAdmin = false;

$stmtAdminV2 = $conn->prepare('SELECT is_admin FROM admins_v2 WHERE member_id=? LIMIT 1');
if ($stmtAdminV2) {
  $stmtAdminV2->bind_param('i', $memberId);
  $stmtAdminV2->execute();
  $stmtAdminV2->bind_result($isAdminFlag);
  if ($stmtAdminV2->fetch()) {
    $isAdmin = (int)$isAdminFlag === 1;
  }
  $stmtAdminV2->close();
} else {
  $stmtAdminLegacy = $conn->prepare('SELECT 1 FROM admins WHERE member_name=? OR pin=? LIMIT 1');
  if ($stmtAdminLegacy) {
    $stmtAdminLegacy->bind_param('ss', $name, $pin);
    $stmtAdminLegacy->execute();
    $stmtAdminLegacy->bind_result($dummy);
    if ($stmtAdminLegacy->fetch()) {
      $isAdmin = true;
    }
    $stmtAdminLegacy->close();
  }
}

$roles = ['member'];
if ($isAdmin) {
  $roles[] = 'admin';
}

$_SESSION['user'] = [
  'id' => (int)$memberId,
  'name' => $name,
  'display_name' => $name,
  'flag' => $flag,
  'roles' => $roles,
];
$_SESSION['is_admin'] = $isAdmin;

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['status' => 'ok', 'admin' => $isAdmin]);

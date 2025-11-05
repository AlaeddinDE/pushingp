<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Mock-Daten - später durch echte Discord API ersetzen
$members = [
  ['name' => 'Alaeddin', 'online' => true],
  ['name' => 'Adis', 'online' => false],
  ['name' => 'Vagif', 'online' => true],
];

$count = count(array_filter($members, fn($m) => $m['online']));
json_response(['count' => $count, 'members' => $members]);
?>

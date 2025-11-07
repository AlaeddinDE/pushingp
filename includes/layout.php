<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/db.php';

// Aktueller Saldo
$res = $conn->query("SELECT SUM(betrag) AS saldo FROM transaktionen");
$saldo = $res && $r = $res->fetch_assoc() ? round($r['saldo'],2) : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>PushingP Dashboard</title>
<style>
body{margin:0;font-family:Inter,Arial;background:#0d0d0d;color:#ddd;display:flex;min-height:100vh;}
aside{width:220px;background:#111;padding:20px;display:flex;flex-direction:column;gap:15px;}
aside a{color:#ccc;text-decoration:none;font-weight:500;}
aside a:hover{color:#00adee;}
header{background:#111;padding:15px 25px;font-size:20px;color:#fff;display:flex;justify-content:space-between;align-items:center;}
main{flex:1;padding:30px;}
.card{background:#111;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 0 10px #0003;}
.btn{background:#00adee;color:#fff;padding:8px 14px;border:none;border-radius:6px;cursor:pointer;}
.btn:hover{background:#00c4ff;}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px;border-bottom:1px solid #222;}
th{color:#00adee;text-align:left;}
.status-on{color:#0f0;}
.status-off{color:#f33;}
</style>
</head>
<body>
<aside>
  <a href="/index.php">🏠 Dashboard</a>
  <a href="/pages/kasse.php">💰 Kasse</a>
  <a href="/pages/shifts.php">⏱ Schichten</a>
  <a href="/pages/members.php">👥 Crew</a>
  <a href="/pages/events.php">🎉 Events</a>
  <a href="/pages/settings.php">⚙ Einstellungen</a>
  <a href="/logout.php">🚪 Logout</a>
</aside>
<div style="flex:1;display:flex;flex-direction:column;">
<header>
  <div><b>PushingP Crew</b></div>
  <div>💶 <?=number_format($saldo,2,',','.')?> €</div>
</header>
<main>

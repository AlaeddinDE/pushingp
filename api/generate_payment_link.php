<?php
include '../includes/db.php';
$user = $_GET['user'] ?? 'unbekannt';
$amount = $_GET['amount'] ?? 10.00;
$link = "https://paypoint.example.com/pay?user=".urlencode($user)."&amount=".$amount;
echo json_encode(["link"=>$link]);
?>

<?php
session_start(); header('Content-Type: application/json');
if(!isset($_SESSION['mitglied_id']) || $_SESSION['role']!=='admin'){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
include '../includes/db.php';
$typ = $_POST['typ'] ?? 'EINZAHLUNG';
$betrag = floatval($_POST['betrag'] ?? 0);
$mid = intval($_POST['mitglied_id'] ?? 0);
$desc = trim($_POST['beschreibung'] ?? '');
if(!$betrag){ echo json_encode(['ok'=>false]); exit; }
$stmt = $conn->prepare("INSERT INTO transaktionen (typ,betrag,mitglied_id,beschreibung) VALUES (?,?,?,?)");
$stmt->bind_param('sdis',$typ,$betrag,$mid,$desc);
$ok=$stmt->execute(); $stmt->close();
echo json_encode(['ok'=>$ok]);

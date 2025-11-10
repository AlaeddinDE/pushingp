<?php
session_start(); 
header('Content-Type: application/json');

if(!isset($_SESSION['mitglied_id'])){ 
    http_response_code(401); 
    echo json_encode(['error'=>'unauthorized']); 
    exit; 
}

include '../includes/db.php';
require_once '../includes/xp_system.php';

$uid = $_SESSION['mitglied_id']; 
$eid = intval($_POST['event_id']??0);

if(!$eid){ 
    echo json_encode(['ok'=>false]); 
    exit; 
}

// Check if already joined
$check = $conn->prepare("SELECT status FROM event_participants WHERE event_id = ? AND mitglied_id = ?");
$check->bind_param('ii', $eid, $uid);
$check->execute();
$check->bind_result($old_status);
$already_joined = $check->fetch();
$check->close();

$stmt = $conn->prepare("INSERT INTO event_participants (event_id,mitglied_id,status) VALUES (?,?, 'coming') ON DUPLICATE KEY UPDATE status='coming'");
$stmt->bind_param('ii', $eid, $uid); 
$ok = $stmt->execute(); 
$stmt->close();

// Award XP if newly joined or changed from declined
if ($ok && (!$already_joined || $old_status !== 'coming')) {
    add_xp($uid, 'event_attended', 'Event beigetreten', $eid, 'events');
}

echo json_encode(['ok'=>$ok]);


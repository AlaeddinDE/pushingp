<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$eid = intval($_GET['event_id'] ?? 0);
if(!$eid){ echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT m.name, p.status FROM event_participants p JOIN users m ON m.id=p.mitglied_id WHERE p.event_id=? ORDER BY m.name");
$stmt->bind_param('i', $eid);
$stmt->execute();
$result = $stmt->get_result();
$out = [];
while($r = $result->fetch_assoc()) {
    $out[] = $r;
}
$stmt->close();
echo json_encode($out);
?>

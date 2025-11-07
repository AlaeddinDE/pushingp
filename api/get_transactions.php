<?php
include '../includes/db.php';
$page = max(0, intval($_GET['page'] ?? 0));
$limit = 10;
$offset = $page * $limit;
$res = $conn->query("
  SELECT typ,betrag,DATE_FORMAT(datum,'%Y-%m-%d %H:%i') AS datum,beschreibung
  FROM transaktionen
  WHERE status='gebucht'
  ORDER BY datum DESC
  LIMIT $limit OFFSET $offset
");
$data = [];
while($r = $res->fetch_assoc()) $data[] = $r;
echo json_encode($data);
?>

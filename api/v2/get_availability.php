<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$date = isset($_GET['date']) ? trim((string) $_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    api_send_response('error', null, 'Ungültiges Datum', 422);
}

$prevDate = (new DateTime($date))->modify('-1 day')->format('Y-m-d');

// Mitgliederliste
$stmt = $conn->prepare('SELECT id, name FROM members_v2 WHERE status="active" AND is_locked=0 ORDER BY name ASC');
if (!$stmt) {
    api_send_response('error', null, 'Datenbankfehler', 500);
}
if (!$stmt->execute()) {
    $stmt->close();
    api_send_response('error', null, 'Datenbankfehler', 500);
}
$stmt->bind_result($memberId, $name);
$members = [];
while ($stmt->fetch()) {
    $members[(int) $memberId] = [
        'id' => (int) $memberId,
        'name' => $name,
        'state' => 'free',
        'presence' => 'offline',
    ];
}
$stmt->close();

if (empty($members)) {
    api_send_response('success', ['availability' => []]);
}

$memberIds = array_keys($members);
$placeholders = implode(',', array_fill(0, count($memberIds), '?'));
$types = str_repeat('i', count($memberIds));

// Präsenzdaten
$stmt = $conn->prepare("SELECT member_id, status FROM discord_status_cache WHERE member_id IN ($placeholders)");
if ($stmt) {
    $stmt->bind_param($types, ...$memberIds);
    if ($stmt->execute()) {
        $stmt->bind_result($pid, $presence);
        while ($stmt->fetch()) {
            if (isset($members[(int) $pid])) {
                $members[(int) $pid]['presence'] = $presence;
            }
        }
    }
    $stmt->close();
}

// Kranktage
$stmt = $conn->prepare('SELECT member_id FROM sickdays WHERE start_date <= ? AND end_date >= ?');
if ($stmt) {
    $stmt->bind_param('ss', $date, $date);
    if ($stmt->execute()) {
        $stmt->bind_result($sid);
        while ($stmt->fetch()) {
            $sid = (int) $sid;
            if (isset($members[$sid])) {
                $members[$sid]['state'] = 'sick';
            }
        }
    }
    $stmt->close();
}

// Urlaub
$stmt = $conn->prepare('SELECT member_id FROM vacations WHERE start_date <= ? AND end_date >= ?');
if ($stmt) {
    $stmt->bind_param('ss', $date, $date);
    if ($stmt->execute()) {
        $stmt->bind_result($vid);
        while ($stmt->fetch()) {
            $vid = (int) $vid;
            if (isset($members[$vid]) && $members[$vid]['state'] !== 'sick') {
                $members[$vid]['state'] = 'vacation';
            }
        }
    }
    $stmt->close();
}

// Schichten am gleichen Tag
$stmt = $conn->prepare('SELECT member_id FROM shifts WHERE shift_date = ?');
if ($stmt) {
    $stmt->bind_param('s', $date);
    if ($stmt->execute()) {
        $stmt->bind_result($mid);
        while ($stmt->fetch()) {
            $mid = (int) $mid;
            if (isset($members[$mid]) && $members[$mid]['state'] === 'free') {
                $members[$mid]['state'] = 'shift';
            }
        }
    }
    $stmt->close();
}

// Nachtschicht vom Vortag
$stmt = $conn->prepare("SELECT member_id FROM shifts WHERE shift_date = ? AND type='night'");
if ($stmt) {
    $stmt->bind_param('s', $prevDate);
    if ($stmt->execute()) {
        $stmt->bind_result($nid);
        while ($stmt->fetch()) {
            $nid = (int) $nid;
            if (isset($members[$nid]) && $members[$nid]['state'] === 'free') {
                $members[$nid]['state'] = 'shift';
            }
        }
    }
    $stmt->close();
}

api_send_response('success', ['availability' => array_values($members)]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

$now = new DateTime();
$today = $now->format('Y-m-d');
$yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
$currentTime = $now->format('H:i:s');

function shift_active(string $shiftDate, string $start, string $end, DateTime $now): bool
{
    $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $shiftDate . ' ' . $start);
    if (!$startTime) {
        return false;
    }
    $endDate = $shiftDate;
    if ($end < $start) {
        $endDate = (new DateTime($shiftDate))->modify('+1 day')->format('Y-m-d');
    }
    $endTime = DateTime::createFromFormat('Y-m-d H:i:s', $endDate . ' ' . $end);
    if (!$endTime) {
        return false;
    }
    return $now >= $startTime && $now < $endTime;
}

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
        'state' => 'available',
        'presence' => 'offline',
    ];
}
$stmt->close();

if (empty($members)) {
    api_send_response('success', ['counters' => [], 'lists' => []]);
}

$memberIds = array_keys($members);
$placeholders = implode(',', array_fill(0, count($memberIds), '?'));
$types = str_repeat('i', count($memberIds));

$stmt = $conn->prepare("SELECT member_id, status FROM discord_status_cache WHERE member_id IN ($placeholders)");
if ($stmt) {
    $stmt->bind_param($types, ...$memberIds);
    if ($stmt->execute()) {
        $stmt->bind_result($pid, $presence);
        while ($stmt->fetch()) {
            $pid = (int) $pid;
            if (isset($members[$pid])) {
                $members[$pid]['presence'] = $presence;
            }
        }
    }
    $stmt->close();
}

$stmt = $conn->prepare('SELECT member_id FROM sickdays WHERE start_date <= ? AND end_date >= ?');
if ($stmt) {
    $stmt->bind_param('ss', $today, $today);
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

$stmt = $conn->prepare('SELECT member_id FROM vacations WHERE start_date <= ? AND end_date >= ?');
if ($stmt) {
    $stmt->bind_param('ss', $today, $today);
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

$stmt = $conn->prepare('SELECT member_id, type, start_time, end_time, shift_date FROM shifts WHERE shift_date IN (?, ?)');
if ($stmt) {
    $stmt->bind_param('ss', $today, $yesterday);
    if ($stmt->execute()) {
        $stmt->bind_result($mid, $type, $startTime, $endTime, $shiftDate);
        while ($stmt->fetch()) {
            $mid = (int) $mid;
            if (!isset($members[$mid])) {
                continue;
            }
            if ($members[$mid]['state'] === 'sick' || $members[$mid]['state'] === 'vacation') {
                continue;
            }
            if ($shiftDate === $today || $shiftDate === $yesterday) {
                if (shift_active($shiftDate, $startTime, $endTime, $now)) {
                    $members[$mid]['state'] = 'shift';
                }
            }
        }
    }
    $stmt->close();
}

$counters = [
    'shift' => 0,
    'available' => 0,
    'vacation' => 0,
    'sick' => 0,
    'online' => 0,
];
$lists = [
    'shift' => [],
    'available' => [],
    'vacation' => [],
    'sick' => [],
];

foreach ($members as $member) {
    $state = $member['state'];
    if (!isset($lists[$state])) {
        $lists[$state] = [];
    }
    $lists[$state][] = $member;
    $counters[$state]++;
    if (in_array($member['presence'], ['online', 'busy'], true)) {
        $counters['online']++;
    }
}

api_send_response('success', [
    'counters' => $counters,
    'lists' => $lists,
]);

<?php
require_once __DIR__ . '/../includes/db.php';

// Get user token from URL parameter
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    exit('Invalid token');
}

// Find user by calendar token
$stmt = $conn->prepare("SELECT id, username, name FROM users WHERE calendar_token = ? AND calendar_sync = 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$stmt->bind_result($user_id, $username, $name);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(404);
    exit('Calendar not found');
}
$stmt->close();

// Get shifts for this user (next 365 days)
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+365 days'));

$stmt = $conn->prepare("SELECT shift_date, shift_type FROM shifts WHERE user_id = ? AND shift_date BETWEEN ? AND ? ORDER BY shift_date");
$stmt->bind_param('iss', $user_id, $start_date, $end_date);
$stmt->execute();
$stmt->bind_result($shift_date, $shift_type);

$shifts = [];
while ($stmt->fetch()) {
    $shifts[] = ['date' => $shift_date, 'type' => $shift_type];
}
$stmt->close();

// Generate iCal format
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="schichten_' . $username . '.ics"');

$ical = "BEGIN:VCALENDAR\r\n";
$ical .= "VERSION:2.0\r\n";
$ical .= "PRODID:-//Pushing P//Schichtplan//DE\r\n";
$ical .= "CALSCALE:GREGORIAN\r\n";
$ical .= "METHOD:PUBLISH\r\n";
$ical .= "X-WR-CALNAME:Pushing P Schichten - " . $name . "\r\n";
$ical .= "X-WR-TIMEZONE:Europe/Berlin\r\n";

foreach ($shifts as $shift) {
    $shift_type = $shift['type'];
    $shift_date = $shift['date'];
    
    // Define shift times
    $shift_info = [
        'Frühschicht' => ['start' => '06:00', 'end' => '14:00', 'color' => '#4ade80'],
        'Spätschicht' => ['start' => '14:00', 'end' => '22:00', 'color' => '#fb923c'],
        'Nachtschicht' => ['start' => '22:00', 'end' => '06:00', 'color' => '#a78bfa'],
        'Frei' => ['start' => '00:00', 'end' => '23:59', 'color' => '#94a3b8'],
        'Urlaub' => ['start' => '00:00', 'end' => '23:59', 'color' => '#60a5fa']
    ];
    
    if (!isset($shift_info[$shift_type])) continue;
    
    $info = $shift_info[$shift_type];
    
    // Create datetime stamps
    $start_datetime = date('Ymd\THis', strtotime($shift_date . ' ' . $info['start']));
    if ($shift_type === 'Nachtschicht') {
        // Night shift ends next day
        $end_datetime = date('Ymd\THis', strtotime($shift_date . ' +1 day ' . $info['end']));
    } else {
        $end_datetime = date('Ymd\THis', strtotime($shift_date . ' ' . $info['end']));
    }
    
    $uid = md5($user_id . $shift_date . $shift_type) . '@pushingp.de';
    $created = date('Ymd\THis\Z');
    
    $ical .= "BEGIN:VEVENT\r\n";
    $ical .= "UID:" . $uid . "\r\n";
    $ical .= "DTSTAMP:" . $created . "\r\n";
    $ical .= "DTSTART;TZID=Europe/Berlin:" . $start_datetime . "\r\n";
    $ical .= "DTEND;TZID=Europe/Berlin:" . $end_datetime . "\r\n";
    $ical .= "SUMMARY:" . $shift_type . "\r\n";
    $ical .= "DESCRIPTION:Pushing P Schichtplan - " . $shift_type . "\r\n";
    $ical .= "STATUS:CONFIRMED\r\n";
    $ical .= "SEQUENCE:0\r\n";
    $ical .= "END:VEVENT\r\n";
}

$ical .= "END:VCALENDAR\r\n";

echo $ical;
?>

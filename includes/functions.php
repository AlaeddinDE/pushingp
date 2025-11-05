<?php

function json_response($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function fetch_member_by_name(mysqli $mysqli, string $name): ?array {
    $stmt = $mysqli->prepare('SELECT id, name, flag, is_locked FROM members WHERE name = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $name);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $stmt->bind_result($id, $memberName, $flag, $isLocked);
    if (!$stmt->fetch()) {
        $stmt->close();
        return null;
    }

    $stmt->close();

    return [
        'id' => (int) $id,
        'name' => $memberName,
        'flag' => $flag,
        'is_locked' => (int) $isLocked === 1,
    ];
}

?>

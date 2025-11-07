<?php
/**
 * Security & Helper Functions
 * Provides CSRF protection, session management, role checking, and sanitization
 */

// Start session securely
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
    }
}

// Generate CSRF token
function generate_csrf_token() {
    require_once __DIR__ . '/db.php';
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $token = bin2hex(random_bytes(32));
    $user_id = $_SESSION['user_id'];
    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Clean old tokens
    $stmt = $conn->prepare("DELETE FROM csrf_tokens WHERE user_id = ? OR expires_at < NOW()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Insert new token
    $stmt = $conn->prepare("INSERT INTO csrf_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token, $expires_at);
    $stmt->execute();
    $stmt->close();
    
    return $token;
}

// Verify CSRF token
function verify_csrf_token($token) {
    require_once __DIR__ . '/db.php';
    global $conn;
    
    if (!isset($_SESSION['user_id']) || empty($token)) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM csrf_tokens WHERE user_id = ? AND token = ? AND expires_at > NOW()");
    $stmt->bind_param("is", $user_id, $token);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    return $count > 0;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has role
function has_role($role) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (isset($_SESSION['roles'])) {
        $roles = is_array($_SESSION['roles']) ? $_SESSION['roles'] : json_decode($_SESSION['roles'], true);
        return in_array($role, $roles);
    }
    
    if (isset($_SESSION['role'])) {
        return $_SESSION['role'] === $role || $_SESSION['role'] === 'admin';
    }
    
    return false;
}

// Check if user is admin
function is_admin() {
    return has_role('admin') || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

// Require login
function require_login() {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'error' => 'Nicht angemeldet']);
        exit;
    }
}

// Require admin
function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Keine Berechtigung']);
        exit;
    }
}

// Sanitize output
function escape($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// JSON response helper
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Get current user ID
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Log admin action
function log_admin_action($action, $entity_type, $entity_id = null, $details = null) {
    require_once __DIR__ . '/db.php';
    global $conn;
    
    if (!is_admin()) {
        return false;
    }
    
    $admin_id = get_current_user_id();
    $payload_hash = $details ? hash('sha256', json_encode($details)) : null;
    $details_json = $details ? json_encode($details) : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, entity_type, entity_id, payload_hash, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssisss", $admin_id, $action, $entity_type, $entity_id, $payload_hash, $details_json, $ip, $ua);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

// Rate limiting (simple IP-based)
function check_rate_limit($action, $max_attempts = 10, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "ratelimit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    if (time() > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    $_SESSION[$key]['count']++;
    
    if ($_SESSION[$key]['count'] > $max_attempts) {
        http_response_code(429);
        json_response(['status' => 'error', 'error' => 'Zu viele Anfragen. Bitte warten.'], 429);
    }
    
    return true;
}

// Validate date format
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Calculate monthly fee based on active months
function calculate_monthly_fee($user_id, $until_month = null) {
    require_once __DIR__ . '/db.php';
    global $conn;
    
    if ($until_month === null) {
        $until_month = date('Y-m');
    }
    
    // Get user's active dates
    $stmt = $conn->prepare("SELECT aktiv_ab, inaktiv_ab FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($aktiv_ab, $inaktiv_ab);
    $stmt->fetch();
    $stmt->close();
    
    if (!$aktiv_ab) {
        return 0;
    }
    
    // Get monthly fee from system settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'monthly_fee'");
    $stmt->execute();
    $stmt->bind_result($monthly_fee);
    $stmt->fetch();
    $stmt->close();
    
    $monthly_fee = floatval($monthly_fee ?? 10.00);
    
    // Calculate months between aktiv_ab and until_month
    $start = new DateTime($aktiv_ab);
    $end = new DateTime($until_month . '-01');
    
    if ($inaktiv_ab) {
        $inactive = new DateTime($inaktiv_ab);
        if ($inactive < $end) {
            $end = $inactive;
        }
    }
    
    $interval = $start->diff($end);
    $months = ($interval->y * 12) + $interval->m;
    
    // Include current month if we're past the start
    if ($start->format('Y-m') <= $end->format('Y-m')) {
        $months++;
    }
    
    return $months * $monthly_fee;
}

// Get member payment status
function get_payment_status($user_id) {
    require_once __DIR__ . '/db.php';
    global $conn;
    
    // Calculate what they should have paid
    $soll = calculate_monthly_fee($user_id);
    
    // Calculate what they actually paid
    $stmt = $conn->prepare("SELECT COALESCE(SUM(betrag), 0) FROM transactions WHERE mitglied_id = ? AND typ = 'EINZAHLUNG' AND status = 'gebucht'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($ist);
    $stmt->fetch();
    $stmt->close();
    
    $difference = $soll - $ist;
    
    // Get due settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'overdue_grace_days'");
    $stmt->execute();
    $stmt->bind_result($grace_days);
    $stmt->fetch();
    $stmt->close();
    
    $grace_days = intval($grace_days ?? 7);
    
    // Determine status
    if ($difference <= 0) {
        return ['status' => 'paid', 'difference' => 0];
    }
    
    // Check if overdue
    $current_month = date('Y-m');
    $due_day = 15; // From system settings
    $due_date = new DateTime($current_month . '-' . str_pad($due_day, 2, '0', STR_PAD_LEFT));
    $overdue_date = clone $due_date;
    $overdue_date->modify("+{$grace_days} days");
    
    $now = new DateTime();
    
    if ($now > $overdue_date) {
        return ['status' => 'overdue', 'difference' => $difference];
    } else if ($now > $due_date) {
        return ['status' => 'open', 'difference' => $difference];
    } else {
        return ['status' => 'open', 'difference' => $difference];
    }
}

<?php
/**
 * XP System Core Functions
 * Handles all XP calculations, level ups, badges
 */

require_once __DIR__ . '/../includes/db.php';

/**
 * Add XP to a user
 * @param int $user_id
 * @param string $action_code
 * @param string $description (optional custom description)
 * @param int $source_id (optional reference ID)
 * @param string $source_table (optional source table)
 * @param int $custom_xp (optional custom XP amount, overrides DB value)
 * @return array ['success' => bool, 'xp_gained' => int, 'level_up' => bool, 'new_level' => int]
 */
function add_xp($user_id, $action_code, $description = null, $source_id = null, $source_table = null, $custom_xp = null) {
    global $conn;
    
    // Get action XP value
    $stmt = $conn->prepare("SELECT xp_value, action_name FROM xp_actions WHERE action_code = ? AND is_active = 1");
    $stmt->bind_param('s', $action_code);
    $stmt->execute();
    $stmt->bind_result($xp_value, $action_name);
    
    if (!$stmt->fetch()) {
        $stmt->close();
        return ['success' => false, 'error' => 'Unknown action code'];
    }
    $stmt->close();
    
    // Use custom XP if provided
    if ($custom_xp !== null) {
        $xp_value = $custom_xp;
    }
    
    // Get user current XP and multiplier
    $stmt = $conn->prepare("SELECT xp_total, level_id, xp_multiplier FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($current_xp, $current_level, $multiplier);
    
    if (!$stmt->fetch()) {
        $stmt->close();
        return ['success' => false, 'error' => 'User not found'];
    }
    $stmt->close();
    
    // Calculate XP with multiplier
    $xp_to_add = round($xp_value * $multiplier);
    $new_xp = max(0, $current_xp + $xp_to_add); // XP can't go below 0
    
    // Update user XP
    $stmt = $conn->prepare("UPDATE users SET xp_total = ?, last_xp_update = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $new_xp, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Log XP change
    $desc = $description ?? $action_name;
    $stmt = $conn->prepare("INSERT INTO xp_history (user_id, action_code, description, xp_change, xp_before, xp_after, source_table, source_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issiiisi', $user_id, $action_code, $desc, $xp_to_add, $current_xp, $new_xp, $source_table, $source_id);
    $stmt->execute();
    $stmt->close();
    
    // Check for level up
    $new_level = calculate_level($new_xp);
    $leveled_up = $new_level > $current_level;
    
    if ($leveled_up) {
        $stmt = $conn->prepare("UPDATE users SET level_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $new_level, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // CHAT INTEGRATION: Announce Level Up
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($user_name);
        $stmt->fetch();
        $stmt->close();
        
        // Get level title
        $level_title = "Level $new_level";
        $res = $conn->query("SELECT title FROM level_config WHERE level_id = $new_level");
        if ($res && $row = $res->fetch_assoc()) {
            $level_title .= " (" . $row['title'] . ")";
        }
        
        $msg = "🎉 **$user_name** ist aufgestiegen zu **$level_title**! 🚀";
        
        // Post to all group chats where user is member
        $groups = $conn->query("SELECT group_id FROM chat_group_members WHERE user_id = $user_id");
        if ($groups) {
            $stmt_chat = $conn->prepare("INSERT INTO chat_messages (sender_id, group_id, message, created_at) VALUES (?, ?, ?, NOW())");
            while ($row = $groups->fetch_assoc()) {
                $gid = $row['group_id'];
                $stmt_chat->bind_param('iis', $user_id, $gid, $msg);
                $stmt_chat->execute();
            }
            $stmt_chat->close();
        }
    }
    
    // Check for new badges
    check_and_award_badges($user_id);
    
    return [
        'success' => true,
        'xp_gained' => $xp_to_add,
        'xp_total' => $new_xp,
        'level_up' => $leveled_up,
        'old_level' => $current_level,
        'new_level' => $new_level
    ];
}

/**
 * Calculate level from XP
 */
function calculate_level($xp) {
    global $conn;
    
    $result = $conn->query("SELECT level_id FROM level_config WHERE xp_required <= $xp ORDER BY xp_required DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['level_id'];
    }
    return 1;
}

/**
 * Get user level info
 */
function get_user_level_info($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM v_user_xp_progress WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    
    // Bind all result columns
    $meta = $stmt->result_metadata();
    $fields = [];
    $row = [];
    
    while ($field = $meta->fetch_field()) {
        $fields[] = &$row[$field->name];
    }
    
    call_user_func_array([$stmt, 'bind_result'], $fields);
    
    if ($stmt->fetch()) {
        $result = [];
        foreach ($row as $key => $val) {
            $result[$key] = $val;
        }
        $stmt->close();
        return $result;
    }
    
    $stmt->close();
    return null;
}

/**
 * Check and award badges to user
 */
function check_and_award_badges($user_id) {
    global $conn;
    
    // Get all badges user doesn't have yet
    $result = $conn->query("
        SELECT b.* FROM badges b
        WHERE b.id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = $user_id)
    ");
    
    while ($badge = $result->fetch_assoc()) {
        $earned = false;
        
        switch ($badge['requirement_type']) {
            case 'membership_days':
                $stmt = $conn->prepare("SELECT DATEDIFF(NOW(), created_at) as days FROM users WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->bind_result($days);
                if ($stmt->fetch() && $days >= $badge['requirement_value']) {
                    $earned = true;
                }
                $stmt->close();
                break;
                
            case 'events_attended':
                $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM event_participants WHERE mitglied_id = ? AND status = 'coming'");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->bind_result($count);
                if ($stmt->fetch() && $count >= $badge['requirement_value']) {
                    $earned = true;
                }
                $stmt->close();
                break;
                
            case 'events_created':
                $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM events WHERE created_by = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->bind_result($count);
                if ($stmt->fetch() && $count >= $badge['requirement_value']) {
                    $earned = true;
                }
                $stmt->close();
                break;
                
            case 'login_streak':
                $stmt = $conn->prepare("SELECT login_streak FROM user_streaks WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->bind_result($streak);
                if ($stmt->fetch() && $streak >= $badge['requirement_value']) {
                    $earned = true;
                }
                $stmt->close();
                break;
        }
        
        if ($earned) {
            award_badge($user_id, $badge['id']);
        }
    }
}

/**
 * Award a badge to user
 */
function award_badge($user_id, $badge_id) {
    global $conn;
    
    // Award badge
    $stmt = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $user_id, $badge_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        // Get badge XP reward
        $stmt = $conn->prepare("SELECT xp_reward, title FROM badges WHERE id = ?");
        $stmt->bind_param('i', $badge_id);
        $stmt->execute();
        $stmt->bind_result($xp_reward, $title);
        $stmt->fetch();
        $stmt->close();
        
        // Add XP for badge
        if ($xp_reward > 0) {
            $stmt = $conn->prepare("SELECT xp_total FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->bind_result($current_xp);
            $stmt->fetch();
            $stmt->close();
            
            $new_xp = $current_xp + $xp_reward;
            
            $stmt = $conn->prepare("UPDATE users SET xp_total = ? WHERE id = ?");
            $stmt->bind_param('ii', $new_xp, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Log badge XP
            $stmt = $conn->prepare("INSERT INTO xp_history (user_id, action_code, description, xp_change, xp_before, xp_after, source_table, source_id) VALUES (?, 'badge_earned', ?, ?, ?, ?, 'badges', ?)");
            $desc = "Badge: $title";
            $stmt->bind_param('isiiii', $user_id, $desc, $xp_reward, $current_xp, $new_xp, $badge_id);
            $stmt->execute();
            $stmt->close();
        }
        
        return true;
    }
    
    return false;
}

/**
 * Update login streak and award XP
 */
function update_login_streak($user_id) {
    global $conn;
    
    $today = date('Y-m-d');
    
    // Get or create streak record
    $stmt = $conn->prepare("SELECT login_streak, last_login_date FROM user_streaks WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($current_streak, $last_login);
    
    if (!$stmt->fetch()) {
        // Create new streak record
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO user_streaks (user_id, login_streak, last_login_date) VALUES (?, 1, ?)");
        $stmt->bind_param('is', $user_id, $today);
        $stmt->execute();
        $stmt->close();
        
        // Award daily login XP
        add_xp($user_id, 'daily_login', 'Täglicher Login');
        return;
    }
    $stmt->close();
    
    // Check if already logged in today
    if ($last_login === $today) {
        return; // Already got XP today
    }
    
    // Check if streak continues (yesterday)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $new_streak = ($last_login === $yesterday) ? $current_streak + 1 : 1;
    
    // Update streak
    $stmt = $conn->prepare("UPDATE user_streaks SET login_streak = ?, last_login_date = ? WHERE user_id = ?");
    $stmt->bind_param('isi', $new_streak, $today, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Award daily login XP
    add_xp($user_id, 'daily_login', 'Täglicher Login');
    
    // Check for streak milestones
    if ($new_streak == 7) {
        add_xp($user_id, 'login_streak_7', '7-Tage Login-Streak');
    } elseif ($new_streak == 30) {
        add_xp($user_id, 'login_streak_30', '30-Tage Login-Streak');
    }
}

/**
 * Check for inactivity penalty
 */
function check_inactivity_penalty() {
    global $conn;
    
    $threshold_date = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    $result = $conn->query("
        SELECT id, username, last_login, xp_total 
        FROM users 
        WHERE status = 'active' 
        AND last_login < '$threshold_date'
        AND last_login IS NOT NULL
    ");
    
    while ($user = $result->fetch_assoc()) {
        $days_inactive = floor((time() - strtotime($user['last_login'])) / 86400) - 30;
        
        if ($days_inactive > 0) {
            $penalty = $days_inactive * 10;
            add_xp($user['id'], 'inactive_penalty', "Inaktiv seit $days_inactive Tagen");
        }
    }
}

/**
 * Get leaderboard
 */
function get_leaderboard($limit = 10) {
    global $conn;
    
    $result = $conn->query("SELECT * FROM v_xp_leaderboard LIMIT $limit");
    $leaderboard = [];
    
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = $row;
    }
    
    return $leaderboard;
}

/**
 * Get user badges
 */
function get_user_badges($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT b.*, ub.earned_at 
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $badges = [];
    while ($row = $result->fetch_assoc()) {
        $badges[] = $row;
    }
    $stmt->close();
    
    return $badges;
}

/**
 * Get XP history for user
 */
function get_xp_history($user_id, $limit = 50) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM xp_history 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT ?
    ");
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    return $history;
}

/**
 * Track user login and reward XP
 * Called on every successful login
 */
function track_login_xp($user_id) {
    global $conn;
    
    // Get user login data
    $stmt = $conn->prepare("SELECT last_login, last_login_reward, login_streak, longest_login_streak FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($last_login, $last_login_reward, $login_streak, $longest_streak);
    
    if (!$stmt->fetch()) {
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    
    // Check if already rewarded today
    if ($last_login_reward) {
        $last_reward_date = (new DateTime($last_login_reward))->format('Y-m-d');
        if ($last_reward_date === $today) {
            // Already rewarded today, just update last_login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            return;
        }
    }
    
    // Calculate streak
    $new_streak = 1;
    if ($last_login_reward) {
        $last_reward = new DateTime($last_login_reward);
        $diff_days = $now->diff($last_reward)->days;
        
        if ($diff_days == 1) {
            // Consecutive day login
            $new_streak = $login_streak + 1;
        } else if ($diff_days > 1) {
            // Streak broken
            $new_streak = 1;
        }
    }
    
    $new_longest = max($longest_streak, $new_streak);
    
    // Update login data
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), last_login_reward = NOW(), login_streak = ?, longest_login_streak = ? WHERE id = ?");
    $stmt->bind_param('iii', $new_streak, $new_longest, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Award daily login XP
    add_xp($user_id, 'LOGIN_DAILY', 'Täglicher Login');
    
    // Check for streak bonuses
    if ($new_streak == 7) {
        add_xp($user_id, 'LOGIN_STREAK_7', '7-Tage Login Streak');
    } else if ($new_streak == 30) {
        add_xp($user_id, 'LOGIN_STREAK_30', '30-Tage Login Streak');
    }
}

/**
 * Award XP for event participation
 */
function award_event_participation_xp($user_id, $event_id) {
    global $conn;
    
    // Check if already awarded
    $stmt = $conn->prepare("SELECT COUNT(*) FROM xp_history WHERE user_id = ? AND action_code = 'EVENT_ATTEND' AND source_id = ?");
    $stmt->bind_param('ii', $user_id, $event_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    if ($count > 0) return; // Already awarded
    
    add_xp($user_id, 'EVENT_ATTEND', 'Event Teilnahme', $event_id, 'events');
    
    // Check for event streak
    check_event_streak($user_id);
}

/**
 * Award XP for organizing an event
 */
function award_event_organizer_xp($user_id, $event_id) {
    add_xp($user_id, 'EVENT_ORGANIZE', 'Event organisiert', $event_id, 'events');
}

/**
 * Award XP when event completes successfully
 */
function award_event_completion_xp($event_id) {
    global $conn;
    
    // Get event details
    $stmt = $conn->prepare("SELECT erstellt_von FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $stmt->bind_result($organizer_id);
    
    if (!$stmt->fetch()) {
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Award organizer bonus
    add_xp($organizer_id, 'EVENT_COMPLETE', 'Event erfolgreich abgeschlossen', $event_id, 'events');
    
    // Count participants
    $stmt = $conn->prepare("SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND status = 'coming'");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $stmt->bind_result($participant_count);
    $stmt->fetch();
    $stmt->close();
    
    // Bonus for large events
    if ($participant_count >= 10) {
        add_xp($organizer_id, 'EVENT_LARGE', 'Event mit 10+ Teilnehmern', $event_id, 'events');
    }
}

/**
 * Check and award event streak
 */
function check_event_streak($user_id) {
    global $conn;
    
    // Get last 5 events this user participated in
    $stmt = $conn->prepare("
        SELECT COUNT(*) as streak
        FROM (
            SELECT e.id, e.datum
            FROM events e
            JOIN event_participants ep ON e.id = ep.event_id
            WHERE ep.mitglied_id = ? AND ep.status = 'coming'
            ORDER BY e.datum DESC
            LIMIT 5
        ) as recent_events
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($streak);
    $stmt->fetch();
    $stmt->close();
    
    if ($streak == 5) {
        // Check if already awarded
        $stmt = $conn->prepare("SELECT COUNT(*) FROM xp_history WHERE user_id = ? AND action_code = 'EVENT_STREAK_5' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($awarded);
        $stmt->fetch();
        $stmt->close();
        
        if ($awarded == 0) {
            add_xp($user_id, 'EVENT_STREAK_5', '5 Events hintereinander besucht');
        }
    }
}

/**
 * Award XP for payment actions
 */
function award_payment_xp($user_id, $amount, $type = 'monthly') {
    global $conn;
    
    if ($type === 'monthly' || $type === 'Monatsbeitrag') {
        // Check if payment is on time
        $stmt = $conn->prepare("SELECT monatsbeitrag FROM settings LIMIT 1");
        $stmt->execute();
        $stmt->bind_result($monthly_fee);
        $stmt->fetch();
        $stmt->close();
        
        if ($amount >= $monthly_fee) {
            add_xp($user_id, 'PAYMENT_ONTIME', 'Pünktliche Monatszahlung');
        }
    }
    
    // Bonus for extra payments
    if ($type === 'Einzahlung' && $amount >= 10) {
        $bonus_xp_count = floor($amount / 10);
        for ($i = 0; $i < min($bonus_xp_count, 10); $i++) {
            add_xp($user_id, 'PAYMENT_EXTRA', 'Extra-Zahlung (10€)');
        }
    }
    
    // Big deposit bonus
    if ($amount >= 100) {
        add_xp($user_id, 'PAYMENT_BIG', 'Große Einzahlung (100€+)');
    }
}

/**
 * Award XP for balance actions
 */
function award_balance_xp($user_id) {
    global $conn;
    
    // Get user balance
    $stmt = $conn->prepare("SELECT SUM(betrag) as balance FROM transaktionen WHERE mitglied_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();
    
    // Award for positive balance
    if ($balance >= 0) {
        add_xp($user_id, 'BALANCE_POSITIVE', 'Ausgeglichene Kasse');
    }
    
    // Award for high balance
    if ($balance >= 100) {
        add_xp($user_id, 'BALANCE_HIGH', 'Kassenstand über 100€');
    }
}

/**
 * Check and award profile completion XP
 */
function check_profile_completion_xp($user_id) {
    global $conn;
    
    // Get user profile
    $stmt = $conn->prepare("SELECT avatar, bio, phone, profile_completed FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($avatar, $bio, $phone, $already_completed);
    
    if (!$stmt->fetch()) {
        $stmt->close();
        return;
    }
    $stmt->close();
    
    if ($already_completed) return; // Already awarded
    
    // Check if profile is complete
    $is_complete = !empty($avatar) && !empty($bio) && !empty($phone);
    
    if ($is_complete) {
        $stmt = $conn->prepare("UPDATE users SET profile_completed = TRUE WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        
        add_xp($user_id, 'PROFILE_COMPLETE', 'Vollständiges Profil erstellt');
    }
}

/**
 * Award XP for referring a new member
 */
function award_referral_xp($referrer_id, $new_user_id) {
    add_xp($referrer_id, 'REFERRAL', 'Neues Mitglied geworben', $new_user_id, 'users');
}

/**
 * Check inactivity and apply penalties (run via cron)
 */
function check_inactivity_penalties() {
    global $conn;
    
    // Find users inactive for 30+ days
    $result = $conn->query("
        SELECT id, DATEDIFF(NOW(), last_login) as days_inactive
        FROM users
        WHERE status = 'active'
        AND last_login IS NOT NULL
        AND last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $days_inactive = $row['days_inactive'];
        
        // Apply penalty: -10 XP per day after 30 days
        $penalty_days = $days_inactive - 30;
        
        if ($penalty_days > 0) {
            add_xp($user_id, 'INACTIVITY_PENALTY', "Inaktivitätsstrafe ($penalty_days Tage)");
        }
    }
}

/**
 * Check and award milestone badges
 */
function check_milestone_badges($user_id) {
    global $conn;
    
    // 1 Year member
    $stmt = $conn->prepare("SELECT DATEDIFF(NOW(), created_at) as days FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($days);
    $stmt->fetch();
    $stmt->close();
    
    if ($days >= 365) {
        award_badge_if_not_exists($user_id, 'MEMBER_1YEAR');
    }
    
    // Event milestones
    $stmt = $conn->prepare("SELECT COUNT(*) FROM event_participants WHERE mitglied_id = ? AND status = 'coming'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($event_count);
    $stmt->fetch();
    $stmt->close();
    
    if ($event_count >= 25) award_badge_if_not_exists($user_id, 'EVENTS_25');
    if ($event_count >= 50) award_badge_if_not_exists($user_id, 'EVENTS_50');
    if ($event_count >= 100) award_badge_if_not_exists($user_id, 'EVENTS_100');
}

/**
 * Award badge if user doesn't have it
 */
function award_badge_if_not_exists($user_id, $badge_code) {
    global $conn;
    
    // Check if user already has badge
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id = ? AND badge_code = ?");
    $stmt->bind_param('is', $user_id, $badge_code);
    $stmt->execute();
    $stmt->bind_result($has_badge);
    $stmt->fetch();
    $stmt->close();
    
    if ($has_badge > 0) return;
    
    // Award badge
    $stmt = $conn->prepare("INSERT INTO user_badges (user_id, badge_code, awarded_at) VALUES (?, ?, NOW())");
    $stmt->bind_param('is', $user_id, $badge_code);
    $stmt->execute();
    $stmt->close();
    
    // Get badge XP value
    $stmt = $conn->prepare("SELECT xp_reward FROM badge_config WHERE badge_code = ?");
    $stmt->bind_param('s', $badge_code);
    $stmt->execute();
    $stmt->bind_result($xp_reward);
    
    if ($stmt->fetch() && $xp_reward > 0) {
        $stmt->close();
        add_xp($user_id, 'BADGE_EARNED', "Badge verdient: $badge_code");
    } else {
        $stmt->close();
    }
}

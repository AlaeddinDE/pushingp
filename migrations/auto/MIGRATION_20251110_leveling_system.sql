-- MIGRATION 2025-11-10: Complete Leveling System
-- Implements XP, Levels, Badges, and Activity Tracking

-- Level Configuration Table
CREATE TABLE IF NOT EXISTS level_config (
    level_id INT PRIMARY KEY,
    xp_required INT NOT NULL,
    title VARCHAR(50) NOT NULL,
    unlock_text TEXT,
    emoji VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Level Definitions
INSERT INTO level_config (level_id, xp_required, title, emoji, unlock_text) VALUES
(1, 0, 'Rookie', '🌱', 'Willkommen bei Pushing P!'),
(2, 250, 'Staff', '👤', 'Du bist jetzt Teil des Teams'),
(3, 750, 'Member', '🔥', 'Aktives Crewmitglied'),
(4, 1500, 'Crew', '💪', 'Du gehörst zur festen Crew'),
(5, 3000, 'Trusted', '⭐', 'Vertrauenswürdiges Mitglied'),
(6, 5000, 'Inner Circle', '💎', 'Du bist im inneren Kreis'),
(7, 8000, 'Elite', '👑', 'Elite-Status erreicht'),
(8, 12000, 'Ehrenmember', '🏆', 'Ehrenmitglied der Crew'),
(9, 18000, 'Pushing Veteran', '🔱', 'Veteran von Pushing P'),
(10, 25000, 'Pushing Legend', '⚡', 'Legende der Community'),
(11, 40000, 'Unantastbar', '🌟', 'Unerreichter Status');

-- Add XP and Level fields to users table
ALTER TABLE users 
ADD COLUMN xp_total INT DEFAULT 0,
ADD COLUMN level_id INT DEFAULT 1,
ADD COLUMN xp_multiplier DECIMAL(3,2) DEFAULT 1.00,
ADD COLUMN badges_json JSON,
ADD COLUMN last_xp_update TIMESTAMP NULL;

-- XP History Table (tracks all XP gains/losses)
CREATE TABLE IF NOT EXISTS xp_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_code VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    xp_change INT NOT NULL,
    xp_before INT NOT NULL,
    xp_after INT NOT NULL,
    source_table VARCHAR(50),
    source_id INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action_code),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Badges Table
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    emoji VARCHAR(10),
    xp_reward INT DEFAULT 0,
    requirement_type VARCHAR(50),
    requirement_value INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Badge Definitions
INSERT INTO badges (code, title, description, emoji, xp_reward, requirement_type, requirement_value) VALUES
('member_1year', '1 Jahr Crew', '1 Jahr aktives Mitglied', '🎂', 500, 'membership_days', 365),
('member_2years', '2 Jahre Crew', '2 Jahre treue Mitgliedschaft', '🎉', 2000, 'membership_days', 730),
('event_starter', 'Event Starter', '5 Events besucht', '🎫', 100, 'events_attended', 5),
('event_enthusiast', 'Event Enthusiast', '25 Events besucht', '🎪', 500, 'events_attended', 25),
('event_legend', 'Event Legend', '100 Events besucht', '🌟', 2000, 'events_attended', 100),
('event_creator', 'Event Creator', '5 Events organisiert', '🎬', 300, 'events_created', 5),
('recruiter', 'Recruiter', '3 Mitglieder geworben', '👥', 1000, 'members_recruited', 3),
('financial_hero', 'Financial Hero', '6 Monate ohne Rückstand', '💰', 800, 'months_no_debt', 6),
('generous_donor', 'Großzügiger Spender', '500€ Extra eingezahlt', '💸', 1500, 'extra_donations', 500),
('daily_warrior', 'Daily Warrior', '30 Tage Login-Streak', '🔥', 400, 'login_streak', 30),
('no_damage', 'Schadensfrei', '1 Jahr ohne Schaden', '✨', 600, 'months_no_damage', 12);

-- User Badges Junction Table
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Streaks Table (tracks user streaks)
CREATE TABLE IF NOT EXISTS user_streaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    login_streak INT DEFAULT 0,
    last_login_date DATE,
    event_streak INT DEFAULT 0,
    last_event_date DATE,
    payment_streak INT DEFAULT 0,
    last_payment_date DATE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- XP Actions Configuration (for easy adjustment)
CREATE TABLE IF NOT EXISTS xp_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_code VARCHAR(50) UNIQUE NOT NULL,
    action_name VARCHAR(100) NOT NULL,
    xp_value INT NOT NULL,
    category VARCHAR(50),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert XP Action Definitions
INSERT INTO xp_actions (action_code, action_name, xp_value, category, description) VALUES
-- Events
('event_attended', 'Event Teilnahme', 20, 'events', 'Teilnahme an einem Event'),
('event_created', 'Event erstellt', 80, 'events', 'Ein Event organisiert'),
('event_completed', 'Event erfolgreich', 30, 'events', 'Event erfolgreich abgeschlossen'),
('event_large', 'Großes Event', 25, 'events', 'Event mit ≥10 Teilnehmern'),
('event_streak_5', 'Event Streak (5)', 150, 'events', '5 Events in Folge besucht'),

-- Finances
('payment_ontime', 'Pünktliche Zahlung', 30, 'finance', 'Monatsbeitrag pünktlich gezahlt'),
('extra_payment_10', 'Extra-Zahlung (10€)', 100, 'finance', 'Freiwillige Einzahlung von 10€'),
('large_deposit', 'Große Einzahlung (100€)', 1000, 'finance', '100€ auf Kassenkonto eingezahlt'),
('no_debt_3months', 'Keine Schulden (3M)', 100, 'finance', '3 Monate ohne Rückstand'),
('no_damage_6months', 'Schadensfrei (6M)', 300, 'finance', '6 Monate ohne Schaden'),
('balance_positive', 'Positiver Saldo', 80, 'finance', 'Kassenstand über 100€'),
('balance_cleared', 'Ausgleich', 20, 'finance', 'Saldo ausgeglichen'),

-- Community
('profile_complete', 'Profil vollständig', 100, 'community', 'Profil komplett ausgefüllt'),
('daily_login', 'Täglicher Login', 5, 'community', 'Einmal pro Tag eingeloggt'),
('login_streak_7', 'Login Streak (7 Tage)', 50, 'community', '7 Tage in Folge eingeloggt'),
('login_streak_30', 'Login Streak (30 Tage)', 200, 'community', '30 Tage in Folge eingeloggt'),
('member_recruited', 'Mitglied geworben', 500, 'community', 'Neues Mitglied geworben'),

-- Penalties
('inactive_penalty', 'Inaktivitätsstrafe', -10, 'penalty', 'Pro Tag nach 30 Tagen Inaktivität'),
('fake_activity', 'Fake Activity', -500, 'penalty', 'Betrug/Missbrauch erkannt'),
('no_event_decision', 'Keine Event-Entscheidung', -15, 'penalty', 'Weder zugesagt noch abgesagt');

-- Leaderboard View
CREATE OR REPLACE VIEW v_xp_leaderboard AS
SELECT 
    u.id,
    u.name,
    u.username,
    u.xp_total,
    u.level_id,
    l.title as level_title,
    l.emoji as level_emoji,
    l.xp_required as current_level_xp,
    (SELECT xp_required FROM level_config WHERE level_id = u.level_id + 1) as next_level_xp,
    u.avatar,
    (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count,
    u.created_at as member_since
FROM users u
LEFT JOIN level_config l ON u.level_id = l.level_id
WHERE u.status = 'active'
ORDER BY u.xp_total DESC;

-- User XP Progress View
CREATE OR REPLACE VIEW v_user_xp_progress AS
SELECT 
    u.id,
    u.username,
    u.name,
    u.xp_total,
    u.level_id,
    l.title as current_level,
    l.emoji as level_emoji,
    l.xp_required as current_level_xp,
    COALESCE((SELECT xp_required FROM level_config WHERE level_id = u.level_id + 1), 999999) as next_level_xp,
    GREATEST(0, u.xp_total - l.xp_required) as xp_in_current_level,
    COALESCE((SELECT xp_required FROM level_config WHERE level_id = u.level_id + 1), 999999) - l.xp_required as xp_needed_for_next,
    ROUND(
        (GREATEST(0, u.xp_total - l.xp_required) / 
        NULLIF(COALESCE((SELECT xp_required FROM level_config WHERE level_id = u.level_id + 1), 999999) - l.xp_required, 0)) * 100, 
        2
    ) as progress_percent
FROM users u
LEFT JOIN level_config l ON u.level_id = l.level_id
WHERE u.status = 'active';

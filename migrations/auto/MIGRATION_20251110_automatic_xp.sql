-- MIGRATION 2025-11-10: Automatic XP System
-- Adds login tracking, automated XP awards, and badge system

-- Login tracking fields
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_reward TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS login_streak INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS longest_login_streak INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_completed BOOLEAN DEFAULT FALSE;

-- Badge configuration table
CREATE TABLE IF NOT EXISTS badge_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    badge_code VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    emoji VARCHAR(10),
    category VARCHAR(50),
    xp_reward INT DEFAULT 0,
    unlock_condition TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert XP actions
INSERT INTO xp_actions (action_code, action_name, category, xp_value, is_active) VALUES
-- Login Actions  
('LOGIN_DAILY', 'Täglicher Login', 'community', 5, 1),
('LOGIN_STREAK_7', '7-Tage Login Streak', 'community', 50, 1),
('LOGIN_STREAK_30', '30-Tage Login Streak', 'community', 200, 1),

-- Event Actions
('EVENT_ATTEND', 'Event Teilnahme', 'events', 20, 1),
('EVENT_ORGANIZE', 'Event organisiert', 'events', 80, 1),
('EVENT_COMPLETE', 'Event abgeschlossen', 'events', 30, 1),
('EVENT_LARGE', 'Event mit 10+ Teilnehmern', 'events', 25, 1),
('EVENT_STREAK_5', '5 Events hintereinander', 'events', 150, 1),

-- Payment Actions
('PAYMENT_ONTIME', 'Pünktliche Monatszahlung', 'kasse', 30, 1),
('PAYMENT_EXTRA', 'Extra-Zahlung (10€)', 'kasse', 100, 1),
('PAYMENT_BIG', 'Große Einzahlung (100€+)', 'kasse', 1000, 1),
('BALANCE_POSITIVE', 'Ausgeglichene Kasse', 'kasse', 20, 1),
('BALANCE_HIGH', 'Kassenstand über 100€', 'kasse', 80, 1),

-- Community Actions
('PROFILE_COMPLETE', 'Vollständiges Profil', 'community', 100, 1),
('REFERRAL', 'Neues Mitglied geworben', 'community', 500, 1),

-- Penalties
('INACTIVITY_PENALTY', 'Inaktivitätsstrafe', 'penalty', -10, 1),
('EVENT_NO_RESPONSE', 'Keine Event-Antwort', 'penalty', -5, 1),
('FAKE_ACTIVITY', 'Fake Activity erkannt', 'penalty', -500, 1),

-- Badge Reward
('BADGE_EARNED', 'Badge verdient', 'achievement', 0, 1)

ON DUPLICATE KEY UPDATE 
    action_name = VALUES(action_name),
    xp_value = VALUES(xp_value);

-- Insert badge configurations
INSERT INTO badge_config (badge_code, title, description, emoji, category, xp_reward, unlock_condition) VALUES
('MEMBER_1YEAR', '1 Jahr Crew', 'Ein Jahr Mitglied bei Pushing P', '🎂', 'membership', 500, '365 Tage Mitglied'),
('MEMBER_2YEAR', '2 Jahre Crew', 'Zwei Jahre Mitglied bei Pushing P', '🎉', 'membership', 1000, '730 Tage Mitglied'),
('EVENTS_25', 'Event Enthusiast', '25 Events besucht', '🎊', 'events', 250, '25 Event-Teilnahmen'),
('EVENTS_50', 'Event Master', '50 Events besucht', '🎆', 'events', 500, '50 Event-Teilnahmen'),
('EVENTS_100', 'Event Legend', '100 Events besucht', '🏆', 'events', 1000, '100 Event-Teilnahmen'),
('EVENT_ORGANIZER', 'Event Organisator', '10 Events organisiert', '📅', 'events', 300, '10 Events erstellt'),
('REFERRER', 'Talent Scout', '3 Mitglieder geworben', '🎯', 'community', 750, '3 Referrals'),
('SOCIAL_BUTTERFLY', 'Social Butterfly', '30-Tage Login Streak', '🦋', 'community', 200, '30 Tage am Stück eingeloggt'),
('LOYAL', 'Treue Seele', '90-Tage Login Streak', '💎', 'community', 500, '90 Tage am Stück eingeloggt'),
('BIG_SPENDER', 'Großzügig', '500€+ eingezahlt', '💰', 'kasse', 400, '500€ Gesamt-Einzahlungen'),
('DEBT_FREE', 'Schuldenfrei', '6 Monate keine Rückstände', '✅', 'kasse', 300, '6 Monate positiver Saldo'),
('LEVEL_5', 'Level 5 erreicht', 'Trusted Status erreicht', '⭐', 'achievement', 100, 'Level 5+'),
('LEVEL_10', 'Level 10 erreicht', 'Legend Status erreicht', '🌟', 'achievement', 500, 'Level 10+'),
('XP_MASTER', 'XP Master', '10.000 XP gesammelt', '🎮', 'achievement', 250, '10.000+ XP')

ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    xp_reward = VALUES(xp_reward);

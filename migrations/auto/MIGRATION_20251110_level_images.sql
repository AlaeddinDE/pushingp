-- MIGRATION 2025-11-10: Add level images
-- Adds image_path column to level_config and updates with PNG file paths

ALTER TABLE level_config ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) AFTER emoji;

UPDATE level_config SET image_path = '/Rookie-Photoroom.png' WHERE level_id = 1;
UPDATE level_config SET image_path = '/Staff-Photoroom.png' WHERE level_id = 2;
UPDATE level_config SET image_path = '/Member-Photoroom.png' WHERE level_id = 3;
UPDATE level_config SET image_path = '/Crew-Photoroom.png' WHERE level_id = 4;
UPDATE level_config SET image_path = '/Trusted-Photoroom.png' WHERE level_id = 5;
UPDATE level_config SET image_path = '/Inner-Circle-Photoroom.png' WHERE level_id = 6;
UPDATE level_config SET image_path = '/Elite-Photoroom.png' WHERE level_id = 7;
UPDATE level_config SET image_path = '/Ehrenmember-Photoroom.png' WHERE level_id = 8;
UPDATE level_config SET image_path = '/Pushing-Veteran-Photoroom.png' WHERE level_id = 9;
UPDATE level_config SET image_path = '/Pushing-Legend-Photoroom.png' WHERE level_id = 10;
UPDATE level_config SET image_path = '/Unantastbar-Photoroom.png' WHERE level_id = 11;

-- Recreate views with image paths
DROP VIEW IF EXISTS v_xp_leaderboard;
CREATE VIEW v_xp_leaderboard AS
SELECT 
    u.id,
    u.name,
    u.username,
    u.xp_total,
    u.level_id,
    l.title as level_title,
    l.emoji as level_emoji,
    l.image_path as level_image,
    l.xp_required as current_level_xp,
    (SELECT xp_required FROM level_config WHERE level_id = u.level_id + 1) as next_level_xp,
    u.avatar,
    (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count,
    u.created_at as member_since
FROM users u
LEFT JOIN level_config l ON u.level_id = l.level_id
WHERE u.status = 'active'
ORDER BY u.xp_total DESC;

DROP VIEW IF EXISTS v_user_xp_progress;
CREATE VIEW v_user_xp_progress AS
SELECT 
    u.id,
    u.username,
    u.name,
    u.xp_total,
    u.level_id,
    l.title as current_level,
    l.emoji as level_emoji,
    l.image_path as level_image,
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

-- MIGRATION 2025-11-20: Add Casino XP Actions
INSERT INTO xp_actions (action_code, action_name, xp_value, category, description, is_active) 
VALUES 
('CASINO_BET', 'Casino Einsatz', 0, 'casino', 'XP für Casino Einsatz (10 XP pro 1€)', 1),
('CASINO_WIN', 'Casino Gewinn', 0, 'casino', 'XP für Casino Gewinn (10 XP pro 1€)', 1)
ON DUPLICATE KEY UPDATE xp_value = VALUES(xp_value);

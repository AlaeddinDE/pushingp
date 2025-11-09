-- MIGRATION 2025-11-09: Consolidate mitglieder into users table
-- All members are users, users table becomes the single source of truth

-- Step 1: Fields already exist in users table (from previous migrations)
-- No need to add: pflicht_monatlich, shift_enabled, shift_mode, shift_start, shift_end, bio

-- Step 2: Migrate data from mitglieder_legacy to users where not already present
-- Only migrate mitglieder that don't exist as users
INSERT INTO users (username, name, email, role, status, pflicht_monatlich, shift_enabled, shift_mode, shift_start, shift_end, avatar, created_at, password)
SELECT 
  LOWER(REPLACE(m.name, ' ', '_')) as username,
  m.name,
  m.email,
  CASE WHEN m.role = 'admin' THEN 'admin' ELSE 'user' END as role,
  'active' as status,
  m.pflicht_monatlich,
  m.shift_enabled,
  CASE 
    WHEN m.shift_mode = 'early' THEN 'early'
    WHEN m.shift_mode = 'late' THEN 'late'
    WHEN m.shift_mode = 'night' THEN 'night'
    WHEN m.shift_mode = 'custom' THEN 'custom'
    ELSE 'none'
  END as shift_mode,
  m.shift_start,
  m.shift_end,
  m.avatar_url,
  COALESCE(m.letzte_aktualisierung, NOW()) as created_at,
  COALESCE(m.passwort, '') as password
FROM mitglieder_legacy m
WHERE NOT EXISTS (
  SELECT 1 FROM users u WHERE LOWER(u.name) = LOWER(m.name) OR (m.email IS NOT NULL AND u.email = m.email)
)
AND m.name IS NOT NULL
ON DUPLICATE KEY UPDATE username = username;

-- Step 3: Create admin action log table
CREATE TABLE IF NOT EXISTS admin_member_actions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action_type ENUM('add','lock','unlock','remove','reactivate') NOT NULL,
  target_user_id INT NOT NULL,
  reason TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id),
  FOREIGN KEY (target_user_id) REFERENCES users(id),
  INDEX idx_admin (admin_id),
  INDEX idx_target (target_user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 4: Table already renamed to mitglieder_legacy
-- No action needed

-- Step 5: Update kasse/transaktionen references to use users instead
-- kasse uses 'mitglied' column, transaktionen uses 'mitglied_id' (already points to users)
ALTER TABLE kasse 
  MODIFY COLUMN mitglied VARCHAR(100) NOT NULL COMMENT 'References users.name';

#!/bin/bash
# ===============================================
# 🚀 PushingP Auto-Deploy Script (Self-Healing + Demigration)
# ===============================================

set -e

PROJECT="Pushing P"
WEB_DIR="/var/www/html"
TMP_DIR="/tmp/pushingp_clone_$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 4)"
DB_NAME="Kasse"
DB_USER="Admin"
DB_PASS="mKahWNiaNxg9xpTAVepC."
BACKUP_DIR="/var/backups/pushingp"
MAIN_SQL="$WEB_DIR/SQL_SETUP_PUSHINGP_2.sql"
MIGR_DIR="$WEB_DIR/migrations"
UNDO_DIR="$MIGR_DIR/undo"
APPLIED_FILE="$WEB_DIR/.applied_migrations"
REPO_URL="https://github.com/AlaeddinDE/pushingp.git"
LOG_FILE="/var/log/pushingp_deploy.log"
DEPLOY_LINK="/usr/local/bin/deploy"

mkdir -p "$BACKUP_DIR" "$MIGR_DIR" "$UNDO_DIR"
touch "$APPLIED_FILE" "$LOG_FILE"

ts() { date '+%Y-%m-%d %H:%M:%S'; }
log() { echo "[$(ts)] $*" | tee -a "$LOG_FILE"; }

log "🚀 Starting deploy for $PROJECT"

# --- Backup ---
BACKUP_FILE="$BACKUP_DIR/db_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
log "🛡️  Creating DB backup: $BACKUP_FILE"
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_FILE" || { log "❌ DB backup failed"; exit 1; }
log "✅ DB backup created"

# --- Clone repo ---
log "📦 Cloning repository into $TMP_DIR"
git clone --depth=1 "$REPO_URL" "$TMP_DIR" >/dev/null 2>&1 || { log "❌ Git clone failed"; exit 1; }
log "✅ Repo cloned"

# --- Sync files (keep local migrations & applied list) ---
log "🧭 Syncing files to $WEB_DIR"
rsync -a --delete \
  --exclude ".git" \
  --exclude "node_modules" \
  --exclude "deploy.sh" \
  --exclude "AGENTS.md" \
  --exclude "deploy" \
  --exclude "migrations" \
  --exclude ".applied_migrations" \
  "$TMP_DIR"/ "$WEB_DIR"/
log "✅ Files synced"

# --- Main migration (idempotent) ---
if [ -f "$MAIN_SQL" ]; then
  log "🧠 Applying main migration $MAIN_SQL"
  mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MAIN_SQL" || { log "❌ Main migration failed"; exit 1; }
  log "✅ Applied main migration"
else
  log "⚠️  No main migration found"
fi

# --- Demigration: handle reverted/removed migrations ---
log "🧹 Checking for reverted migrations (Demigration)"
DEMIGR=0
TMP_APPLIED="$(mktemp)"
: > "$TMP_APPLIED"

while IFS= read -r applied || [ -n "$applied" ]; do
  [ -z "$applied" ] && continue
  # If the migration file no longer exists in repo, attempt rollback
  if [ ! -f "$MIGR_DIR/$applied" ]; then
    undo_file="$UNDO_DIR/${applied%.sql}_down.sql"
    if [ -f "$undo_file" ]; then
      log "🔁 Reverted in repo detected: $applied"
      log "↩️  Applying demigration: $(basename "$undo_file")"
      if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$undo_file"; then
        log "✅ Demigration applied for $applied"
        DEMIGR=$((DEMIGR+1))
        # do not keep this line in applied list
        continue
      else
        log "❌ Demigration failed for $applied"
        rm -f "$TMP_APPLIED"
        exit 1
      fi
    else
      log "⚠️  $applied removed in repo, but no undo script at $undo_file"
    fi
  fi
  echo "$applied" >> "$TMP_APPLIED"
done < "$APPLIED_FILE"

mv "$TMP_APPLIED" "$APPLIED_FILE"
[ "$DEMIGR" -gt 0 ] && log "🧽 Removed $DEMIGR reverted migration(s) from DB + registry"

# --- Forward migrations: apply new files once ---
log "🧱 Checking extra migrations in $MIGR_DIR"
NEW_MIGR=0
for sql in $(find "$MIGR_DIR" -maxdepth 1 -type f -name "*.sql" | sort); do
  base=$(basename "$sql")
  if ! grep -qx "$base" "$APPLIED_FILE"; then
    log "➡️  Applying migration: $base"
    if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$sql"; then
      echo "$base" >> "$APPLIED_FILE"
      log "✅ Migration applied: $base"
      NEW_MIGR=$((NEW_MIGR+1))
    else
      log "❌ Migration failed: $base"
      exit 1
    fi
  else
    log "⏩ Already applied: $base"
  fi
done

[ "$NEW_MIGR" -eq 0 ] && log "ℹ️  No new migrations to apply"

# --- Permissions ---
log "🔧 Setting permissions"
chown -R www-data:www-data "$WEB_DIR"
find "$WEB_DIR" -type d -exec chmod 775 {} \;
find "$WEB_DIR" -type f -exec chmod 664 {} \;

# --- Apache restart ---
log "🔁 Restarting Apache"
systemctl restart apache2 && log "✅ Apache restarted"

# --- Ensure deploy link ---
log "🩺 Ensuring deploy executable permissions"
chmod +x "$WEB_DIR/deploy.sh"
chmod 755 "$WEB_DIR/deploy.sh"
ln -sf "$WEB_DIR/deploy.sh" "$DEPLOY_LINK"
chmod 755 "$DEPLOY_LINK"
log "✅ Deploy executable restored"

log "✅ Deploy completed successfully"

# --- Auto-check migration placement ---
log "🧩 Verifying migration file placement"
mkdir -p "$MIGR_DIR/auto" "$MIGR_DIR/undo"

# Verschiebe versehentlich falsch abgelegte SQL-Dateien (außer Setup)
find "$WEB_DIR" -maxdepth 1 -type f -name "*.sql" ! -name "SQL_SETUP_*" | while read misplaced; do
  base=$(basename "$misplaced")
  log "⚠️  Found misplaced migration: $base — moving to $MIGR_DIR/auto"
  mv "$misplaced" "$MIGR_DIR/auto/$base"
done

chown -R www-data:www-data "$MIGR_DIR"
log "✅ Migration folder structure enforced"

exit 0


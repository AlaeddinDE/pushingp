#!/bin/bash
# ===============================================
# 🚀 PushingP Auto-Deploy Script
# ===============================================

set -e

# --- CONFIGURATION ---
PROJECT="Pushing P"
WEB_DIR="/var/www/html"
TMP_DIR="/tmp/pushingp_clone_$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 4)"
DB_NAME="Kasse"
DB_USER="Admin"
DB_PASS="mKahWNiaNxg9xpTAVepC."
BACKUP_DIR="/var/backups/pushingp"
MAIN_SQL="$WEB_DIR/SQL_SETUP_PUSHINGP_2.sql"
MIGR_DIR="$WEB_DIR/migrations"
APPLIED_FILE="$WEB_DIR/.applied_migrations"
REPO_URL="https://github.com/AlaeddinDE/pushingp.git"
LOG_FILE="/var/log/pushingp_deploy.log"

mkdir -p "$BACKUP_DIR" "$MIGR_DIR"
touch "$APPLIED_FILE" "$LOG_FILE"

ts() { date '+%Y-%m-%d %H:%M:%S'; }

log() { echo "[$(ts)] $*" | tee -a "$LOG_FILE"; }

# --- START DEPLOY ---
log "🚀 Starting deploy for $PROJECT"

# --- DB BACKUP ---
BACKUP_FILE="$BACKUP_DIR/db_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
log "🛡️  Creating DB backup: $BACKUP_FILE"
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_FILE" || {
  log "❌ DB backup failed"; exit 1;
}
log "✅ DB backup created"

# --- GIT CLONE ---
log "📦 Cloning repository into $TMP_DIR"
git clone --depth=1 "$REPO_URL" "$TMP_DIR" >/dev/null 2>&1 || {
  log "❌ Git clone failed"; exit 1;
}
log "✅ Repo cloned"

# --- SYNC FILES ---
log "🧭 Syncing files to $WEB_DIR"
rsync -a --delete --exclude ".git" --exclude "node_modules" --exclude "deploy.sh" "$TMP_DIR"/ "$WEB_DIR"/
log "✅ Files synced"

# --- MAIN MIGRATION ---
if [ -f "$MAIN_SQL" ]; then
  log "🧠 Applying main migration $MAIN_SQL"
  mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MAIN_SQL" || {
    log "❌ Migration failed"; exit 1;
  }
  log "✅ Applied main migration"
else
  log "⚠️  No main migration found"
fi

# --- PROCESS MIGRATIONS ---
log "🧱 Checking extra migrations in $MIGR_DIR"
NEW_MIGRATIONS=0
find "$MIGR_DIR" -maxdepth 1 -type f -name "*.sql" | sort | while read -r sqlfile; do
  fname=$(basename "$sqlfile")
  if ! grep -qx "$fname" "$APPLIED_FILE"; then
    log "➡️  Applying migration: $fname"
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$sqlfile" && echo "$fname" >> "$APPLIED_FILE"
    ((NEW_MIGRATIONS++))
  fi
done
if [ "$NEW_MIGRATIONS" -eq 0 ]; then
  log "ℹ️  No new migrations to apply"
fi

# --- PERMISSIONS ---
log "🔧 Setting permissions"
chown -R www-data:www-data "$WEB_DIR"
find "$WEB_DIR" -type d -exec chmod 775 {} \;
find "$WEB_DIR" -type f -exec chmod 664 {} \;

# --- RESTART APACHE ---
log "🔁 Restarting Apache"
systemctl restart apache2 && log "✅ Apache restarted"

log "✅ Deploy completed successfully"

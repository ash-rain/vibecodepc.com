#!/usr/bin/env bash
# VibeCodePC Auto-Update Script
# Checks GitHub Releases for a newer device app version.
# If found, downloads and applies the update with zero-downtime swap.

set -euo pipefail

APP_DIR="/opt/vibecodepc"
APP_USER="vibecodepc"
RELEASES_API="https://api.github.com/repos/vibecodepc/vibecodepc/releases/latest"
VERSION_FILE="${APP_DIR}/storage/app/firmware-version"
LOG_FILE="/var/log/vibecodepc/update.log"
LOCK_FILE="/var/lock/vibecodepc-update.lock"

mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$LOG_FILE") 2>&1

info()  { echo "[$(date -Iseconds)] [update] $*"; }
ok()    { echo "[$(date -Iseconds)] [update] ✓ $*"; }
skip()  { echo "[$(date -Iseconds)] [update] – $*"; exit 0; }
fail()  { echo "[$(date -Iseconds)] [update] ✗ $*"; exit 1; }

# ---------- lock to prevent concurrent runs ----------

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    skip "Another update is already running. Exiting."
fi

# ---------- check current version ----------

CURRENT_VERSION="unknown"
if [[ -f "$VERSION_FILE" ]]; then
    CURRENT_VERSION=$(cat "$VERSION_FILE" | tr -d '[:space:]')
fi
info "Current firmware version: ${CURRENT_VERSION}"

# ---------- fetch latest release ----------

info "Checking for updates..."
RELEASE_JSON=$(curl -fsSL --connect-timeout 10 "$RELEASES_API" 2>/dev/null) \
    || skip "Could not reach update server (offline?)."

LATEST_VERSION=$(echo "$RELEASE_JSON" | jq -r '.tag_name')
TARBALL_URL=$(echo "$RELEASE_JSON" | jq -r \
    '.assets[] | select(.name | test("device.*\\.tar\\.gz")) | .browser_download_url')
RELEASE_NOTES=$(echo "$RELEASE_JSON" | jq -r '.body // ""' | head -5)

if [[ -z "$LATEST_VERSION" || -z "$TARBALL_URL" ]]; then
    skip "Could not parse release info."
fi

info "Latest version: ${LATEST_VERSION}"

# ---------- compare versions ----------

if [[ "$CURRENT_VERSION" == "$LATEST_VERSION" ]]; then
    skip "Already up to date (${CURRENT_VERSION})."
fi

info "Update available: ${CURRENT_VERSION} → ${LATEST_VERSION}"
if [[ -n "$RELEASE_NOTES" ]]; then
    info "Release notes:"
    echo "$RELEASE_NOTES" | sed 's/^/  /'
fi

# ---------- download update ----------

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

info "Downloading ${LATEST_VERSION}..."
curl -fsSL --progress-bar "$TARBALL_URL" -o "${TMPDIR}/update.tar.gz"
ok "Download complete"

# ---------- verify tarball ----------

EXTRACTED="${TMPDIR}/app"
mkdir -p "$EXTRACTED"
tar -xzf "${TMPDIR}/update.tar.gz" -C "$EXTRACTED" --strip-components=1
ok "Tarball extracted"

# ---------- apply update (rolling swap) ----------

BACKUP_DIR="/opt/vibecodepc-backup"

info "Applying update..."

# 1. Put app in maintenance mode
sudo -u "$APP_USER" php "${APP_DIR}/artisan" down --retry=10 --no-interaction || true

# 2. Backup current app (keep last 2 backups)
if [[ -d "$APP_DIR" ]]; then
    rm -rf "${BACKUP_DIR}.2" 2>/dev/null || true
    mv "${BACKUP_DIR}" "${BACKUP_DIR}.2" 2>/dev/null || true
    cp -a "$APP_DIR" "$BACKUP_DIR"
fi

# 3. Sync new files (preserve .env, database, storage, and vendor for now)
rsync -a --delete \
    --exclude='.env' \
    --exclude='storage/device.json' \
    --exclude='storage/app/firmware-version' \
    --exclude='storage/app/projects/' \
    --exclude='database/database.sqlite' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    "${EXTRACTED}/" "${APP_DIR}/"

# 4. Install PHP dependencies
sudo -u "$APP_USER" composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet \
    --working-dir="$APP_DIR"

# 5. Run migrations
sudo -u "$APP_USER" php "${APP_DIR}/artisan" migrate --force --no-interaction

# 6. Clear and rebuild caches
sudo -u "$APP_USER" php "${APP_DIR}/artisan" optimize:clear --no-interaction
sudo -u "$APP_USER" php "${APP_DIR}/artisan" config:cache --no-interaction
sudo -u "$APP_USER" php "${APP_DIR}/artisan" route:cache --no-interaction
sudo -u "$APP_USER" php "${APP_DIR}/artisan" view:cache --no-interaction

# 7. Fix permissions
chown -R "${APP_USER}:${APP_USER}" "$APP_DIR"
chmod -R 755 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# 8. Record new version
echo "$LATEST_VERSION" > "$VERSION_FILE"

# 9. Restart services
systemctl restart vibecodepc.service
php-fpm8.4_reload() { systemctl reload php8.4-fpm 2>/dev/null || systemctl restart php8.4-fpm; }
php-fpm8.4_reload

# 10. Bring app out of maintenance mode
sudo -u "$APP_USER" php "${APP_DIR}/artisan" up --no-interaction

ok "Update applied successfully: ${CURRENT_VERSION} → ${LATEST_VERSION}"

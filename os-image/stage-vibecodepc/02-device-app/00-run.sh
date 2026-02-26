#!/bin/bash -e
# Stage: Deploy the Laravel device app (runs in chroot)
# Clones the device app from GitHub Releases into /opt/vibecodepc
# and runs a production build.

on_chroot << 'EOF'
set -euo pipefail

APP_DIR="/opt/vibecodepc"
APP_USER="vibecodepc"
APP_VERSION="${VIBECODEPC_APP_VERSION:-latest}"
RELEASES_API="https://api.github.com/repos/vibecodepc/vibecodepc/releases"

info()  { echo "[stage-vibecodepc/02] $*"; }

# ---------- create system user ----------

if ! id "$APP_USER" &>/dev/null; then
    useradd --system --create-home --home-dir "$APP_DIR" \
        --shell /bin/bash --groups docker,audio,video,gpio "$APP_USER"
    info "Created user: ${APP_USER}"
fi

mkdir -p "$APP_DIR"
chown "${APP_USER}:${APP_USER}" "$APP_DIR"

# ---------- download device app ----------

if [[ "$APP_VERSION" == "latest" ]]; then
    info "Fetching latest release info..."
    RELEASE_JSON=$(curl -fsSL "${RELEASES_API}/latest")
    APP_VERSION=$(echo "$RELEASE_JSON" | jq -r '.tag_name')
    TARBALL_URL=$(echo "$RELEASE_JSON" | jq -r '.assets[] | select(.name | test("device.*\\.tar\\.gz")) | .browser_download_url')
else
    TARBALL_URL="${RELEASES_API}/tags/${APP_VERSION}"
    TARBALL_URL=$(curl -fsSL "$TARBALL_URL" | jq -r '.assets[] | select(.name | test("device.*\\.tar\\.gz")) | .browser_download_url')
fi

info "Installing device app version: ${APP_VERSION}"

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

curl -fsSL "$TARBALL_URL" -o "${TMPDIR}/device-app.tar.gz"
tar -xzf "${TMPDIR}/device-app.tar.gz" -C "$APP_DIR" --strip-components=1

chown -R "${APP_USER}:${APP_USER}" "$APP_DIR"

# ---------- PHP dependencies (production, no dev) ----------

info "Installing PHP dependencies..."
cd "$APP_DIR"
sudo -u "$APP_USER" composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet

# ---------- Frontend assets ----------

info "Building frontend assets..."
sudo -u "$APP_USER" npm ci --no-audit --quiet
sudo -u "$APP_USER" npm run build
rm -rf node_modules  # clean up to save space on the image

# ---------- Environment file ----------

if [[ ! -f "${APP_DIR}/.env" ]]; then
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    sudo -u "$APP_USER" php artisan key:generate --no-interaction
    info ".env created and app key generated"
fi

# Set production values
sed -i \
    -e 's|APP_ENV=.*|APP_ENV=production|' \
    -e 's|APP_DEBUG=.*|APP_DEBUG=false|' \
    -e 's|LOG_LEVEL=.*|LOG_LEVEL=warning|' \
    "${APP_DIR}/.env"

# ---------- SQLite database ----------

DB_FILE="${APP_DIR}/database/database.sqlite"
if [[ ! -f "$DB_FILE" ]]; then
    touch "$DB_FILE"
    chown "${APP_USER}:${APP_USER}" "$DB_FILE"
    info "SQLite database created"
fi

# ---------- Permissions ----------

chown -R "${APP_USER}:${APP_USER}" "$APP_DIR"
chmod -R 755 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" -type d -exec chmod 775 {} \;
find "${APP_DIR}/storage" -type f -exec chmod 664 {} \;

# ---------- Store installed version ----------

echo "$APP_VERSION" > "${APP_DIR}/storage/app/firmware-version"
chown "${APP_USER}:${APP_USER}" "${APP_DIR}/storage/app/firmware-version"

info "Device app deployed: ${APP_VERSION}"
EOF

#!/usr/bin/env bash
# VibeCodePC First-Boot Initialization Script
# Runs once on the very first boot after flashing.
# Controlled by vibecodepc-first-boot.service (oneshot, removes itself when done).

set -euo pipefail

APP_DIR="/opt/vibecodepc"
APP_USER="vibecodepc"
STATE_FILE="/var/lib/vibecodepc/first-boot-done"
LOG_FILE="/var/log/vibecodepc/first-boot.log"

mkdir -p "$(dirname "$LOG_FILE")" /var/lib/vibecodepc

exec > >(tee -a "$LOG_FILE") 2>&1

info()  { echo "[$(date -Iseconds)] [first-boot] $*"; }
ok()    { echo "[$(date -Iseconds)] [first-boot] ✓ $*"; }
fail()  { echo "[$(date -Iseconds)] [first-boot] ✗ $*"; exit 1; }

# ---------- guard: skip if already done ----------

if [[ -f "$STATE_FILE" ]]; then
    info "First-boot already completed — skipping."
    exit 0
fi

info "=== VibeCodePC First Boot ==="

# ---------- 1. Expand filesystem (if not already done by raspi-config) ----------

info "Expanding filesystem..."
# raspi-config does this automatically on first boot; we ensure it's done
if command -v raspi-config &>/dev/null; then
    raspi-config nonint do_expand_rootfs || true
fi

# ---------- 2. Generate unique device identity ----------

DEVICE_JSON="${APP_DIR}/storage/device.json"

if [[ ! -f "$DEVICE_JSON" ]]; then
    info "Generating device identity..."
    sudo -u "$APP_USER" php "$APP_DIR/artisan" device:generate-id --no-interaction
    ok "Device identity created: $(jq -r '.id' "$DEVICE_JSON")"
else
    EXISTING_ID=$(jq -r '.id' "$DEVICE_JSON" 2>/dev/null || echo "unknown")
    info "Device identity already exists: ${EXISTING_ID}"
fi

# ---------- 3. Run database migrations ----------

info "Running database migrations..."
sudo -u "$APP_USER" php "$APP_DIR/artisan" migrate --force --no-interaction
ok "Migrations complete"

# ---------- 4. Optimize Laravel for production ----------

info "Optimizing Laravel..."
sudo -u "$APP_USER" php "$APP_DIR/artisan" config:cache --no-interaction
sudo -u "$APP_USER" php "$APP_DIR/artisan" route:cache --no-interaction
sudo -u "$APP_USER" php "$APP_DIR/artisan" view:cache --no-interaction
ok "Laravel optimized"

# ---------- 5. Set hostname from device identity ----------

DEVICE_ID=$(jq -r '.id' "$DEVICE_JSON" | cut -c1-8)
NEW_HOSTNAME="vibecodepc-${DEVICE_ID}"
hostnamectl set-hostname "$NEW_HOSTNAME"
# Also update /etc/hosts
sed -i "s/vibecodepc/${NEW_HOSTNAME}/g" /etc/hosts
ok "Hostname set: ${NEW_HOSTNAME}"

# ---------- 6. Start main services ----------

info "Starting services..."
systemctl start redis-server 2>/dev/null || systemctl start valkey 2>/dev/null || true
systemctl start vibecodepc.service
systemctl start code-server@vibecodepc.service
ok "Services started"

# ---------- 7. Display QR code on HDMI (TTY1) ----------

info "Starting pairing display on TTY1..."
systemctl start vibecodepc-display.service || true

# ---------- 8. Mark first boot complete ----------

date -Iseconds > "$STATE_FILE"
ok "First boot complete. Device ready for pairing."

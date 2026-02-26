#!/bin/bash -e
# Stage: First-Boot Setup (runs in chroot)
# Installs all systemd services, Nginx config, and the first-boot script.

STAGE_DIR="$(dirname "$0")"
FILES_DIR="${STAGE_DIR}/files"

on_chroot << 'EOF'
set -euo pipefail
info()  { echo "[stage-vibecodepc/03] $*"; }

# Nginx for serving the device app
apt-get install -y -qq nginx
EOF

# ---------- install scripts ----------

install -m 755 "${FILES_DIR}/vibecodepc-first-boot.sh"  "${ROOTFS_DIR}/usr/local/bin/vibecodepc-first-boot.sh"
install -m 755 "${FILES_DIR}/vibecodepc-display.sh"      "${ROOTFS_DIR}/usr/local/bin/vibecodepc-display.sh"
install -m 755 "${FILES_DIR}/vibecodepc-cli"             "${ROOTFS_DIR}/usr/local/bin/vibecodepc"

# ---------- install systemd units ----------

SYSTEMD_DIR="${ROOTFS_DIR}/etc/systemd/system"
mkdir -p "$SYSTEMD_DIR"

install -m 644 "${FILES_DIR}/vibecodepc-first-boot.service" "${SYSTEMD_DIR}/vibecodepc-first-boot.service"
install -m 644 "${FILES_DIR}/vibecodepc-display.service"    "${SYSTEMD_DIR}/vibecodepc-display.service"
install -m 644 "${FILES_DIR}/vibecodepc.service"             "${SYSTEMD_DIR}/vibecodepc.service"

# ---------- Nginx config ----------

install -m 644 "${FILES_DIR}/vibecodepc-nginx.conf" \
    "${ROOTFS_DIR}/etc/nginx/sites-available/vibecodepc"

on_chroot << 'EOF'
set -euo pipefail
info()  { echo "[stage-vibecodepc/03] $*"; }

# Enable nginx site
ln -sf /etc/nginx/sites-available/vibecodepc /etc/nginx/sites-enabled/vibecodepc
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable nginx

# Enable our services
systemctl enable vibecodepc-first-boot.service
systemctl enable vibecodepc.service
systemctl enable vibecodepc-display.service

# Disable getty@tty1 (our display service takes its place)
systemctl disable getty@tty1.service || true

# Create state directory
mkdir -p /var/lib/vibecodepc /var/log/vibecodepc

info "First-boot stage configured."
EOF

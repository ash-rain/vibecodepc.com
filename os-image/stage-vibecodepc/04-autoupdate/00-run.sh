#!/bin/bash -e
# Stage: Auto-Update Mechanism (runs in chroot)

STAGE_DIR="$(dirname "$0")"
FILES_DIR="${STAGE_DIR}/files"

# Install the update script
install -m 755 "${FILES_DIR}/vibecodepc-update.sh" \
    "${ROOTFS_DIR}/usr/local/bin/vibecodepc-update.sh"

# Install systemd units
SYSTEMD_DIR="${ROOTFS_DIR}/etc/systemd/system"
mkdir -p "$SYSTEMD_DIR"

install -m 644 "${FILES_DIR}/vibecodepc-update.service" \
    "${SYSTEMD_DIR}/vibecodepc-update.service"
install -m 644 "${FILES_DIR}/vibecodepc-update.timer" \
    "${SYSTEMD_DIR}/vibecodepc-update.timer"

on_chroot << 'EOF'
set -euo pipefail
info() { echo "[stage-vibecodepc/04] $*"; }

# Enable the timer (not the service directly — timer triggers the service)
systemctl enable vibecodepc-update.timer
info "Auto-update timer enabled."
EOF

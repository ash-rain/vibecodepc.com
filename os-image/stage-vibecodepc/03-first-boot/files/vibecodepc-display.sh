#!/usr/bin/env bash
# VibeCodePC Pairing Display — runs on TTY1
# Shows the pairing QR code, device ID, and local IP until the device is paired.
# Once paired, transitions to showing device status.

APP_DIR="/opt/vibecodepc"
APP_USER="vibecodepc"
REFRESH_INTERVAL=10   # seconds between display refreshes
TTY="/dev/tty1"

# Redirect output to TTY1
exec > "$TTY" 2>&1

clear_tty() {
    echo -e "\033[2J\033[H" > "$TTY"
}

get_local_ip() {
    ip route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") {print $(i+1); exit}}' \
        || hostname -I 2>/dev/null | awk '{print $1}' \
        || echo "unknown"
}

show_pairing_screen() {
    local device_json="${APP_DIR}/storage/device.json"
    local ip
    ip=$(get_local_ip)

    clear_tty

    echo ""
    echo "  ╔══════════════════════════════════════════╗"
    echo "  ║         Welcome to VibeCodePC            ║"
    echo "  ╠══════════════════════════════════════════╣"
    echo "  ║  Scan the QR code below to pair your     ║"
    echo "  ║  device and begin setup.                 ║"
    echo "  ╚══════════════════════════════════════════╝"
    echo ""

    if [[ -f "$device_json" ]]; then
        # Render QR code in terminal (UTF-8 blocks)
        sudo -u "$APP_USER" php "${APP_DIR}/artisan" device:show-qr 2>/dev/null \
            || echo "  [QR code unavailable — visit pairing URL below]"
    else
        echo "  Generating device identity..."
    fi

    echo ""
    echo "  ──────────────────────────────────────────"
    if [[ -f "$device_json" ]]; then
        local device_id pair_url
        device_id=$(jq -r '.id' "$device_json" 2>/dev/null || echo "...")
        pair_url="https://vibecodepc.com/pair/${device_id}"
        echo "  Device ID : ${device_id}"
        echo "  Pair URL  : ${pair_url}"
    fi
    echo "  Local IP  : ${ip}"
    echo "  Web UI    : http://${ip}/"
    echo "  ──────────────────────────────────────────"
    echo ""
    echo "  Or open a browser on the same network and go to:"
    echo "  http://vibecodepc.local/"
    echo ""
    printf "  (Refreshing every %ds — %s)\n" "$REFRESH_INTERVAL" "$(date '+%H:%M:%S')"
}

show_status_screen() {
    local ip
    ip=$(get_local_ip)

    clear_tty
    echo ""
    echo "  VibeCodePC — Running"
    echo "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  Web UI  : http://${ip}/"
    echo "  VS Code : http://${ip}:8443/"
    echo "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" device:status 2>/dev/null || true
    echo ""
    printf "  (Updated %s)\n" "$(date '+%H:%M:%S')"
}

# ---------- main loop ----------

while true; do
    # Check if the device has been paired (wizard_progress table has at least one row,
    # or we check device state from the app)
    PAIRED=$(sudo -u "$APP_USER" php "${APP_DIR}/artisan" \
        tinker --execute="echo App\Models\DeviceState::isPaired() ? '1' : '0';" 2>/dev/null \
        || echo "0")

    if [[ "$PAIRED" == "1" ]]; then
        show_status_screen
    else
        show_pairing_screen
    fi

    sleep "$REFRESH_INTERVAL"
done

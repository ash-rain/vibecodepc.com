#!/usr/bin/env bash
# VibeCodePC Manufacturing Flash Script
# Flashes the OS image to an NVMe SSD and burns a unique device identity.
#
# Usage (on a Linux flashing station):
#   sudo ./flash.sh --image /path/to/VibeCodePC-<version>.img.xz --device /dev/sdX
#
# What it does:
#   1. Writes the compressed image to the target block device
#   2. Mounts the root filesystem
#   3. Burns a unique device UUID (or uses a pre-generated one)
#   4. Records hardware serial and firmware version in device.json
#   5. Prints the QR label data (UUID + pairing URL) for sticker printing
#   6. Unmounts and verifies the written image

set -euo pipefail

# ---------- defaults ----------

IMAGE_PATH=""
TARGET_DEVICE=""
DEVICE_UUID=""             # leave empty to auto-generate
FIRMWARE_VERSION=""        # read from image if empty
DRY_RUN=false
SKIP_VERIFY=false
QR_OUTPUT_FILE=""          # if set, write QR data to this file (for batch printing)
CLOUD_BASE_URL="https://vibecodepc.com"

# ---------- helpers ----------

info()  { echo -e "\033[1;34m[info]\033[0m  $*"; }
ok()    { echo -e "\033[1;32m[ok]\033[0m    $*"; }
warn()  { echo -e "\033[1;33m[warn]\033[0m  $*"; }
fail()  { echo -e "\033[1;31m[error]\033[0m $*"; exit 1; }
hr()    { echo "────────────────────────────────────────────────"; }

need_root() { [[ $EUID -eq 0 ]] || fail "Must run as root: sudo $0 $*"; }
need_cmd()  { command -v "$1" &>/dev/null || fail "Required command not found: $1"; }

# ---------- parse args ----------

while [[ $# -gt 0 ]]; do
    case "$1" in
        --image|-i)       IMAGE_PATH="$2";    shift 2 ;;
        --device|-d)      TARGET_DEVICE="$2"; shift 2 ;;
        --uuid|-u)        DEVICE_UUID="$2";   shift 2 ;;
        --version|-v)     FIRMWARE_VERSION="$2"; shift 2 ;;
        --qr-output)      QR_OUTPUT_FILE="$2"; shift 2 ;;
        --cloud-url)      CLOUD_BASE_URL="$2"; shift 2 ;;
        --dry-run)        DRY_RUN=true; shift ;;
        --skip-verify)    SKIP_VERIFY=true; shift ;;
        -h|--help)
            cat <<USAGE
Usage: sudo ./flash.sh --image <image.img.xz> --device <block-device>

Options:
  --image, -i     Path to .img or .img.xz image file (required)
  --device, -d    Target block device, e.g. /dev/sda (required)
  --uuid, -u      Pre-generated device UUID (auto-generated if not provided)
  --version, -v   Firmware version string (read from image if not provided)
  --qr-output     File to append QR label data to (for batch printing)
  --cloud-url     Base URL for pairing QR code (default: https://vibecodepc.com)
  --dry-run       Simulate without writing anything
  --skip-verify   Skip post-flash checksum verification

USAGE
            exit 0
            ;;
        *) fail "Unknown argument: $1" ;;
    esac
done

# ---------- validate ----------

need_root
[[ -n "$IMAGE_PATH" ]]    || fail "--image is required"
[[ -n "$TARGET_DEVICE" ]] || fail "--device is required"
[[ -f "$IMAGE_PATH" ]]    || fail "Image not found: $IMAGE_PATH"
[[ -b "$TARGET_DEVICE" ]] || { $DRY_RUN || fail "Not a block device: $TARGET_DEVICE"; }

need_cmd dd
need_cmd jq
need_cmd uuidgen
need_cmd losetup
need_cmd mount
need_cmd umount
need_cmd sha256sum
command -v qrencode &>/dev/null || warn "qrencode not found — skipping terminal QR display"

# ---------- safety check ----------

if ! $DRY_RUN; then
    # Confirm target is not mounted
    if grep -q "^${TARGET_DEVICE}" /proc/mounts 2>/dev/null; then
        fail "Target device ${TARGET_DEVICE} is currently mounted. Unmount it first."
    fi

    # Detect if target is a system disk
    ROOT_DEVICE=$(lsblk -no PKNAME "$(findmnt -n -o SOURCE /)" 2>/dev/null || true)
    if [[ "/dev/${ROOT_DEVICE}" == "$TARGET_DEVICE" ]]; then
        fail "REFUSING: ${TARGET_DEVICE} appears to be the system root disk!"
    fi

    hr
    warn "ABOUT TO WRITE TO: ${TARGET_DEVICE}"
    warn "ALL DATA ON THIS DEVICE WILL BE ERASED."
    hr
    lsblk "$TARGET_DEVICE" 2>/dev/null || true
    hr
    read -r -p "Type 'FLASH' to confirm: " CONFIRM
    [[ "$CONFIRM" == "FLASH" ]] || { echo "Cancelled."; exit 0; }
fi

# ---------- generate / validate device UUID ----------

if [[ -z "$DEVICE_UUID" ]]; then
    DEVICE_UUID=$(uuidgen | tr '[:upper:]' '[:lower:]')
    info "Generated device UUID: ${DEVICE_UUID}"
else
    # Validate UUID v4 format
    if ! [[ "$DEVICE_UUID" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]]; then
        fail "Invalid UUID format: ${DEVICE_UUID}"
    fi
    info "Using provided UUID: ${DEVICE_UUID}"
fi

PAIR_URL="${CLOUD_BASE_URL}/pair/${DEVICE_UUID}"

# ---------- detect image format ----------

IMAGE_COMPRESSED=false
[[ "$IMAGE_PATH" == *.xz ]] && IMAGE_COMPRESSED=true

# ---------- flash image ----------

info "Flashing image to ${TARGET_DEVICE}..."
info "Source: ${IMAGE_PATH}"

if ! $DRY_RUN; then
    if $IMAGE_COMPRESSED; then
        xzcat "$IMAGE_PATH" | dd of="$TARGET_DEVICE" bs=4M conv=fsync status=progress
    else
        dd if="$IMAGE_PATH" of="$TARGET_DEVICE" bs=4M conv=fsync status=progress
    fi
    sync
    ok "Image written"
else
    ok "[DRY RUN] Would flash ${IMAGE_PATH} → ${TARGET_DEVICE}"
fi

# ---------- detect root partition ----------

# Allow kernel to re-read partition table
if ! $DRY_RUN; then
    partprobe "$TARGET_DEVICE" 2>/dev/null || true
    sleep 2

    # The root filesystem is typically the second partition on RPi images
    ROOT_PART="${TARGET_DEVICE}2"
    if [[ ! -b "$ROOT_PART" ]]; then
        # Try with 'p' separator (e.g. /dev/mmcblk0p2)
        ROOT_PART="${TARGET_DEVICE}p2"
    fi
    [[ -b "$ROOT_PART" ]] || fail "Could not find root partition on ${TARGET_DEVICE}"
fi

# ---------- mount root partition and burn device.json ----------

if ! $DRY_RUN; then
    MOUNT_DIR=$(mktemp -d)
    trap "umount ${MOUNT_DIR} 2>/dev/null; rmdir ${MOUNT_DIR} 2>/dev/null" EXIT

    mount "$ROOT_PART" "$MOUNT_DIR"

    DEVICE_JSON_PATH="${MOUNT_DIR}/opt/vibecodepc/storage/device.json"
    FIRMWARE_VERSION_PATH="${MOUNT_DIR}/opt/vibecodepc/storage/app/firmware-version"

    if [[ -z "$FIRMWARE_VERSION" && -f "$FIRMWARE_VERSION_PATH" ]]; then
        FIRMWARE_VERSION=$(cat "$FIRMWARE_VERSION_PATH" | tr -d '[:space:]')
    fi
    FIRMWARE_VERSION="${FIRMWARE_VERSION:-1.0.0}"

    # Detect hardware serial from device if available (not possible here — use placeholder)
    HARDWARE_SERIAL="pre-burned"
    MANUFACTURED_AT=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

    # Write device.json
    mkdir -p "$(dirname "$DEVICE_JSON_PATH")"
    cat > "$DEVICE_JSON_PATH" <<JSON
{
    "id": "${DEVICE_UUID}",
    "hardware_serial": "${HARDWARE_SERIAL}",
    "manufactured_at": "${MANUFACTURED_AT}",
    "firmware_version": "${FIRMWARE_VERSION}"
}
JSON
    # Set ownership to UID/GID of vibecodepc user (typically 1001)
    VIBECODEPC_UID=$(grep '^vibecodepc:' "${MOUNT_DIR}/etc/passwd" | cut -d: -f3 || echo 1001)
    chown "${VIBECODEPC_UID}:${VIBECODEPC_UID}" "$DEVICE_JSON_PATH"
    ok "Burned device.json: ${DEVICE_UUID}"

    umount "$MOUNT_DIR"
    trap - EXIT
    rmdir "$MOUNT_DIR"
    sync
fi

# ---------- verify image (optional) ----------

if ! $SKIP_VERIFY && ! $DRY_RUN; then
    info "Verifying written data (spot-check first 256 MB)..."
    # Compare first 256 MB of original vs written
    ORIG_HASH=$(if $IMAGE_COMPRESSED; then
        xzcat "$IMAGE_PATH" | head -c $((256*1024*1024)) | sha256sum | awk '{print $1}'
    else
        head -c $((256*1024*1024)) "$IMAGE_PATH" | sha256sum | awk '{print $1}'
    fi)
    WRITE_HASH=$(dd if="$TARGET_DEVICE" bs=4M count=64 2>/dev/null | sha256sum | awk '{print $1}')

    if [[ "$ORIG_HASH" == "$WRITE_HASH" ]]; then
        ok "Verification passed"
    else
        fail "Verification FAILED — image may be corrupt"
    fi
fi

# ---------- output QR label data ----------

hr
ok "Flash complete!"
hr
echo ""
echo "  Device UUID : ${DEVICE_UUID}"
echo "  Firmware    : ${FIRMWARE_VERSION:-unknown}"
echo "  Pair URL    : ${PAIR_URL}"
echo ""

if command -v qrencode &>/dev/null; then
    qrencode -t UTF8 "$PAIR_URL"
fi

# Append to batch label file if requested
if [[ -n "$QR_OUTPUT_FILE" ]]; then
    mkdir -p "$(dirname "$QR_OUTPUT_FILE")"
    cat >> "$QR_OUTPUT_FILE" <<CSV
${DEVICE_UUID},${FIRMWARE_VERSION:-unknown},${MANUFACTURED_AT:-$(date -u +"%Y-%m-%dT%H:%M:%SZ")},${PAIR_URL}
CSV
    info "Label data appended to: ${QR_OUTPUT_FILE}"
fi

hr

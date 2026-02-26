#!/usr/bin/env bash
# VibeCodePC OS Image Build Script
# Clones pi-gen, patches in our custom stage, and builds the image.
#
# Prerequisites (Debian/Ubuntu host):
#   sudo apt-get install coreutils quilt parted qemu-user-static debootstrap \
#     zerofree zip dosfstools libarchive-tools libcap2-bin grep rsync xz-utils \
#     file git curl bc xxd
#
# Usage:
#   ./build.sh [--clean]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PIGEN_DIR="${SCRIPT_DIR}/.pi-gen"
PIGEN_REPO="https://github.com/RPi-Distro/pi-gen.git"
PIGEN_TAG="arm64"           # use the arm64 branch for RPi 5
OUT_DIR="${SCRIPT_DIR}/deploy"

info()  { echo -e "\033[1;34m[info]\033[0m  $*"; }
ok()    { echo -e "\033[1;32m[ok]\033[0m    $*"; }
fail()  { echo -e "\033[1;31m[error]\033[0m $*"; exit 1; }

# ---------- parse args ----------

CLEAN=false
for arg in "$@"; do
    [[ "$arg" == "--clean" ]] && CLEAN=true
done

# ---------- clone / update pi-gen ----------

if [[ ! -d "$PIGEN_DIR" ]]; then
    info "Cloning pi-gen..."
    git clone --depth 1 --branch "$PIGEN_TAG" "$PIGEN_REPO" "$PIGEN_DIR"
else
    info "Updating pi-gen..."
    git -C "$PIGEN_DIR" fetch --depth 1 origin "$PIGEN_TAG"
    git -C "$PIGEN_DIR" reset --hard FETCH_HEAD
fi

# ---------- clean previous build ----------

if $CLEAN; then
    info "Cleaning previous build artifacts..."
    rm -rf "${PIGEN_DIR}/work" "${PIGEN_DIR}/deploy"
    # Remove SKIP markers so stages re-run
    find "$PIGEN_DIR" -name "SKIP" -delete
    find "$PIGEN_DIR" -name "SKIP_IMAGES" -delete
fi

# ---------- skip standard non-lite stages ----------

touch "${PIGEN_DIR}/stage3/SKIP" "${PIGEN_DIR}/stage3/SKIP_IMAGES" 2>/dev/null || true
touch "${PIGEN_DIR}/stage4/SKIP" "${PIGEN_DIR}/stage4/SKIP_IMAGES" 2>/dev/null || true
touch "${PIGEN_DIR}/stage5/SKIP" "${PIGEN_DIR}/stage5/SKIP_IMAGES" 2>/dev/null || true

# ---------- symlink our custom stage ----------

STAGE_LINK="${PIGEN_DIR}/stage-vibecodepc"
if [[ -L "$STAGE_LINK" ]]; then
    rm "$STAGE_LINK"
fi
ln -s "${SCRIPT_DIR}/stage-vibecodepc" "$STAGE_LINK"
info "Linked stage-vibecodepc → ${STAGE_LINK}"

# ---------- copy config ----------

cp "${SCRIPT_DIR}/config" "${PIGEN_DIR}/config"

# Inject the absolute path for STAGE_LIST (pi-gen needs it)
sed -i "s|stage-vibecodepc|${PIGEN_DIR}/stage-vibecodepc|g" "${PIGEN_DIR}/config"

# ---------- run pi-gen ----------

info "Starting pi-gen build (this takes 20–40 minutes)..."
cd "$PIGEN_DIR"
sudo PIGEN_DOCKER_OPTS="" bash build-docker.sh

# ---------- collect output ----------

mkdir -p "$OUT_DIR"
find "${PIGEN_DIR}/deploy" -name "*.img.xz" -exec cp {} "$OUT_DIR/" \;
find "${PIGEN_DIR}/deploy" -name "*.info" -exec cp {} "$OUT_DIR/" \;

ok "Build complete. Image(s) in: ${OUT_DIR}/"
ls -lh "${OUT_DIR}/"

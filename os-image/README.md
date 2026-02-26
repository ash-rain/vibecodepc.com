# VibeCodePC OS Image

This directory contains everything needed to build, flash, and maintain the custom Raspberry Pi OS image that ships on every VibeCodePC device.

---

## Table of Contents

1. [Overview](#overview)
2. [Directory Structure](#directory-structure)
3. [How the Build Works](#how-the-build-works)
4. [Build Prerequisites](#build-prerequisites)
5. [Building the Image](#building-the-image)
6. [Stage-by-Stage Breakdown](#stage-by-stage-breakdown)
   - [Stage 0–2: Base Debian Lite](#stage-02-base-debian-lite-pi-gen-standard)
   - [Stage: 00-packages](#stage-00-packages)
   - [Stage: 01-install-software](#stage-01-install-software)
   - [Stage: 02-device-app](#stage-02-device-app)
   - [Stage: 03-first-boot](#stage-03-first-boot)
   - [Stage: 04-autoupdate](#stage-04-autoupdate)
7. [First Boot Sequence](#first-boot-sequence)
8. [HDMI Display Service](#hdmi-display-service)
9. [Auto-Update Mechanism](#auto-update-mechanism)
10. [Manufacturing: Flashing Devices](#manufacturing-flashing-devices)
11. [The `vibecodepc` CLI](#the-vibecodepc-cli)
12. [Key Paths on the Device](#key-paths-on-the-device)
13. [Customising the Build](#customising-the-build)
14. [Troubleshooting](#troubleshooting)

---

## Overview

The image is built with [pi-gen](https://github.com/RPi-Distro/pi-gen) — the same tool Raspberry Pi Ltd uses for the official OS. We add a single custom stage (`stage-vibecodepc`) on top of the standard **Bookworm Lite** base (stages 0–2). Desktop stages 3–5 are explicitly skipped, keeping the image lean.

At the manufacturing line, a base image is built once and flashed to many SSDs. Each flash run burns a **unique device UUID** into the image, which is what makes every VibeCodePC individually identifiable. The UUID is printed as a QR label and attached to the hardware.

On first power-on, a one-shot systemd service finalises the device (expands the filesystem, runs migrations, sets the hostname), then the device waits for the user to scan the QR code to begin pairing.

---

## Directory Structure

```
os-image/
├── config                          # pi-gen build variables
├── build.sh                        # Wrapper: clones pi-gen, links stage, runs build
│
├── stage-vibecodepc/               # Our custom pi-gen stage
│   ├── 00-packages/
│   │   └── packages                # apt packages (avahi, sqlite3, qrencode, etc.)
│   │
│   ├── 01-install-software/
│   │   └── 00-run.sh               # PHP 8.4, Composer, Node 22, Docker, cloudflared,
│   │                               # code-server, Valkey — all from upstream repos
│   │
│   ├── 02-device-app/
│   │   └── 00-run.sh               # Downloads device app from GitHub Releases,
│   │                               # builds assets, seeds .env + SQLite
│   │
│   ├── 03-first-boot/
│   │   ├── 00-run.sh               # Stage install script (installs Nginx, copies files)
│   │   └── files/
│   │       ├── vibecodepc-first-boot.sh       # First-boot initialisation script
│   │       ├── vibecodepc-first-boot.service  # systemd oneshot unit
│   │       ├── vibecodepc-display.sh          # TTY1 pairing / status display loop
│   │       ├── vibecodepc-display.service     # systemd unit (replaces getty@tty1)
│   │       ├── vibecodepc.service             # Laravel Horizon queue worker
│   │       ├── vibecodepc-nginx.conf          # Nginx site (serves Laravel on :80)
│   │       └── vibecodepc-cli                 # Installed as /usr/local/bin/vibecodepc
│   │
│   └── 04-autoupdate/
│       ├── 00-run.sh               # Stage install script
│       └── files/
│           ├── vibecodepc-update.sh           # Update script (GitHub Releases → rolling swap)
│           ├── vibecodepc-update.service      # systemd oneshot unit
│           └── vibecodepc-update.timer        # systemd timer (daily + on-boot)
│
└── scripts/
    └── flash.sh                    # Manufacturing flash + device ID burn script
```

---

## How the Build Works

### pi-gen in a nutshell

pi-gen works by running a series of **stages** in order. Each stage is a directory that may contain:

- `packages` — list of apt packages to install
- `XX-run.sh` — shell script that runs **outside** the chroot (can copy files into `$ROOTFS_DIR`)
- `XX-run-chroot.sh` — shell script that runs **inside** the chroot

`build.sh` handles the plumbing:

1. Clones (or updates) pi-gen's `arm64` branch into `.pi-gen/`
2. Creates a symlink `.pi-gen/stage-vibecodepc → ./stage-vibecodepc`
3. Copies `config` into pi-gen's root
4. Places `SKIP` + `SKIP_IMAGES` markers in stages 3, 4, 5 so the desktop is never built
5. Calls pi-gen's `build-docker.sh` which builds inside a Debian container (no host contamination)
6. Copies the final `.img.xz` to `deploy/`

The `STAGE_LIST` in `config` tells pi-gen exactly which stages to run:

```
stage0  →  stage1  →  stage2  →  stage-vibecodepc
(Debian base)  (minimal)  (lite, no desktop)  (our custom software)
```

### Why Docker?

pi-gen uses Docker so the build environment is identical regardless of the host OS. The inner container runs Debian Bookworm arm64 under QEMU. This means you can build the RPi image on an x86 Linux workstation or CI runner without a Pi.

---

## Build Prerequisites

### Host machine

A **Debian or Ubuntu** Linux host (x86_64 or arm64). macOS is not supported for building — use a Linux VM or CI.

```bash
# Install host dependencies
sudo apt-get update
sudo apt-get install -y \
    coreutils quilt parted qemu-user-static debootstrap \
    zerofree zip dosfstools libarchive-tools libcap2-bin \
    grep rsync xz-utils file git curl bc xxd
```

### Docker

Docker must be installed and the current user must have permission to run it (or use `sudo`).

```bash
# Install Docker (if not already installed)
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER"
newgrp docker
```

### GitHub access

Stage `02-device-app` downloads the device app tarball from the GitHub Releases API. This works without authentication for public releases. If the repo is private, set a `GITHUB_TOKEN` environment variable before building:

```bash
export GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
```

---

## Building the Image

```bash
cd os-image/

# First build (clones pi-gen, ~20–40 min)
./build.sh

# Rebuild from scratch (wipe all intermediate work)
./build.sh --clean

# Pin a specific device app version (default: latest release)
VIBECODEPC_APP_VERSION=v1.2.0 ./build.sh
```

Output lands in `os-image/deploy/`:

```
deploy/
├── VibeCodePC-<date>-arm64.img.xz    (~2 GB compressed)
└── VibeCodePC-<date>-arm64.info      (build metadata)
```

### Incremental builds

pi-gen caches each completed stage in `.pi-gen/work/`. If you only change `stage-vibecodepc`, the next build skips stages 0–2 (about 15 min saved). Use `--clean` only when you need a guaranteed-fresh base.

---

## Stage-by-Stage Breakdown

### Stage 0–2: Base Debian Lite (pi-gen standard)

These are pi-gen's own stages, unchanged:

| Stage | What it does |
|-------|-------------|
| `stage0` | Debootstrap minimal Debian 12 arm64, configure apt, locale, timezone |
| `stage1` | Add firmware packages, kernel, bootloader config for RPi 5 |
| `stage2` | Lite system — systemd, networking, SSH, no desktop. This is what `Raspberry Pi OS Lite` ships as. |

After stage2 you have a clean, bootable, 800 MB Debian Lite system with nothing but the OS.

---

### Stage: `00-packages`

**File:** `stage-vibecodepc/00-packages/packages`

pi-gen reads this file and runs a single `apt-get install` for all listed packages before any of our run scripts execute. Packages installed here:

| Group | Packages |
|-------|---------|
| Core utils | `curl`, `wget`, `git`, `jq`, `htop`, `unzip`, `ca-certificates`, `gnupg` |
| mDNS | `avahi-daemon`, `avahi-utils`, `libnss-mdns` |
| Database | `sqlite3`, `libsqlite3-dev` |
| Queue/cache | `redis-server` (fallback; Valkey installed later from upstream) |
| Python | `python3`, `python3-pip`, `python3-venv` |
| Docker deps | `libffi-dev`, `libssl-dev`, `apt-transport-https` |
| QR display | `qrencode`, `fbi` |
| Pi utilities | `i2c-tools`, `libraspberrypi-bin`, `raspi-config` |

All packages that have an upstream repo (PHP, Docker, Node, cloudflared, code-server, Valkey) are intentionally **not** installed here — they're installed in the next stage to get current versions rather than potentially outdated distro packages.

---

### Stage: `01-install-software`

**File:** `stage-vibecodepc/01-install-software/00-run.sh`

Runs in chroot. Adds upstream apt sources and installs all runtime software:

#### PHP 8.4

Added from `packages.sury.org` (Ondřej Surý's repo, the de-facto PHP source for Debian):

```
php8.4  php8.4-cli  php8.4-fpm  php8.4-sqlite3  php8.4-redis
php8.4-curl  php8.4-mbstring  php8.4-xml  php8.4-zip
php8.4-intl  php8.4-bcmath  php8.4-gd  php8.4-opcache
```

OPcache is tuned conservatively for the Pi 5's 8 GB RAM:

```ini
opcache.enable=1
opcache.memory_consumption=64       # MB — enough for Laravel without wasting RAM
opcache.max_accelerated_files=4096
opcache.validate_timestamps=0       # disabled in production for speed
```

JIT is left off — it adds complexity and marginal benefit for a primarily I/O-bound Laravel app.

#### Composer

Downloaded directly from `getcomposer.org` and installed to `/usr/local/bin/composer`.

#### Node.js 22 LTS

Added from `deb.nodesource.com`. Both `npm` and `pnpm` are installed globally.

#### Docker

Added from `download.docker.com/linux/debian`. Includes `docker-ce`, `docker-compose-plugin`, and `docker-buildx-plugin`. The `vibecodepc` system user is added to the `docker` group so the app can manage project containers.

#### cloudflared

Added from Cloudflare's own apt repo (`pkg.cloudflare.com/cloudflared`). The wizard uses this binary to create and manage tunnels.

#### code-server

Installed via the official `code-server.dev/install.sh` installer (picks the latest stable release). The wizard configures it; this stage just ensures the binary is present.

#### Valkey (Redis-compatible)

Added from `packages.redis.io`. Valkey is the open-source Redis fork that took over after Redis changed its licence. Falls back to `redis-server` (already installed from apt packages) if the Valkey repo isn't available yet for this architecture.

---

### Stage: `02-device-app`

**File:** `stage-vibecodepc/02-device-app/00-run.sh`

Runs in chroot. Downloads and installs the production build of the Laravel device app.

#### Steps

1. **Create system user** `vibecodepc` (system user, home at `/opt/vibecodepc`, added to `docker`, `audio`, `video`, `gpio`)

2. **Fetch release tarball** from the GitHub Releases API:
   ```
   GET https://api.github.com/repos/vibecodepc/vibecodepc/releases/latest
   ```
   Finds the asset matching `device*.tar.gz`. Set `VIBECODEPC_APP_VERSION=v1.x.x` to pin a specific version.

3. **Install PHP dependencies** — `composer install --no-dev --optimize-autoloader`. Dev dependencies (Pest, etc.) are excluded to shrink the image.

4. **Build frontend assets** — `npm ci && npm run build`. `node_modules` is deleted afterward; only the compiled `public/build/` output is kept.

5. **Seed `.env`** — copies `.env.example`, generates an `APP_KEY`, sets `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`.

6. **Create SQLite database** — touches `database/database.sqlite`. Schema is applied on first boot (not here) so the image remains generic across units.

7. **Record firmware version** — writes the release tag to `storage/app/firmware-version` (read by the update script and the `vibecodepc version` CLI command).

> **Note:** `device.json` (the device identity) is deliberately **not** created here. It is either burned by `flash.sh` at manufacturing time or generated on first boot. This is what makes each flashed unit unique.

---

### Stage: `03-first-boot`

**File:** `stage-vibecodepc/03-first-boot/00-run.sh` + `files/`

This stage installs the runtime services that make the device work. The `00-run.sh` script:

1. Installs `nginx` (apt, inside chroot)
2. Copies scripts to `/usr/local/bin/`:
   - `vibecodepc-first-boot.sh`
   - `vibecodepc-display.sh`
   - `vibecodepc` (the CLI)
3. Copies systemd units to `/etc/systemd/system/`:
   - `vibecodepc-first-boot.service`
   - `vibecodepc-display.service`
   - `vibecodepc.service`
4. Enables the Nginx site and removes the default site
5. Enables all services via `systemctl enable`
6. Disables `getty@tty1` (replaced by the display service)
7. Creates `/var/lib/vibecodepc/` and `/var/log/vibecodepc/`

**Files installed by this stage:**

| File | Installed as | Purpose |
|------|-------------|---------|
| `vibecodepc-first-boot.sh` | `/usr/local/bin/vibecodepc-first-boot.sh` | One-time boot init |
| `vibecodepc-first-boot.service` | `/etc/systemd/system/` | systemd oneshot unit |
| `vibecodepc-display.sh` | `/usr/local/bin/vibecodepc-display.sh` | TTY1 display loop |
| `vibecodepc-display.service` | `/etc/systemd/system/` | systemd unit for display |
| `vibecodepc.service` | `/etc/systemd/system/` | Laravel Horizon queue worker |
| `vibecodepc-nginx.conf` | `/etc/nginx/sites-available/vibecodepc` | Nginx PHP-FPM site on :80 |
| `vibecodepc-cli` | `/usr/local/bin/vibecodepc` | Device management CLI |

---

### Stage: `04-autoupdate`

**File:** `stage-vibecodepc/04-autoupdate/00-run.sh` + `files/`

Installs the auto-update mechanism:

| File | Installed as |
|------|-------------|
| `vibecodepc-update.sh` | `/usr/local/bin/vibecodepc-update.sh` |
| `vibecodepc-update.service` | `/etc/systemd/system/` |
| `vibecodepc-update.timer` | `/etc/systemd/system/` |

Only the **timer** is enabled (not the service directly). The timer triggers the service.

---

## First Boot Sequence

When the flashed SSD powers on for the first time, events happen in this order:

```
Power on
    │
    ▼
systemd starts all enabled units
    │
    ├── redis-server / valkey          (cache + queue broker)
    ├── vibecodepc-first-boot.service  (oneshot, see below)
    ├── vibecodepc.service             (waits for first-boot to complete)
    └── vibecodepc-display.service     (TTY1, starts immediately)
```

### vibecodepc-first-boot.service

The unit has:
```ini
ConditionPathExists=!/var/lib/vibecodepc/first-boot-done
```

This means it **only runs if the state file does not exist**. If the device reboots mid-wizard, it will not re-run first-boot. The state file is written as the last step, so an interrupted boot will re-run first-boot cleanly on the next attempt.

**Steps performed by `vibecodepc-first-boot.sh`:**

| Step | What happens |
|------|-------------|
| 1 | `raspi-config nonint do_expand_rootfs` — expands the root partition to fill the SSD |
| 2 | Check for `storage/device.json` — if absent, run `php artisan device:generate-id` to create a UUID |
| 3 | `php artisan migrate --force` — applies all database migrations against the fresh SQLite file |
| 4 | `php artisan config:cache && route:cache && view:cache` — pre-compiles Laravel caches for fast startup |
| 5 | Set hostname to `vibecodepc-{first 8 chars of UUID}` (e.g. `vibecodepc-a1b2c3d4`) and update `/etc/hosts` |
| 6 | `systemctl start vibecodepc.service` + `code-server@vibecodepc.service` |
| 7 | `systemctl start vibecodepc-display.service` (shows QR on TTY1) |
| 8 | Write timestamp to `/var/lib/vibecodepc/first-boot-done` |

All output is logged to `/var/log/vibecodepc/first-boot.log`.

---

## HDMI Display Service

`vibecodepc-display.service` replaces `getty@tty1` on the HDMI output. It runs `vibecodepc-display.sh` in a continuous loop, refreshing every 10 seconds.

### Pairing screen (before wizard completes)

```
  ╔══════════════════════════════════════════╗
  ║         Welcome to VibeCodePC            ║
  ╠══════════════════════════════════════════╣
  ║  Scan the QR code below to pair your     ║
  ║  device and begin setup.                 ║
  ╚══════════════════════════════════════════╝

  [QR code rendered in UTF-8 block characters]

  ──────────────────────────────────────────
  Device ID : a1b2c3d4-...
  Pair URL  : https://vibecodepc.com/pair/a1b2c3d4-...
  Local IP  : 192.168.1.42
  Web UI    : http://192.168.1.42/
  ──────────────────────────────────────────

  Or open a browser on the same network and go to:
  http://vibecodepc.local/
```

The QR code is rendered by `php artisan device:show-qr` using the `chillerlan/php-qrcode` library (text/UTF-8 output mode).

### Status screen (after wizard completes)

Once `App\Models\DeviceState::isPaired()` returns true, the display switches to a live status screen showing service states, the web UI URL, and VS Code URL.

### No HDMI connected?

The display service runs regardless. If no monitor is attached, the service just writes to a TTY that nothing is reading — harmless. Users can always find the device via `vibecodepc.local` or their router's DHCP table.

---

## Auto-Update Mechanism

### How it works

The update timer fires:
- **Daily at 2:00–4:00 AM** (randomised within the 2-hour window to prevent all devices hitting GitHub at once)
- **5 minutes after boot** (catches missed daily windows)

When triggered, `vibecodepc-update.sh` does:

```
1.  Acquire flock on /var/lock/vibecodepc-update.lock (prevents concurrent runs)
2.  Read current version from storage/app/firmware-version
3.  GET https://api.github.com/repos/vibecodepc/vibecodepc/releases/latest
4.  Compare tag_name — exit silently if already up to date
5.  Download device*.tar.gz tarball
6.  Extract to a temp directory
7.  php artisan down --retry=10
8.  Backup /opt/vibecodepc → /opt/vibecodepc-backup (keep last 2)
9.  rsync new files (preserves .env, device.json, database.sqlite, projects)
10. composer install --no-dev
11. php artisan migrate --force
12. php artisan optimize:clear && config:cache && route:cache && view:cache
13. Fix permissions
14. Write new version to firmware-version
15. systemctl restart vibecodepc.service && reload php8.4-fpm
16. php artisan up
```

### What is preserved across updates

`rsync` explicitly excludes these paths so user data and device identity survive:

| Path | Why preserved |
|------|--------------|
| `.env` | Contains `APP_KEY` and user-configured secrets |
| `storage/device.json` | Unique device identity (UUID, serial, manufactured_at) |
| `storage/app/firmware-version` | Overwritten at step 14 with the new version |
| `storage/app/projects/` | User project files |
| `database/database.sqlite` | All wizard progress, AI keys, tunnel config |
| `vendor/` | Rebuilt by Composer at step 10 |
| `node_modules/` | Not present on device (npm build output in `public/build/` is synced) |

### Offline behaviour

`curl` uses `--connect-timeout 10`. If the device has no internet, the script exits with a skip message (not an error), and the timer will try again on the next trigger.

### Checking update logs

```bash
sudo journalctl -u vibecodepc-update.service --no-pager
# or
sudo cat /var/log/vibecodepc/update.log
```

### Manual update

```bash
sudo vibecodepc update
```

---

## Manufacturing: Flashing Devices

### Setup

The flashing station is a Linux machine (x86 or arm64) with:
- The `.img.xz` image file from `os-image/deploy/`
- An NVMe SSD in a USB enclosure or M.2 slot
- `jq`, `uuidgen`, `dd`, `qrencode` installed

### Single device

```bash
sudo ./scripts/flash.sh \
    --image deploy/VibeCodePC-2026-01-15-arm64.img.xz \
    --device /dev/sda
```

The script will:
1. Show you what device `/dev/sda` is and ask you to type `FLASH` to confirm
2. Write the image with `dd` (progress shown)
3. Mount partition 2 (root filesystem)
4. Generate a UUID v4 and write `device.json` to `/opt/vibecodepc/storage/device.json`
5. Unmount
6. Verify first 256 MB matches original (SHA256 spot-check)
7. Print the QR code to the terminal
8. Print the device UUID and pairing URL

### Batch flashing (assembly line)

For flashing many units in sequence, use `--qr-output` to accumulate label data:

```bash
for DRIVE in /dev/sda /dev/sdb /dev/sdc /dev/sdd; do
    sudo ./scripts/flash.sh \
        --image deploy/VibeCodePC-2026-01-15-arm64.img.xz \
        --device "$DRIVE" \
        --qr-output labels.csv \
        --skip-verify   # skip verify for speed; spot-check 1-in-10 manually
done
```

`labels.csv` will contain one row per device:

```csv
a1b2c3d4-...,v1.0.0,2026-01-15T09:23:01Z,https://vibecodepc.com/pair/a1b2c3d4-...
b5c6d7e8-...,v1.0.0,2026-01-15T09:25:44Z,https://vibecodepc.com/pair/b5c6d7e8-...
```

Feed this CSV into your label printing workflow to generate QR stickers.

### Pre-registering devices in the cloud

Before shipping, each UUID must be registered in the cloud database so users can claim it. Import `labels.csv` via the cloud admin:

```bash
# cloud app
php artisan devices:import --csv labels.csv
```

### Dry run (test without writing)

```bash
./scripts/flash.sh \
    --image deploy/VibeCodePC-2026-01-15-arm64.img.xz \
    --device /dev/sda \
    --dry-run
```

Simulates the full process, generates a UUID, and prints the QR — but writes nothing.

### Options reference

| Flag | Description |
|------|-------------|
| `--image, -i` | Path to `.img` or `.img.xz` file (required) |
| `--device, -d` | Target block device, e.g. `/dev/sda` (required) |
| `--uuid, -u` | Use a pre-generated UUID (auto-generated if omitted) |
| `--version, -v` | Override firmware version string |
| `--qr-output` | Append label data to this CSV file |
| `--cloud-url` | Override pairing base URL (default: `https://vibecodepc.com`) |
| `--dry-run` | Simulate without writing |
| `--skip-verify` | Skip post-flash SHA256 spot-check |

---

## The `vibecodepc` CLI

Installed to `/usr/local/bin/vibecodepc`. Most commands require `sudo`.

```bash
sudo vibecodepc status           # System health + service states + disk/RAM/CPU
sudo vibecodepc tunnel start     # Start the Cloudflare tunnel
sudo vibecodepc tunnel stop      # Stop the tunnel
sudo vibecodepc tunnel status    # Show tunnel connection state
sudo vibecodepc update           # Check GitHub and apply firmware update now
sudo vibecodepc reset            # Factory reset (clears wizard, re-runs on next boot)
      vibecodepc logs            # Tail all vibecodepc-related service logs
      vibecodepc logs nginx      # Tail a specific service
sudo vibecodepc restart          # Restart all services (nginx, php-fpm, horizon)
      vibecodepc version         # Print firmware version
```

`reset` is interactive — you must type `RESET` to confirm. It calls `php artisan vibecodepc:reset`, clears the first-boot state file, and restarts the app. Projects and user files are untouched.

---

## Key Paths on the Device

| Path | Contents |
|------|---------|
| `/opt/vibecodepc/` | Laravel device app root |
| `/opt/vibecodepc/.env` | App environment (APP_KEY, AI keys encrypted, tunnel config) |
| `/opt/vibecodepc/storage/device.json` | Device identity: UUID, serial, manufactured_at, firmware_version |
| `/opt/vibecodepc/storage/app/firmware-version` | Current firmware version tag (e.g. `v1.2.0`) |
| `/opt/vibecodepc/storage/app/projects/` | User project files |
| `/opt/vibecodepc/database/database.sqlite` | All app data (wizard state, AI keys, tunnel routes, etc.) |
| `/var/lib/vibecodepc/first-boot-done` | Presence of this file prevents first-boot from re-running |
| `/var/log/vibecodepc/first-boot.log` | First-boot log |
| `/var/log/vibecodepc/update.log` | Update history log |
| `/opt/vibecodepc-backup/` | Snapshot of app before last update |
| `/opt/vibecodepc-backup.2/` | Snapshot before second-to-last update |

---

## Customising the Build

### Changing the default locale/timezone

Edit `os-image/config`:

```bash
LOCALE_DEFAULT=en_GB.UTF-8
TIMEZONE_DEFAULT=Europe/London
KEYBOARD_KEYMAP=gb
KEYBOARD_LAYOUT="English (UK)"
```

### Pinning software versions

In `01-install-software/00-run.sh`, the installers use `stable` or `latest`. To pin:

```bash
# Pin code-server version
curl -fsSL https://code-server.dev/install.sh | sh -s -- --version 4.23.1

# Pin Node.js major version
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -  # change 22 to desired
```

### Adding apt packages

Add a line to `stage-vibecodepc/00-packages/packages`. Comments (`#`) and blank lines are ignored.

### Changing the device app source

By default, stage `02-device-app` fetches from:
```
https://api.github.com/repos/vibecodepc/vibecodepc/releases/latest
```

For a fork or a local build, set `RELEASES_API` in `02-device-app/00-run.sh` or pass a pre-built tarball path.

---

## Troubleshooting

### Build fails in stage 01 (package not found)

The arm64 Valkey repo may lag behind. The script falls back to `redis-server` automatically. Check the pi-gen work log:

```bash
cat .pi-gen/work/stage-vibecodepc/01-install-software/00-run.sh.log
```

### Build fails in stage 02 (can't download device app)

The GitHub Releases asset name must match `device*.tar.gz`. If the release hasn't been published yet, set:

```bash
VIBECODEPC_APP_VERSION=v1.0.0 ./build.sh
```

Or build the tarball locally and serve it with `python3 -m http.server` during the build.

### Device doesn't appear at `vibecodepc.local`

Avahi (mDNS) requires the client to support mDNS resolution. On Linux hosts, ensure `libnss-mdns` is installed. On macOS, Bonjour handles this natively. On Windows, install Bonjour from Apple or enable the DNS-SD service.

Check Avahi is running on the device:
```bash
sudo systemctl status avahi-daemon
```

### First boot seems stuck

The first-boot service has a 300-second timeout. If the Pi has no internet, `device:generate-id` still works (it reads `/proc/cpuinfo` for the hardware serial). Only the update check requires internet; it's not part of first-boot.

Check the log:
```bash
sudo journalctl -u vibecodepc-first-boot.service --no-pager
sudo cat /var/log/vibecodepc/first-boot.log
```

### QR code not showing on HDMI

Check the display service:
```bash
sudo systemctl status vibecodepc-display.service
sudo journalctl -u vibecodepc-display.service -n 50
```

Verify that `getty@tty1` was disabled during image build:
```bash
systemctl status getty@tty1.service
# Should be "disabled/dead"
```

### Flash script refuses to write (system disk protection)

The script detects if the target device is the root disk. If you're flashing on a Pi itself (e.g. booted from SD, writing to an NVMe SSD), make sure `--device` points to the NVMe (`/dev/nvme0n1`), not the SD card (`/dev/mmcblk0`).

### Rolling back a bad update

The last two app snapshots are kept at `/opt/vibecodepc-backup/` and `/opt/vibecodepc-backup.2/`:

```bash
# Roll back to previous version
sudo systemctl stop vibecodepc.service nginx php8.4-fpm
sudo rsync -a /opt/vibecodepc-backup/ /opt/vibecodepc/
sudo systemctl start vibecodepc.service nginx php8.4-fpm
```

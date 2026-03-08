# VibeCodePC — Device App

The on-device Laravel application that powers the VibeCodePC Raspberry Pi 5. Runs the first-run setup wizard, dashboard, project manager, and tunnel/deploy manager.

## Prerequisites

- PHP 8.2+ with SQLite extension
- Composer
- Node.js & npm
- The `vibecodepc/common` package (at `../packages/vibecodepc-common/`)

## Quick Start

```bash
composer setup
php artisan serve
```

This installs dependencies, creates `.env`, generates the app key, runs migrations, generates a device identity, and builds frontend assets.

## Development

```bash
# Dev server with Vite HMR (Laravel + Vite in parallel)
composer run dev

# Run tests
php artisan test

# Run tests with coverage
php artisan test --coverage

# Lint / format
./vendor/bin/pint
```

## Artisan Commands

| Command | Description |
|---|---|
| `device:generate-id` | Generate a unique device UUID and write `storage/device.json` |
| `device:show-qr` | Display the QR code for device pairing |
| `device:poll-pairing` | Poll the cloud API for pairing status |

Options for `device:generate-id`:
- `--force` — Overwrite existing device identity
- `--path=<path>` — Custom output path (default from `VIBECODEPC_DEVICE_JSON` env var)

## Project Structure

```
app/
├── Console/Commands/     # device:generate-id, device:show-qr, device:poll-pairing
├── Livewire/
│   ├── Wizard/           # One component per setup wizard step
│   └── Dashboard/        # Dashboard panel components
├── Models/               # Eloquent models (User, AiProviderConfig, WizardProgress, …)
├── Services/
│   ├── AiProviders/      # OpenAI, Anthropic, OpenRouter, HuggingFace
│   ├── CodeServer/       # code-server lifecycle
│   ├── GitHub/           # GitHub OAuth
│   ├── Tunnel/           # Cloudflare tunnel management
│   └── DeviceRegistry/   # Device identity & QR pairing
└── Http/Controllers/
```

## Environment Variables

Key variables in `.env` (see `.env.example` for the full list):

| Variable | Default | Description |
|---|---|---|
| `VIBECODEPC_CLOUD_URL` | `https://vibecodepc.com` | Cloud edge URL |
| `VIBECODEPC_DEVICE_JSON` | `storage/device.json` | Path to device identity file |
| `CODE_SERVER_PORT` | `8443` | code-server port |
| `GITHUB_CLIENT_ID` | — | GitHub OAuth client ID |
| `CLOUDFLARED_CONFIG` | `/etc/cloudflared/config.yml` | Cloudflare tunnel config path |

> On the actual Raspberry Pi, `VIBECODEPC_DEVICE_JSON` defaults to `storage/device.json`.

## Device Modes

VibeCodePC supports two operational modes:

### Local-Only Mode (Unpaired)

When you skip the Cloudflare Tunnel setup during the first-run wizard, your device operates in **local-only mode**:

- **Access**: Dashboard and code-server are only available on your local network
- **URLs**: Access via `http://raspberrypi.local:8000` (dashboard) and `http://raspberrypi.local:8443` (code-server)
- **Features**: All core features work locally—project creation, code editing, and container management
- **Limitations**: No remote access from outside your network, no cloud dashboard features
- **Upgrade**: You can pair the device later from Settings → Cloudflare Tunnel

### Paired Mode (Remote Access)

When you complete the Cloudflare Tunnel setup, your device operates in **paired mode**:

- **Access**: Full remote access via the cloud dashboard at `https://vibecodepc.com`
- **Tunnel**: Secure Cloudflare tunnel provides encrypted access from anywhere
- **Features**: Remote code editing, project management, and device monitoring
- **Requirements**: Internet connection and Cloudflare account

### Switching Between Modes

| Action | How To |
|--------|--------|
| Skip pairing during setup | Click "Skip for now — use locally" in the tunnel setup step |
| Pair later | Go to Settings → Cloudflare Tunnel → "Set up remote access" |
| Check current mode | Dashboard shows "Device not paired" banner when in local-only mode |

## Backups

VibeCodePC includes an encrypted backup system for your device configuration. All backups are encrypted using Laravel's `Crypt` facade and stored as ZIP archives containing the encrypted payload.

### What Gets Backed Up

Backups include data from the following tables:

- `ai_providers` — AI provider API configurations
- `tunnel_configs` — Cloudflare tunnel settings
- `github_credentials` — GitHub OAuth credentials
- `device_state` — Device state and metadata
- `wizard_progress` — First-run wizard completion status
- `cloud_credentials` — Cloud API credentials

Additionally, your `.env` file is included in the backup (encrypted).

### Creating a Backup

**Via the Dashboard:**

1. Go to **Settings → System**
2. Click **"Download Backup"**
3. The backup will be downloaded as a ZIP file with a timestamped filename (e.g., `backup-2025-03-08-143022.zip`)

**Via Code:**

```php
use App\Services\BackupService;

$backupService = new BackupService;
$path = $backupService->createBackup();
// $path contains the full path to the created ZIP file
```

### Restoring a Backup

**Via the Dashboard:**

1. Go to **Settings → System**
2. Under **"Restore from Backup"**, select your backup ZIP file
3. Click **"Restore Backup"**
4. The device will restore all database tables and `.env` settings from the backup

**Via Code:**

```php
use App\Services\BackupService;

$backupService = new BackupService;
$backupService->restoreBackup('/path/to/backup-2025-03-08-143022.zip');
```

### Restore Procedures

When restoring a backup:

1. **Database tables are truncated** before restoration — existing data will be replaced
2. **Only configured tables are restored** — tables not in the backup list are left untouched
3. **The `.env` file is overwritten** — current environment variables will be replaced with backed-up values
4. **Some settings may require a restart** — tunnel configurations and environment variables may need a device restart to take effect

### Security

- All backup data is **encrypted** using Laravel's `Crypt` facade before being stored
- The encryption uses your app's `APP_KEY` — **you must use the same `APP_KEY`** to decrypt a backup
- If you lose your `APP_KEY`, backups cannot be restored
- Backup files have the `.zip` extension but the contents are encrypted

### Error Handling

Restore operations may fail with the following exceptions:

| Error | Cause |
|-------|-------|
| `Failed to open backup file.` | ZIP file is corrupted or inaccessible |
| `Invalid backup file — missing encrypted payload.` | ZIP is valid but missing the `backup.enc` file inside |
| `Invalid backup data structure.` | Decryption failed or backup data is malformed (possible `APP_KEY` mismatch) |

Always verify your backup file integrity before attempting restoration, especially after transferring between devices.

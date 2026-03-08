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

## Troubleshooting

### Device Identity Generation and QR Pairing

This section covers common issues with device identity generation (`device:generate-id`) and QR code pairing (`device:show-qr`).

#### Device Identity File Not Found

**Error:**
```
Device identity file not found at /path/to/device.json. Run: php artisan device:generate-id
```

**Cause:** The device identity file has not been created yet, or the path is misconfigured.

**Solutions:**
1. Generate the device identity:
   ```bash
   php artisan device:generate-id
   ```

2. If using a custom path, verify the `VIBECODEPC_DEVICE_JSON` environment variable:
   ```bash
   # Check current configuration
   php artisan tinker --execute="echo config('vibecodepc.device_json_path');"

   # Or check .env file
   grep VIBECODEPC_DEVICE_JSON .env
   ```

3. Ensure the storage directory is writable:
   ```bash
   ls -la storage/
   # Should show writable permissions (drwxrwxr-x or similar)
   ```

#### Device Identity Already Exists

**Error:**
```
Device identity already exists at /path/to/device.json. Use --force to overwrite.
```

**Cause:** A device.json file already exists and you're trying to generate a new one without the force flag.

**Solutions:**
1. If you want to keep the existing identity, no action needed.

2. If you need to regenerate (e.g., corrupted identity, fresh start):
   ```bash
   php artisan device:generate-id --force
   ```

   ⚠️ **Warning:** Using `--force` generates a new UUID, which means any existing cloud pairings will be invalidated. The device will need to be re-paired.

#### Cannot Create Directory for Device Identity

**Error:**
```
Cannot create directory: /path/to/nonexistent/directory
```

**Cause:** The parent directory doesn't exist and the process doesn't have permission to create it.

**Solutions:**
1. Create the directory manually:
   ```bash
   mkdir -p /path/to/parent/directory
   ```

2. Verify write permissions:
   ```bash
   # Check parent directory permissions
   ls -la /path/to/parent/

   # Fix permissions if needed (on Raspberry Pi)
   sudo chown -R vibecodepc:vibecodepc /path/to/storage/
   ```

3. Use the default path instead of a custom one:
   ```bash
   php artisan device:generate-id
   # Uses storage/device.json by default
   ```

#### QR Code Not Displaying in Terminal

**Error:** No QR code appears when running `device:show-qr`

**Cause:** The device identity exists but there may be issues with the terminal output or the QR code library.

**Solutions:**
1. First, verify the device identity exists:
   ```bash
   php artisan device:generate-id
   # or check the file directly
   cat storage/device.json
   ```

2. Check terminal compatibility:
   - The QR code requires a monospace font
   - Some terminals may not support the text-based QR output
   - Try a different terminal emulator (iTerm2, GNOME Terminal, etc.)

3. Verify the pairing URL manually:
   ```bash
   # The URL is displayed even if QR code rendering fails
   php artisan device:show-qr | grep "Pair URL"
   ```

4. Generate a QR code externally using the URL:
   ```bash
   # Extract just the URL
   php artisan device:show-qr 2>&1 | grep "Pair URL:" | awk '{print $3}'
   ```

#### Pairing URL Returns 404

**Error:** When scanning the QR code, the browser shows a 404 error or "Device not found"

**Cause:** The device has not been registered with the cloud service yet.

**Solutions:**
1. Poll for pairing status to register the device:
   ```bash
   php artisan device:poll-pairing
   ```

2. Verify the cloud URL configuration:
   ```bash
   # Check the configured cloud URL
   php artisan tinker --execute="echo config('vibecodepc.cloud_browser_url');"

   # Should output something like: https://vibecodepc.com
   ```

3. Ensure the device can reach the cloud API:
   ```bash
   curl -I https://vibecodepc.com/api/health
   # Should return HTTP 200
   ```

#### Hardware Serial Detection Issues

**Issue:** Hardware serial shows as "dev-XXXXXXXX" instead of actual Raspberry Pi serial

**Cause:** The device is not running on a Raspberry Pi, or `/proc/cpuinfo` is not accessible.

**Solutions:**
1. This is expected behavior on non-Pi development environments

2. On Raspberry Pi, ensure `/proc/cpuinfo` is readable:
   ```bash
   cat /proc/cpuinfo | grep Serial
   # Should show: Serial  : XXXXXXXXXXXXXXXX
   ```

3. The hardware serial is informational only and doesn't affect pairing

#### Cloud Pairing Poll Fails

**Error:**
```
Poll failed: [error message]
```

**Causes and Solutions:**

1. **Network connectivity issues:**
   ```bash
   # Test connectivity to cloud
   ping vibecodepc.com

   # Test API endpoint
   curl -v https://vibecodepc.com/api/devices/status
   ```

2. **Device not registered:**
   ```bash
   # Force re-registration by clearing cache
   php artisan cache:clear
   php artisan device:poll-pairing
   ```

3. **Cloud API errors:**
   - Check cloud service status
   - Review logs: `storage/logs/laravel.log`

4. **Already paired:**
   ```bash
   # Check pairing status
   php artisan tinker --execute="var_dump(App\Models\CloudCredential::current()?->isPaired());"
   ```

#### Device ID Changes After Restore

**Issue:** After restoring from backup, the device shows a different ID

**Cause:** The device.json file was not included in the backup, or was overwritten.

**Solutions:**
1. Check if device.json was backed up:
   ```bash
   # List contents of backup ZIP
   unzip -l /path/to/backup-file.zip | grep device.json
   ```

2. Restore device identity from backup or regenerate:
   ```bash
   php artisan device:generate-id --force
   # Then re-pair with cloud
   ```

3. To preserve device identity during manual backups:
   ```bash
   # Always include device.json in backups
   cp storage/device.json /backup/location/
   ```

#### Permission Denied on Device Commands

**Error:**
```
Permission denied: storage/device.json
```

**Cause:** File permissions are incorrect.

**Solutions:**
1. Fix ownership (on Raspberry Pi):
   ```bash
   sudo chown -R vibecodepc:vibecodepc /home/vibecodepc/device/
   sudo chmod -R 755 /home/vibecodepc/device/storage/
   ```

2. For development environments:
   ```bash
   chmod 644 storage/device.json
   ```

3. Check if running as correct user:
   ```bash
   whoami
   # Should match the user that owns the project files
   ```

#### Device Shows as "Not Paired" in Dashboard

**Issue:** Dashboard displays "Device not paired" banner even after pairing

**Causes and Solutions:**

1. **Pairing incomplete:**
   ```bash
   # Check if credentials were saved
   php artisan tinker --execute="var_dump(App\Models\CloudCredential::current());"
   ```

2. **Cache stale:**
   ```bash
   php artisan cache:clear
   php artisan view:clear
   ```

3. **Device identity mismatch:**
   - Verify the device ID in cloud matches local device.json
   - Compare the pairing URL's UUID with device.json ID

4. **Wizard state stuck:**
   ```bash
   # Check current mode
   php artisan tinker --execute="echo App\Models\DeviceState::getValue('device_mode');"

   # Force dashboard mode if needed
   php artisan tinker --execute="App\Models\DeviceState::setValue('device_mode', 'dashboard');"
   ```

#### Regenerating Device Identity (Nuclear Option)

If all else fails and you need to start fresh:

```bash
# 1. Backup existing identity (optional)
cp storage/device.json storage/device.json.backup.$(date +%Y%m%d_%H%M%S)

# 2. Clear any cloud credentials
php artisan tinker --execute="App\Models\CloudCredential::query()->delete();"

# 3. Clear cache
php artisan cache:clear

# 4. Generate new identity
php artisan device:generate-id --force

# 5. Display new QR code
php artisan device:show-qr

# 6. Poll for pairing
php artisan device:poll-pairing
```

⚠️ **Warning:** This will invalidate any existing pairings. The device will need to be paired again with your cloud account.

### Getting Help

If you encounter issues not covered here:

1. Check the logs: `storage/logs/laravel.log`
2. Verify environment: `php artisan about`
3. Test commands with verbose output:
   ```bash
   php artisan device:generate-id -vvv
   php artisan device:show-qr -vvv
   ```
4. Review test examples in `tests/Feature/Console/Commands/` for expected behavior

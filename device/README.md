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

This application uses environment variables for configuration. Copy `.env.example` to `.env` and customize as needed.

### Application Settings

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `VibeCodePC` | Application name displayed in UI |
| `APP_ENV` | `local` | Environment: `local`, `production`, `testing` |
| `APP_KEY` | — | 32-character encryption key (generate with `php artisan key:generate`) |
| `APP_DEBUG` | `true` | Enable debug mode (disable in production) |
| `APP_URL` | `http://vibecodepc.local` | Base URL for URL generation |
| `APP_LOCALE` | `en` | Default language locale |
| `APP_FALLBACK_LOCALE` | `en` | Fallback locale when translation missing |
| `APP_FAKER_LOCALE` | `en_US` | Locale for fake data generation |
| `APP_MAINTENANCE_DRIVER` | `file` | Maintenance mode driver: `file`, `cache` |
| `BCRYPT_ROUNDS` | `12` | Password hashing rounds |

### Database Configuration

| Variable | Default | Description |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | Database driver: `sqlite`, `mysql`, `mariadb`, `pgsql`, `sqlsrv` |
| `DB_DATABASE` | `database.sqlite` | Database name/path (SQLite: path, MySQL: name) |
| `DB_URL` | — | Database connection URL (overrides other settings) |
| `DB_FOREIGN_KEYS` | `true` | Enable foreign key constraints (SQLite) |

**MySQL/MariaDB specific:**
| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` / `3306` | Database port |
| `DB_USERNAME` | `root` | Database username |
| `DB_PASSWORD` | — | Database password |
| `DB_SOCKET` | — | Unix socket path |
| `DB_CHARSET` | `utf8mb4` | Character set |
| `DB_COLLATION` | `utf8mb4_unicode_ci` | Collation |
| `MYSQL_ATTR_SSL_CA` | — | SSL CA certificate path |

**PostgreSQL specific:**
| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `5432` | Database port |
| `DB_SSLMODE` | `prefer` | SSL mode: `disable`, `allow`, `prefer`, `require`, `verify-ca`, `verify-full` |

### Session Configuration

| Variable | Default | Description |
|---|---|---|
| `SESSION_DRIVER` | `database` | Session driver: `file`, `cookie`, `database`, `memcached`, `redis`, `dynamodb`, `array` |
| `SESSION_LIFETIME` | `120` | Session idle timeout in minutes (default: 2 hours) |
| `SESSION_ENCRYPT` | `false` | Encrypt session data |
| `SESSION_PATH` | `/` | Session cookie path |
| `SESSION_DOMAIN` | `null` | Session cookie domain |
| `SESSION_CONNECTION` | — | Database connection for session storage |
| `SESSION_TABLE` | `sessions` | Database table for sessions |
| `SESSION_STORE` | — | Cache store for session (affects `redis`, `memcached`, `dynamodb`) |
| `SESSION_COOKIE` | `{app}-session` | Session cookie name |
| `SESSION_SECURE_COOKIE` | — | HTTPS-only cookies |
| `SESSION_HTTP_ONLY` | `true` | HTTP-only cookies (prevents JS access) |
| `SESSION_SAME_SITE` | `lax` | SameSite cookie attribute: `lax`, `strict`, `none` |
| `SESSION_PARTITIONED_COOKIE` | `false` | Partitioned cookies for cross-site contexts |
| `SESSION_EXPIRE_ON_CLOSE` | `false` | Expire session when browser closes |

> **Note:** On the device, `SESSION_LIFETIME` is set to `10080` (1 week) since the device is always-on.

### Cache Configuration

| Variable | Default | Description |
|---|---|---|
| `CACHE_STORE` | `database` | Cache driver: `array`, `database`, `file`, `memcached`, `redis`, `dynamodb`, `octane`, `failover`, `null` |
| `CACHE_PREFIX` | `{app}-cache-` | Key prefix for cache entries |

**Database cache:**
| Variable | Default | Description |
|---|---|---|
| `DB_CACHE_CONNECTION` | — | Database connection for cache |
| `DB_CACHE_TABLE` | `cache` | Cache table name |
| `DB_CACHE_LOCK_CONNECTION` | — | Connection for cache locks |
| `DB_CACHE_LOCK_TABLE` | — | Table for cache locks |

**Memcached:**
| Variable | Default | Description |
|---|---|---|
| `MEMCACHED_PERSISTENT_ID` | — | Persistent connection ID |
| `MEMCACHED_USERNAME` | — | SASL username |
| `MEMCACHED_PASSWORD` | — | SASL password |
| `MEMCACHED_HOST` | `127.0.0.1` | Memcached host |
| `MEMCACHED_PORT` | `11211` | Memcached port |

**Redis cache:**
| Variable | Default | Description |
|---|---|---|
| `REDIS_CACHE_CONNECTION` | `cache` | Redis connection for cache |
| `REDIS_CACHE_LOCK_CONNECTION` | `default` | Redis connection for cache locks |

**DynamoDB cache:**
| Variable | Default | Description |
|---|---|---|
| `DYNAMODB_CACHE_TABLE` | `cache` | DynamoDB table name |
| `DYNAMODB_ENDPOINT` | — | Custom endpoint URL |

### Queue Configuration

| Variable | Default | Description |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | Queue driver: `sync`, `database`, `beanstalkd`, `sqs`, `redis`, `deferred`, `background`, `failover`, `null` |
| `QUEUE_FAILED_DRIVER` | `database-uuids` | Failed job storage: `database-uuids`, `dynamodb`, `file`, `null` |

**Database queue:**
| Variable | Default | Description |
|---|---|---|
| `DB_QUEUE_CONNECTION` | — | Database connection for queue |
| `DB_QUEUE_TABLE` | `jobs` | Jobs table name |
| `DB_QUEUE` | `default` | Default queue name |
| `DB_QUEUE_RETRY_AFTER` | `600` | Seconds before retrying failed jobs |

**Beanstalkd:**
| Variable | Default | Description |
|---|---|---|
| `BEANSTALKD_QUEUE_HOST` | `localhost` | Beanstalkd host |
| `BEANSTALKD_QUEUE` | `default` | Queue name |
| `BEANSTALKD_QUEUE_RETRY_AFTER` | `90` | Retry timeout in seconds |

**SQS:**
| Variable | Default | Description |
|---|---|---|
| `SQS_PREFIX` | `https://sqs.us-east-1...` | SQS queue URL prefix |
| `SQS_QUEUE` | `default` | Queue name |
| `SQS_SUFFIX` | — | Queue name suffix |

**Redis queue:**
| Variable | Default | Description |
|---|---|---|
| `REDIS_QUEUE_CONNECTION` | `default` | Redis connection |
| `REDIS_QUEUE` | `default` | Queue name |
| `REDIS_QUEUE_RETRY_AFTER` | `90` | Retry timeout in seconds |

### Redis Configuration

| Variable | Default | Description |
|---|---|---|
| `REDIS_CLIENT` | `phpredis` | Redis client: `phpredis`, `predis` |
| `REDIS_URL` | — | Redis connection URL |
| `REDIS_HOST` | `127.0.0.1` | Redis host |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | `null` | Redis password |
| `REDIS_USERNAME` | — | Redis username |
| `REDIS_DB` | `0` | Default database number |
| `REDIS_CACHE_DB` | `1` | Cache database number |
| `REDIS_CLUSTER` | `redis` | Cluster mode |
| `REDIS_PREFIX` | `{app}-database-` | Key prefix |
| `REDIS_PERSISTENT` | `false` | Persistent connections |
| `REDIS_MAX_RETRIES` | `3` | Max connection retries |
| `REDIS_BACKOFF_ALGORITHM` | `decorrelated_jitter` | Retry backoff algorithm |
| `REDIS_BACKOFF_BASE` | `100` | Backoff base delay (ms) |
| `REDIS_BACKOFF_CAP` | `1000` | Backoff cap (ms) |

### Logging Configuration

| Variable | Default | Description |
|---|---|---|
| `LOG_CHANNEL` | `stack` | Default log channel |
| `LOG_STACK` | `single` | Stack channels (comma-separated) |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Deprecation log channel |
| `LOG_LEVEL` | `debug` | Minimum log level: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency` |

### Filesystem Configuration

| Variable | Default | Description |
|---|---|---|
| `FILESYSTEM_DISK` | `local` | Default disk: `local`, `public`, `s3` |

**AWS S3:**
| Variable | Default | Description |
|---|---|---|
| `AWS_ACCESS_KEY_ID` | — | AWS access key |
| `AWS_SECRET_ACCESS_KEY` | — | AWS secret key |
| `AWS_DEFAULT_REGION` | `us-east-1` | AWS region |
| `AWS_BUCKET` | — | S3 bucket name |
| `AWS_URL` | — | Custom S3 URL |
| `AWS_ENDPOINT` | — | Custom S3 endpoint |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | Use path-style endpoints |

### VibeCodePC-Specific Settings

| Variable | Default | Description |
|---|---|---|
| `VIBECODEPC_CLOUD_URL` | `https://vibecodepc.com` | Cloud API base URL |
| `VIBECODEPC_CLOUD_BROWSER_URL` | `VIBECODEPC_CLOUD_URL` | Browser-facing cloud URL (for Docker dev) |
| `VIBECODEPC_DEVICE_JSON` | `storage/device.json` | Path to device identity file |
| `VIBECODEPC_PAIRING_REQUIRED` | `false` | Require cloud pairing to use device |
| `VIBECODEPC_PROJECTS_PATH` | `storage/app/projects` | Base path for projects |
| `VIBECODEPC_MAX_PROJECTS` | `10` | Maximum number of projects allowed |

### Code-Server Configuration

| Variable | Default | Description |
|---|---|---|
| `CODE_SERVER_PORT` | `8443` | code-server listening port |
| `CODE_SERVER_CONFIG` | `~/.config/code-server/config.yaml` | code-server config file path |
| `CODE_SERVER_SETTINGS` | `~/.local/share/code-server/User/settings.json` | VS Code: settings path |

### GitHub OAuth Configuration

| Variable | Default | Description |
|---|---|---|
| `GITHUB_CLIENT_ID` | — | GitHub OAuth app client ID |

### Cloudflare Tunnel Configuration

| Variable | Default | Description |
|---|---|---|
| `CLOUDFLARED_CONFIG` | `/etc/cloudflared/config.yml` | cloudflared config path |
| `DEVICE_APP_PORT` | `8081` | Port for device app tunnel |
| `TUNNEL_TOKEN_PATH` | `storage/tunnel/token` | Path to tunnel token file |
| `TUNNEL_ORIGIN_HOST` | — | Tunnel origin host override |

### Docker Configuration

| Variable | Default | Description |
|---|---|---|
| `DOCKER_HOST` | `unix:///var/run/docker.sock` | Docker daemon socket |
| `DOCKER_HOST_PROJECTS_PATH` | — | Host path for projects (Docker dev only) |

### Third-Party Services

**Postmark:**
| Variable | Default | Description |
|---|---|---|
| `POSTMARK_API_KEY` | — | Postmark API key |

**Resend:**
| Variable | Default | Description |
|---|---|---|
| `RESEND_API_KEY` | — | Resend API key |

**Slack:**
| Variable | Default | Description |
|---|---|---|
| `SLACK_BOT_USER_OAUTH_TOKEN` | — | Slack bot OAuth token |
| `SLACK_BOT_USER_DEFAULT_CHANNEL` | — | Default Slack channel |

### Broadcast Configuration

| Variable | Default | Description |
|---|---|---|
| `BROADCAST_CONNECTION` | `log` | Broadcast driver: `pusher`, `ably`, `redis`, `log`, `null` |

### Mail Configuration

See `config/mail.php` for mail configuration options. Common variables:

| Variable | Description |
|---|---|
| `MAIL_MAILER` | Mail driver: `smtp`, `sendmail`, `mailgun`, `postmark`, `ses`, `resend` |
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | SMTP port |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |
| `MAIL_ENCRYPTION` | Encryption: `tls`, `ssl`, `null` |
| `MAIL_FROM_ADDRESS` | Default from address |
| `MAIL_FROM_NAME` | Default from name |

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

### Factory Reset

This section covers the `device:factory-reset` command which erases all settings and returns the device to its initial state.

#### Factory Reset Safety Requirements

The factory reset command is **destructive and irreversible**. Before running it, understand these safety requirements:

**What Gets Deleted:**
- All projects and their data
- AI provider configurations
- GitHub OAuth credentials
- Tunnel configurations
- Project logs and activity history
- Wizard progress state

**What Is Preserved:**
- Device identity (UUID for cloud pairing)
- Cloud credentials (device remains paired if already paired)

**Safety Mechanisms:**
1. **Confirmation Prompt** — Without the `--force` flag, the command requires explicit confirmation:
   ```bash
   php artisan device:factory-reset
   # Prompts: "This will erase ALL data, projects, and settings. Continue?"
   ```

2. **Force Flag Bypass** — The `--force` flag skips confirmation (use with extreme caution):
   ```bash
   php artisan device:factory-reset --force
   ```

3. **Cancellation Support** — You can cancel during the confirmation prompt by answering "no".

4. **Progress Output** — The command shows each step to help you understand what's happening:
   ```
   Stopping tunnel...
   Clearing database...
   Resetting wizard...
   Factory reset complete. The setup wizard will appear on next visit.
   ```

**Pre-Reset Checklist:**

Before running a factory reset, ensure:

- [ ] **Create a backup** if you want to restore settings later:
  ```bash
  # Via Dashboard: Settings → System → Download Backup
  # Or via code if you have a custom backup solution
  ```

- [ ] **Export important project data** — Projects will be permanently deleted

- [ ] **Note your AI provider API keys** — You'll need to reconfigure them

- [ ] **Document GitHub OAuth settings** — Credentials will be cleared

- [ ] **Verify tunnel status** — The reset will stop any active tunnel

**Reset Scenarios:**

| Scenario | Command |
|----------|---------|
| Interactive reset (recommended) | `php artisan device:factory-reset` |
| Automated/scripted reset | `php artisan device:factory-reset --force` |
| After reset, device enters | Setup wizard mode |

**Post-Reset State:**

After a successful factory reset:
- Device is in wizard mode (setup required)
- Database tables are empty
- All configuration is cleared
- Device identity is preserved (re-pairing not required if already paired)
- On next dashboard visit, the setup wizard will appear

**Troubleshooting Factory Reset:**

**Error: Permission denied during truncation**

**Cause:** Database file permissions prevent table truncation.

**Solution:**
```bash
# Fix database permissions (on Raspberry Pi)
sudo chown -R vibecodepc:vibecodepc /home/vibecodepc/device/
sudo chmod 664 storage/database.sqlite

# For development environments
chmod 664 storage/database.sqlite
```

**Error: Foreign key constraint violation**

**Cause:** Tables are being truncated in an order that violates foreign key constraints.

**Solution:**
This is handled automatically — the command truncates tables in the correct order (ProjectLog before Project). If you encounter this error, ensure you're using the latest version of the command.

**Reset Interrupted Midway**

**Issue:** Reset process was interrupted (power loss, process killed).

**Solution:**
```bash
# Check current state
php artisan tinker --execute="echo App\Models\DeviceState::getValue('device_mode');"

# If stuck in partial state, complete reset manually:
php artisan device:factory-reset --force

# Or restart the device and let it reinitialize
```

**Wizard Not Appearing After Reset**

**Issue:** Device mode wasn't reset to wizard.

**Solution:**
```bash
# Force wizard mode manually
php artisan tinker --execute="App\Models\DeviceState::setValue('device_mode', 'wizard');"

# Clear caches
php artisan cache:clear
php artisan view:clear
```

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

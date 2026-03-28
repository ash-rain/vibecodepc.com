# VibeCodePC Troubleshooting Guide

A comprehensive guide for diagnosing and resolving common issues in the VibeCodePC device application.

## Table of Contents

- [Quick Diagnosis](#quick-diagnosis)
- [Installation Issues](#installation-issues)
- [Configuration Issues](#configuration-issues)
- [Tunnel Issues](#tunnel-issues)
- [Cloud API Issues](#cloud-api-issues)
- [Device Pairing Issues](#device-pairing-issues)
- [Code Server Issues](#code-server-issues)
- [Project/Docker Issues](#projectdocker-issues)
- [Backup/Restore Issues](#backuprestore-issues)
- [AI Provider Issues](#ai-provider-issues)
- [Performance Issues](#performance-issues)
- [Diagnostic Commands](#diagnostic-commands)

---

## Quick Diagnosis

When encountering an issue, start with these diagnostic commands:

```bash
# Check application status
php artisan about

# View recent logs
tail -50 storage/logs/laravel.log

# Check device health
php artisan device:health

# Verify environment
php artisan tinker --execute="echo config('app.env');"

# Check database connectivity
php artisan tinker --execute="echo DB::connection()->getPdo() ? 'OK' : 'Failed';"
```

---

## Installation Issues

### Composer Dependencies Fail to Install

**Symptoms:**
- `composer install` fails with dependency resolution errors
- Package conflicts with `vibecodepc/common`

**Solutions:**

1. Ensure the common package is available:
```bash
ls -la ../packages/vibecodepc-common/
```

2. Clear composer cache and reinstall:
```bash
composer clear-cache
rm -rf vendor composer.lock
composer install
```

3. Check PHP version compatibility:
```bash
php --version  # Must be >= 8.2
```

### NPM/Node Build Failures

**Symptoms:**
- `npm run build` fails
- Vite errors during development

**Solutions:**

1. Clear node_modules and reinstall:
```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

2. Check Node.js version:
```bash
node --version  # Must be compatible with project requirements
```

---

## Configuration Issues

### Application Key Not Set

**Error:**
```
No application encryption key has been specified.
```

**Solution:**
```bash
php artisan key:generate
```

### Database Connection Failed

**Error:**
```
could not find driver (Connection: sqlite, ...)
```

**Solutions:**

1. Install SQLite extension:
```bash
# Ubuntu/Debian
sudo apt-get install php8.2-sqlite3

# Verify
php -m | grep sqlite
```

2. Check database file permissions:
```bash
ls -la storage/database.sqlite
chmod 664 storage/database.sqlite
```

3. For MySQL/PostgreSQL, verify connection string in `.env`

### Session Configuration Errors

**Symptoms:**
- "Session store not set" errors
- Users logged out unexpectedly

**Solutions:**

1. For database driver:
```bash
php artisan session:table
php artisan migrate
```

2. Clear session cache:
```bash
php artisan cache:clear
php artisan session:clear
```

---

## Tunnel Issues

### Tunnel Not Starting

**Error:**
```
Tunnel is not configured. Complete the setup wizard to provision tunnel credentials.
```

**Solutions:**

1. Check if tunnel was skipped:
```bash
php artisan tinker --execute="var_dump(App\Models\TunnelConfig::current()?->isSkipped());"
```

2. Verify token file path and permissions:
```bash
ls -la storage/tunnel/
chmod 755 storage/tunnel
```

3. Check disk space:
```bash
df -h storage/tunnel/
```

### Token File Permission Denied

**Error:**
```
Failed to write tunnel token file: /tunnel/token
```

**Solutions:**

1. Fix directory permissions:
```bash
sudo chown -R $(whoami):$(whoami) storage/tunnel/
chmod 755 storage/tunnel/
```

2. On Raspberry Pi, ensure correct user:
```bash
sudo chown -R vibecodepc:vibecodepc /home/vibecodepc/device/storage/
```

### Tunnel Shows as Not Running

**Symptoms:**
- Dashboard shows "Tunnel: Not Running"
- Token file exists but is empty

**Solutions:**

1. Check token file content:
```bash
cat storage/tunnel/token | wc -c  # Should be > 0
```

2. Restart tunnel service:
```bash
php artisan tinker --execute="(new App\Services\Tunnel\TunnelService)->start();"
```

3. Check cloudflared container logs (if using Docker):
```bash
docker logs cloudflared
```

---

## Cloud API Issues

### Circuit Breaker Open

**Error:**
```
Circuit breaker is OPEN: too many failures
```

**Cause:**
Multiple consecutive failures to the cloud API triggered the circuit breaker.

**Solutions:**

1. Wait 1 minute for automatic recovery (half-open state)

2. Manually reset the circuit breaker:
```bash
php artisan tinker --execute="(new App\Services\CloudApiClient(config('vibecodepc.cloud_url')))->resetCircuitBreaker();"
```

3. Check cloud API connectivity:
```bash
curl -I https://vibecodepc.com/api/health
```

### Rate Limit Exceeded

**Error:**
```
429 Too Many Requests
```

**Solutions:**

1. Wait for rate limit window to reset (60 seconds)

2. Check current rate limit status:
```bash
curl -I http://localhost:8000/api/health
# Look for X-RateLimit-Remaining header
```

3. Reduce request frequency in your application logic

### SSL Certificate Errors

**Error:**
```
cURL error 60: SSL certificate problem
```

**Solution:**
This is expected in local development. Ensure `APP_ENV=local` in `.env` to disable SSL verification for Cloud API calls.

---

## Device Pairing Issues

### Device Identity File Not Found

**Error:**
```
Device identity file not found at /path/to/device.json. Run: php artisan device:generate-id
```

**Solutions:**

1. Generate device identity:
```bash
php artisan device:generate-id
```

2. Verify path configuration:
```bash
grep VIBECODEPC_DEVICE_JSON .env
php artisan tinker --execute="echo config('vibecodepc.device_json_path');"
```

3. Check storage directory is writable:
```bash
ls -ld storage/
chmod 755 storage/
```

### QR Code Not Displaying

**Symptoms:**
- No QR code in terminal output
- Pairing URL not shown

**Solutions:**

1. Verify device identity exists:
```bash
cat storage/device.json
```

2. Check terminal compatibility:
- Requires monospace font
- Some terminals may not support text-based QR output
- Try a different terminal (iTerm2, GNOME Terminal)

3. Get pairing URL manually:
```bash
php artisan device:show-qr 2>&1 | grep "Pair URL:" | awk '{print $3}'
```

### Pairing URL Returns 404

**Error:**
- Browser shows "Device not found"
- Cloud dashboard shows 404

**Solutions:**

1. Register device with cloud:
```bash
php artisan device:poll-pairing
```

2. Verify cloud URL configuration:
```bash
php artisan tinker --execute="echo config('vibecodepc.cloud_browser_url');"
```

3. Check internet connectivity:
```bash
curl -I https://vibecodepc.com/api/health
```

### Device ID Changes After Restore

**Issue:**
Device shows different UUID after backup restoration

**Solutions:**

1. Check if device.json was backed up:
```bash
unzip -l /path/to/backup-file.zip | grep device.json
```

2. Restore device identity or regenerate:
```bash
# Restore from backup if available
cp /backup/device.json storage/device.json

# Or regenerate (requires re-pairing)
php artisan device:generate-id --force
php artisan device:show-qr
php artisan device:poll-pairing
```

---

## Code Server Issues

### Code Server Not Starting

**Error:**
```
Failed to start code-server: [error details]
```

**Solutions:**

1. Check if code-server is installed:
```bash
code-server --version
```

2. Check port availability:
```bash
lsof -iTCP:8443 -sTCP:LISTEN
```

3. Review code-server logs:
```bash
tail -50 /tmp/code-server.log
tail -50 ~/.local/share/code-server/logs/
```

4. Try starting manually:
```bash
code-server --auth none --bind-addr 127.0.0.1:8443
```

### Port Already in Use

**Error:**
```
Port 8443 is already in use
```

**Solutions:**

1. Find and kill existing process:
```bash
lsof -iTCP:8443 -sTCP:LISTEN -t | xargs kill -9
```

2. Use a different port in `.env`:
```bash
CODE_SERVER_PORT=8444
```

### Extension Installation Fails

**Symptoms:**
- Extensions don't appear in code-server
- "Failed to install extension" errors

**Solutions:**

1. Check extension marketplace connectivity:
```bash
curl -I https://open-vsx.org/
```

2. Install extensions manually:
```bash
code-server --install-extension publisher.extension-name
```

3. Check code-server version compatibility:
```bash
code-server --version
```

---

## Project/Docker Issues

### Docker Not Available

**Error:**
```
Docker is not running or not installed
```

**Solutions:**

1. Check Docker installation:
```bash
docker --version
docker-compose --version
```

2. Start Docker service:
```bash
# Linux
sudo systemctl start docker

# macOS
open -a Docker
```

3. Check Docker socket permissions:
```bash
ls -la /var/run/docker.sock
sudo usermod -aG docker $USER  # Requires logout/login
```

### Project Container Fails to Start

**Error:**
```
Failed to start container (no output)
```

**Solutions:**

1. Check Docker logs:
```bash
docker logs <container-id>
```

2. Verify project configuration:
```bash
cat storage/app/projects/<project>/docker-compose.yml
```

3. Check disk space:
```bash
docker system df
df -h
```

4. Clean up Docker resources:
```bash
docker system prune -a
```

### Port Conflicts

**Error:**
```
Bind for 0.0.0.0:XXXX failed: port is already allocated
```

**Solutions:**

1. Find conflicting container:
```bash
docker ps --filter "publish=XXXX"
```

2. Stop conflicting container:
```bash
docker stop <container-id>
```

3. Use auto-assigned ports in project settings

---

## Backup/Restore Issues

### Backup Creation Fails

**Error:**
```
Failed to create backup: [error details]
```

**Solutions:**

1. Check storage permissions:
```bash
ls -ld storage/app/private/
chmod -R 755 storage/app/private/
```

2. Verify encryption key is set:
```bash
grep APP_KEY .env
```

3. Check available disk space:
```bash
df -h storage/app/
```

### Restore Fails

**Error:**
```
Invalid backup file — missing encrypted payload.
```

**Solutions:**

1. Verify backup file integrity:
```bash
unzip -t /path/to/backup-file.zip
```

2. Check APP_KEY matches backup:
```bash
# Must use same APP_KEY that created the backup
grep APP_KEY .env
```

3. Ensure backup file is readable:
```bash
ls -la /path/to/backup-file.zip
```

### Backup Integrity Check Failed

**Error:**
```
Backup file integrity check failed. The file may be corrupted or tampered with.
```

**Cause:**
- File corruption during transfer
- Tampered file
- Wrong APP_KEY

**Solutions:**

1. Use original backup file (not transferred copy)
2. Verify file checksum:
```bash
sha256sum backup-file.zip
```
3. Restore from a different backup if available

---

## AI Provider Issues

### API Key Validation Fails

**Symptoms:**
- "Invalid API key" errors
- Test connection fails

**Solutions:**

1. Verify key format:
- OpenAI: `sk-...`
- Anthropic: `sk-ant-...`
- HuggingFace: `hf_...`

2. Check key permissions:
```bash
# Test with curl
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer YOUR_API_KEY"
```

3. Verify provider configuration:
```bash
php artisan tinker --execute="var_dump(App\Models\AiProviderConfig::current());"
```

### OpenCode Configuration Issues

**Symptoms:**
- Configuration not loading
- Provider not found errors

**Solutions:**

1. Validate JSON syntax:
```bash
python3 -m json.tool ~/.config/opencode/opencode.json > /dev/null
python3 -m json.tool ~/.local/share/opencode/auth.json > /dev/null
```

2. Check file permissions:
```bash
chmod 600 ~/.local/share/opencode/auth.json
chmod 644 ~/.config/opencode/opencode.json
```

3. Verify provider status:
```bash
opencode --version
```

4. See [OPENCODE_CONFIGURATION.md](./OPENCODE_CONFIGURATION.md) for detailed troubleshooting

---

## Performance Issues

### High CPU Usage

**Symptoms:**
- Dashboard shows red CPU indicator (>85%)
- System is slow to respond

**Solutions:**

1. Check running projects:
```bash
docker ps --format "table {{.Names}}\t{{.Status}}"
php artisan tinker --execute="echo App\Models\Project::where('status', 'running')->count();"
```

2. Stop unused projects via dashboard or:
```bash
php artisan tinker --execute="(new App\Services\Docker\ProjectContainerService)->stop(App\Models\Project::find(<id>));"
```

3. Check for runaway processes:
```bash
top -bn1 | head -20
```

### High Memory Usage

**Symptoms:**
- Dashboard shows red RAM indicator (>85%)
- Out of memory errors

**Solutions:**

1. Check Docker memory usage:
```bash
docker stats --no-stream
```

2. Stop inactive projects

3. Check for memory leaks:
```bash
ps aux --sort=-%mem | head -10
```

4. Consider adding swap space if not present:
```bash
free -h  # Check swap
```

### High Disk Usage

**Symptoms:**
- Dashboard shows red disk indicator (>90%)
- "Insufficient disk space" errors

**Solutions:**

1. Clean up Docker:
```bash
docker system prune -a --volumes
```

2. Clear logs:
```bash
php artisan log:clear
# Or manually
truncate -s 0 storage/logs/laravel.log
```

3. Check project sizes:
```bash
du -sh storage/app/projects/* | sort -hr
```

4. Remove old backups:
```bash
ls -lt storage/app/private/backup-*.zip | tail -n +10 | xargs rm -f
```

### High Temperature (Raspberry Pi)

**Symptoms:**
- Dashboard shows red temperature indicator (>75°C)
- Throttling warnings

**Solutions:**

1. Check temperature directly:
```bash
cat /sys/class/thermal/thermal_zone0/temp  # Output in millidegrees
```

2. Improve ventilation:
- Add heatsinks
- Use fan case
- Ensure air circulation

3. Reduce CPU load by stopping unnecessary projects

4. Check for thermal throttling:
```bash
cat /sys/class/thermal/thermal_zone0/trip_point_0_temp
```

---

## Diagnostic Commands

### System Information

```bash
# Application status
php artisan about

# Environment details
php artisan tinker --execute="print_r(config('app'));"

# Database status
php artisan db:monitor

# Cache status
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Health Check

```bash
# Full health report
php artisan device:health

# JSON output for scripting
php artisan device:health --json

# API endpoint
curl http://localhost:8000/api/health | jq
```

### Logs

```bash
# Recent application logs
tail -100 storage/logs/laravel.log

# Real-time log streaming
tail -f storage/logs/laravel.log

# Export logs
php artisan logs:export --path=/tmp/logs-export.zip
```

### Configuration Debug

```bash
# Verify environment variables
php artisan tinker --execute="echo env('VAR_NAME');"

# Check config values
php artisan tinker --execute="echo config('vibecodepc.cloud_url');"

# List all models
php artisan tinker --execute="print_r(get_declared_classes());" | grep "App\\\\Models"
```

### Database Queries

```bash
# Check table status
php artisan tinker --execute="print_r(DB::select('SELECT name FROM sqlite_master WHERE type=\"table\"'));"

# Count records
php artisan tinker --execute="echo App\Models\User::count();"

# Check migrations
php artisan migrate:status
```

---

## Getting Help

If issues persist after trying the above solutions:

1. **Check the logs:**
   ```bash
   tail -200 storage/logs/laravel.log
   ```

2. **Run with verbose output:**
   ```bash
   php artisan <command> -vvv
   ```

3. **Verify system requirements:**
   - PHP >= 8.2
   - Composer
   - Node.js & npm
   - SQLite (or configured database)
   - Docker (for projects)

4. **Review test output:**
   ```bash
   php artisan test --filter=<related-test>
   ```

5. **Check related documentation:**
   - [API Documentation](./API.md)
   - [Error Handling Patterns](./SERVICES_ERROR_HANDLING.md)
   - [OpenCode Configuration](./OPENCODE_CONFIGURATION.md)
   - [README.md](../README.md)

---

**Last Updated:** 2026-03-16

For updates or corrections to this guide, refer to the service implementations in `app/Services/` and their corresponding test files.

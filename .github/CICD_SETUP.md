# GitHub Actions CI/CD Setup

This repository uses GitHub Actions for automated testing and deployment.

## Workflows

### 🧪 CI Workflow (`.github/workflows/ci.yml`)

Runs automatically on:
- Push to `main` or `develop` branches
- Pull requests targeting `main` or `develop`

**What it does:**
- **Tests**: Runs PHPUnit tests across PHP 8.2, 8.3, and 8.4 with 80% minimum coverage
- **Code Style**: Validates PSR-12 compliance using Laravel Pint
- **Security Audit**: Checks for known vulnerabilities in dependencies
- **Asset Build**: Ensures frontend assets compile successfully

### 🚀 Deploy Workflow (`.github/workflows/deploy.yml`)

Runs on:
- Manual trigger (workflow_dispatch)
- Push to `main` branch (only when `cloud/` files change)

**What it does:**
- Connects to your VPS via SSH
- Pulls latest code
- Installs dependencies (Composer & NPM)
- Runs migrations
- Builds and caches config
- Restarts services
- Performs health check

## Setup Instructions

### 1. Configure Repository Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions, then add:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `SSH_PRIVATE_KEY` | SSH private key for server access | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `SERVER_HOST` | Your VPS hostname/IP | `vibecodepc.com` or `192.168.1.100` |
| `SERVER_USER` | SSH username | `deploy` or `root` |
| `DEPLOY_PATH` | Path to Laravel installation | `/var/www/vibecodepc.com` |

### 2. Generate SSH Key Pair

On your local machine:

```bash
# Generate new SSH key pair
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_deploy

# Copy public key to server
ssh-copy-id -i ~/.ssh/github_deploy.pub user@your-server.com

# Copy private key content for GitHub secret
cat ~/.ssh/github_deploy
```

### 3. Prepare Your Server

Ensure your VPS has:

```bash
# Required software
- PHP 8.4 (with required extensions)
- Composer
- Node.js 20+ & npm
- Git
- Nginx/Apache
- MySQL/PostgreSQL or SQLite

# Git repository setup
cd /var/www/vibecodepc.com
git init
git remote add origin https://github.com/your-username/vibecodepc.com.git

# Permissions
sudo chown -R deploy:www-data /var/www/vibecodepc.com
sudo chmod -R 755 /var/www/vibecodepc.com/cloud/storage
sudo chmod -R 755 /var/www/vibecodepc.com/cloud/bootstrap/cache
```

### 4. Create Production Environment

Create `.env` file on server:

```bash
cd /var/www/vibecodepc.com/cloud
cp .env.example .env
php artisan key:generate
```

Edit `.env` with production values (database, mail, etc.).

### 5. Optional: Set Up GitHub Environment

For additional protection, create a `production` environment:

1. Go to Settings → Environments → New environment
2. Name it `production`
3. Add protection rules:
   - Required reviewers
   - Wait timer
   - Deployment branches (only `main`)

## Usage

### Running Tests Locally

```bash
cd cloud
php artisan test --coverage
./vendor/bin/pint --test
composer audit
```

### Manual Deployment

Go to Actions → Deploy to Production → Run workflow

### Monitoring CI/CD

- Check the Actions tab in your repository
- Failed jobs will show red ❌
- Successful jobs will show green ✅
- Click on any job for detailed logs

## Troubleshooting

### Tests Failing on CI

```bash
# Run locally first
cd cloud
php artisan test --coverage --min=80
```

### Pint Errors

```bash
# Auto-fix code style issues
cd cloud
./vendor/bin/pint
git add .
git commit -m "Fix code style"
```

### Deployment Fails

```bash
# SSH into server manually
ssh user@your-server.com
cd /var/www/vibecodepc.com/cloud

# Check logs
tail -f storage/logs/laravel.log

# Run deployment commands manually
php artisan down
git pull
composer install
php artisan migrate --force
php artisan config:cache
php artisan up
```

### SSH Connection Issues

Verify SSH key is correctly added:

```bash
# Test SSH connection
ssh -i ~/.ssh/github_deploy user@your-server.com

# Check authorized_keys on server
cat ~/.ssh/authorized_keys
```

## Future Enhancements

Consider adding:
- [ ] Automated backups before deployment
- [ ] Slack/Discord notifications
- [ ] Rollback mechanism
- [ ] Database seeding for staging
- [ ] Static analysis (PHPStan/Larastan)
- [ ] Browser testing (Laravel Dusk)
- [ ] Performance testing
- [ ] Staging environment deployment

## Support

For issues with GitHub Actions, check:
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Laravel Deployment Documentation](https://laravel.com/docs/12.x/deployment)
- Repository Issues tab

#!/usr/bin/env bash
set -euo pipefail

# VibeCodePC Docker Setup
# Runs the Laravel-specific parts of device setup inside the container.
# System services (Docker, code-server, cloudflared) are skipped in containers.

APP_DIR="/var/www/html"

info()  { echo -e "\033[1;34m[vibecodepc]\033[0m $*"; }
ok()    { echo -e "\033[1;32m[vibecodepc]\033[0m $*"; }

cd "$APP_DIR"

# Environment
if [[ ! -f .env ]]; then
    info "Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate --no-interaction
    ok ".env created and app key generated"
fi

# Database
DB_FILE="database/database.sqlite"
if [[ ! -f "$DB_FILE" ]]; then
    info "Creating SQLite database..."
    touch "$DB_FILE"
fi

info "Running migrations..."
php artisan migrate --force --no-interaction
ok "Migrations complete"

# Device identity
info "Ensuring device identity..."
php artisan device:generate-id --no-interaction 2>/dev/null || true
ok "Device identity ready"

# Composer dependencies (if vendor is missing)
if [[ ! -d vendor/autoload.php ]] && [[ -f composer.json ]]; then
    info "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

ok "VibeCodePC device setup complete!"

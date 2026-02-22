#!/usr/bin/env bash
set -euo pipefail

# VibeCodePC Cloud Docker Setup
# Runs Laravel-specific setup inside the cloud container at startup.

APP_DIR="/var/www/html"

info()  { echo -e "\033[1;34m[vibecodepc-cloud]\033[0m $*"; }
ok()    { echo -e "\033[1;32m[vibecodepc-cloud]\033[0m $*"; }

cd "$APP_DIR"

# Environment
if [[ ! -f .env ]]; then
    info "Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate --no-interaction
    ok ".env created and app key generated"
fi

# Wait for PostgreSQL to be ready
if [[ "${DB_CONNECTION:-}" == "pgsql" ]]; then
    info "Waiting for PostgreSQL..."
    for i in $(seq 1 30); do
        if php -r "new PDO('pgsql:host=${DB_HOST:-postgres};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-vibecodepc_cloud}', '${DB_USERNAME:-vibecodepc}', '${DB_PASSWORD:-vibecodepc}');" 2>/dev/null; then
            ok "PostgreSQL is ready"
            break
        fi
        if [[ $i -eq 30 ]]; then
            info "PostgreSQL not ready after 30s, continuing anyway..."
        fi
        sleep 1
    done
fi

info "Running migrations..."
php artisan migrate --force --no-interaction
ok "Migrations complete"

# Composer dependencies (if vendor is missing due to bind-mount)
if [[ ! -f vendor/autoload.php ]] && [[ -f composer.json ]]; then
    info "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

ok "VibeCodePC cloud setup complete!"

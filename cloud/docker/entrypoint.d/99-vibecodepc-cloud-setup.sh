#!/bin/sh
set -eu

# VibeCodePC Cloud Docker Setup
# Runs Laravel-specific setup inside the cloud container at startup.
# NOTE: serversideup/php v4 sources entrypoint scripts with `sh`, so this
# must use POSIX-compatible syntax only (no [[ ]], no bashisms).

APP_DIR="/var/www/html"

info()  { printf '\033[1;34m[vibecodepc-cloud]\033[0m %s\n' "$*"; }
ok()    { printf '\033[1;32m[vibecodepc-cloud]\033[0m %s\n' "$*"; }

cd "$APP_DIR"

# Environment
if [ ! -f .env ]; then
    info "Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate --no-interaction
    ok ".env created and app key generated"
fi

# Wait for PostgreSQL to be ready
if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
    info "Waiting for PostgreSQL..."
    i=1
    while [ "$i" -le 30 ]; do
        if php -r "new PDO('pgsql:host=${DB_HOST:-postgres};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-vibecodepc_cloud}', '${DB_USERNAME:-vibecodepc}', '${DB_PASSWORD:-vibecodepc}');" 2>/dev/null; then
            ok "PostgreSQL is ready"
            break
        fi
        if [ "$i" -eq 30 ]; then
            info "PostgreSQL not ready after 30s, continuing anyway..."
        fi
        i=$((i + 1))
        sleep 1
    done
fi

info "Running migrations..."
php artisan migrate --force --no-interaction
ok "Migrations complete"

info "Seeding database..."
php artisan db:seed --force --no-interaction
ok "Seeding complete"

# Composer dependencies (if vendor is missing due to bind-mount)
if [ ! -f vendor/autoload.php ] && [ -f composer.json ]; then
    info "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

ok "VibeCodePC cloud setup complete!"

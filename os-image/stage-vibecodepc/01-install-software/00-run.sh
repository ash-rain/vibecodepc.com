#!/bin/bash -e
# Stage: Install software (runs in chroot)
# Installs: PHP 8.4, Composer, Node.js 22, npm, pnpm, cloudflared, code-server, Docker

on_chroot << 'EOF'
set -euo pipefail

info()  { echo "[stage-vibecodepc/01] $*"; }

# ---------- PHP 8.4 ----------

info "Installing PHP 8.4..."
curl -fsSL https://packages.sury.org/php/apt.gpg \
    -o /etc/apt/trusted.gpg.d/php-sury.gpg
echo "deb https://packages.sury.org/php/ bookworm main" \
    > /etc/apt/sources.list.d/php-sury.list
apt-get update -qq
apt-get install -y -qq \
    php8.4 \
    php8.4-cli \
    php8.4-fpm \
    php8.4-sqlite3 \
    php8.4-redis \
    php8.4-curl \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    php8.4-intl \
    php8.4-bcmath \
    php8.4-gd \
    php8.4-opcache

# Tune OPcache for Pi 5 (keep JIT off to save RAM)
cat > /etc/php/8.4/cli/conf.d/99-vibecodepc.ini << 'PHPINI'
opcache.enable=1
opcache.memory_consumption=64
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4096
opcache.validate_timestamps=0
PHPINI

# ---------- Composer ----------

info "Installing Composer..."
curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version

# ---------- Node.js 22 LTS ----------

info "Installing Node.js 22 LTS..."
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y -qq nodejs
node --version
npm --version

# pnpm
npm install -g pnpm
pnpm --version

# ---------- Docker ----------

info "Installing Docker..."
curl -fsSL https://download.docker.com/linux/debian/gpg \
    -o /etc/apt/trusted.gpg.d/docker.gpg
echo "deb [arch=arm64] https://download.docker.com/linux/debian bookworm stable" \
    > /etc/apt/sources.list.d/docker.list
apt-get update -qq
apt-get install -y -qq \
    docker-ce \
    docker-ce-cli \
    containerd.io \
    docker-buildx-plugin \
    docker-compose-plugin
systemctl enable docker
usermod -aG docker vibecodepc

# ---------- cloudflared ----------

info "Installing cloudflared..."
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg \
    -o /etc/apt/trusted.gpg.d/cloudflare.gpg
echo "deb [arch=arm64] https://pkg.cloudflare.com/cloudflared bookworm main" \
    > /etc/apt/sources.list.d/cloudflared.list
apt-get update -qq
apt-get install -y -qq cloudflared

# ---------- code-server ----------

info "Installing code-server..."
export HOME=/root
curl -fsSL https://code-server.dev/install.sh | sh --version stable
code-server --version

# ---------- Valkey (Redis-compatible) ----------

info "Installing Valkey..."
curl -fsSL https://packages.redis.io/gpg \
    -o /etc/apt/trusted.gpg.d/valkey.gpg
echo "deb [arch=arm64] https://packages.redis.io/deb bookworm main" \
    > /etc/apt/sources.list.d/valkey.list
apt-get update -qq
# If Valkey is not yet in the repo, fall back to redis-server (already installed via packages list)
apt-get install -y -qq valkey 2>/dev/null || true
systemctl enable redis-server || systemctl enable valkey || true

info "Software installation complete."
EOF

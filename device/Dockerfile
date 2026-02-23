FROM serversideup/php:8.4-fpm-nginx

# Switch to root to run setup
USER root

# Install PHP extensions not included in base image
RUN install-php-extensions bcmath gd

# Install Docker CLI + Compose plugin (NOT the daemon — we talk to host via socket)
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian bookworm stable" \
       > /etc/apt/sources.list.d/docker.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends docker-ce-cli docker-compose-plugin \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy app and shared packages
COPY device/ /var/www/html/
COPY packages/ /var/www/packages/

# Run device setup (skip Docker, systemd, npm — we're inside a container)
RUN VIBECODEPC_DOCKER_BUILD=1 bash bin/setup

# Clear config cache — runtime env vars (from docker-compose) must take precedence
RUN rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php

# Hand back to the default serversideup entrypoint
USER www-data

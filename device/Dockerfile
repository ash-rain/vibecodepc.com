FROM serversideup/php:8.4-fpm-nginx

# Switch to root to run setup
USER root

WORKDIR /var/www/html

# Create device config directory
RUN mkdir -p /etc/vibecodepc

# Copy app and shared packages
COPY device/ /var/www/html/
COPY packages/ /var/www/packages/

# Run device setup (skip Docker, systemd, npm — we're inside a container)
RUN VIBECODEPC_DOCKER_BUILD=1 bash bin/setup

# Hand back to the default serversideup entrypoint
USER www-data

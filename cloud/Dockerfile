FROM serversideup/php:8.4-fpm-nginx

USER root

# Install PHP extensions not included in base image
RUN install-php-extensions bcmath gd intl pdo_pgsql

WORKDIR /var/www/html

COPY cloud/ /var/www/html/
COPY packages/ /var/www/packages/

# Entrypoint scripts must be at /etc/entrypoint.d/ for serversideup/php v4
COPY cloud/docker/entrypoint.d/ /etc/entrypoint.d/
RUN chmod +x /etc/entrypoint.d/*.sh

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Clear config cache — runtime env vars (from docker-compose) must take precedence
RUN rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php

# Pre-create tunnel token directory so the named volume inherits www-data ownership
RUN mkdir -p /tunnel && chown www-data:www-data /tunnel

USER www-data

# PHP 8.3 image used by the app, queue and scheduler containers.
# The project source is bind-mounted at runtime (see docker-compose.yml), so this
# image only carries the PHP runtime, extensions and Composer — not the code.
FROM php:8.3-cli

ARG UID=1000
ARG GID=1000

# System libraries + PHP extensions.
#   pdo_mysql        -> MySQL
#   gd, exif         -> image handling (event images)
#   zip, intl, bcmath, pcntl -> framework / queue worker signals
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip default-mysql-client \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libicu-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath exif pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Debian's "default-mysql-client" is the MariaDB client, which verifies the
# server's TLS cert by default. The MySQL server presents a self-signed cert, so
# `php artisan db` / the mysql CLI fail with a "self-signed certificate" TLS
# error. Skip client-side SSL for this local-dev stack.
RUN printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/disable-ssl.cnf

# Composer (from the official Composer image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js + npm (same Debian bookworm base as php:8.3-cli, so the binaries are
# compatible). The Vite container runs from this image because the Wayfinder
# plugin shells out to `php artisan` during the dev-server startup.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Run as a non-root user whose UID/GID match the host, so files written into the
# bind-mounted project (vendor/, storage/, etc.) stay owned by the host user.
RUN (getent group ${GID} || groupadd -g ${GID} app) \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash app

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER app

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
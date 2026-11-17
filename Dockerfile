# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — build: PHP deps + front-end assets.
# The Wayfinder Vite plugin runs `php artisan wayfinder:generate` during
# `npm run build`, so this stage needs PHP, vendor/ and Node together.
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-cli AS build

USER root

# Node 22 (glibc build, matches the Debian base image)
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && install-php-extensions bcmath

WORKDIR /app

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules

# ---------------------------------------------------------------------------
# Stage 2 — runtime: nginx + php-fpm (listens on 8080)
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx

USER root
RUN install-php-extensions bcmath

# serversideup "autorun": migrate --force, storage:link, config/route/view/event cache
ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1 \
    LOG_CHANNEL=stderr

# Runs after the autorun migrations: idempotent, insert-only seeders
# (roles/permissions, the 3 assignment products, demo products, demo users).
COPY --chmod=755 docker/entrypoint.d/60-seed.sh /etc/entrypoint.d/60-seed.sh

COPY --from=build --chown=www-data:www-data /app /var/www/html

USER www-data

EXPOSE 8080

# Multi-stage build for Laravel on Render
# Stage 1: Node build
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# Stage 2: PHP runtime
FROM serversideup/php:8.4-fpm-nginx

USER root

# Install system dependencies
RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    redis

# Copy application files
COPY --from=node-builder /app /var/www/html

# Set working directory
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Switch to www-data user
USER www-data

# Expose port 8080 (Render default)
EXPOSE 8080

# Start server (Render sets PORT env var)
CMD ["sh", "-c", "php artisan migrate --force --no-interaction && php artisan config:cache && php artisan route:cache && php artisan view:cache && /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf"]
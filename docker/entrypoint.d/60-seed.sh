#!/bin/sh
# Runs on every container start, after serversideup's autorun migrations.
# All seeders are idempotent and insert-only, so restarts never reset data.
set -e

echo "🌱 Seeding roles, permissions, products and demo users (insert-only)..."
php /var/www/html/artisan db:seed --force --no-interaction

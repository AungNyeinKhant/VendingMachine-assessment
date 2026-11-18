#!/bin/sh
# Runs on every container start, after serversideup's autorun migrations.
set -e

# All seeders are idempotent and insert-only, so restarts never reset data.
echo "🌱 Seeding roles, permissions, products and demo users (insert-only)..."
php /var/www/html/artisan db:seed --force --no-interaction

# The committed API docs were generated against http://localhost:8000. Re-render
# them from the committed .scribe/ YAML with this environment's APP_URL
# (no route extraction, no database access).
echo "📘 Rendering API docs for ${APP_URL:-APP_URL}..."
php /var/www/html/artisan scribe:generate --no-extraction --no-interaction
php /var/www/html/artisan view:clear --no-interaction

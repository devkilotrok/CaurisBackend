#!/usr/bin/env bash
set -euo pipefail

# Render exposes $PORT (dynamic). Apache must listen on it.
PORT="${PORT:-8080}"

echo "Starting Laravel (Apache) on port ${PORT}..."

# Update Apache listen port
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost \\*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ensure required directories exist
mkdir -p /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# If APP_KEY is missing, Laravel will crash on requests; we don't auto-generate it
# because keys must be stable across deploys. Render should set APP_KEY env var.
if [ -z "${APP_KEY:-}" ] && [ ! -f /var/www/html/.env ]; then
  echo "WARNING: APP_KEY is not set. Set it in Render environment variables."
fi

# (Optional) Warm caches if env is present; ignore failures during first boot.
if [ -f /var/www/html/.env ] || [ -n "${APP_KEY:-}" ]; then
  php /var/www/html/artisan config:cache || true
  php /var/www/html/artisan route:cache || true
  php /var/www/html/artisan view:cache || true
fi

exec apache2-foreground


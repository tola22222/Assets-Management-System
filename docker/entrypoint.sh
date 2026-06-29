#!/bin/sh
set -e

# Seed shared public volume with image's original public assets (for nginx to serve)
if [ -d /var/www/public_original ] && [ ! -f /var/www/public/.seeded ]; then
    cp -rn /var/www/public_original/. /var/www/public/ 2>/dev/null || true
    touch /var/www/public/.seeded
fi

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

exec docker-php-entrypoint "$@"

#!/bin/sh
set -e

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

if [ -n "$DB_HOST" ]; then
  echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
  for i in $(seq 1 30); do
    if mysqladmin ping -h "$DB_HOST" -P "${DB_PORT:-3306}" --silent; then
      echo "Database is up."
      break
    fi
    if [ "$i" = "30" ]; then
      echo "Database not reachable after 30 attempts, starting anyway."
    fi
    sleep 2
  done
fi

exec docker-php-entrypoint "$@"
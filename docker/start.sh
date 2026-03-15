#!/bin/bash
set -e

# Parse DATABASE_URL → DB_* vars (Fly Postgres attach 시 자동 주입)
if [ -n "$DATABASE_URL" ]; then
  export DB_HOST=$(echo "$DATABASE_URL" | sed -e 's/^postgres:\/\/[^:]*:[^@]*@//' -e 's/:.*//' -e 's/\/.*//')
  export DB_PORT=$(echo "$DATABASE_URL" | sed -e 's/.*:\([0-9]*\)\/.*/\1/')
  export DB_DATABASE=$(echo "$DATABASE_URL" | sed -e 's/.*\///')
  export DB_USERNAME=$(echo "$DATABASE_URL" | sed -e 's/postgres:\/\/\([^:]*\):.*/\1/')
  export DB_PASSWORD=$(echo "$DATABASE_URL" | sed -e 's/postgres:\/\/[^:]*:\([^@]*\)@.*/\1/')
  echo "==> DATABASE_URL parsed: host=$DB_HOST db=$DB_DATABASE user=$DB_USERNAME"
fi

echo "==> Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

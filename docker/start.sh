#!/bin/bash
set -e

# Parse DATABASE_URL → DB_* vars (Fly Postgres attach 시 자동 주입)
# PHP parse_url() 사용 → ?sslmode=disable 등 query string 안전하게 제거
if [ -n "$DATABASE_URL" ]; then
  export DB_HOST=$(php -r "echo parse_url(getenv('DATABASE_URL'), PHP_URL_HOST);")
  export DB_PORT=$(php -r "echo parse_url(getenv('DATABASE_URL'), PHP_URL_PORT) ?: '5432';")
  export DB_DATABASE=$(php -r "echo ltrim(parse_url(getenv('DATABASE_URL'), PHP_URL_PATH), '/');")
  export DB_USERNAME=$(php -r "echo parse_url(getenv('DATABASE_URL'), PHP_URL_USER);")
  export DB_PASSWORD=$(php -r "echo parse_url(getenv('DATABASE_URL'), PHP_URL_PASS);")
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

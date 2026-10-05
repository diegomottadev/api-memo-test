#!/bin/sh
# Prepares the API every time the container starts. Every step can run many
# times without breaking anything, so `docker compose up` is all a new user needs.
set -e

cd /var/www/api-memo-test

as_app_user() {
  su-exec www-data "$@"
}

echo "[memo-api] 1/6 Environment file"
if [ ! -f .env ]; then
  cp .env.example .env
  chown www-data:www-data .env
fi

echo "[memo-api] 2/6 Folder permissions"
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
# Only folders: changing the mode of tracked files (the .gitignore files in
# these folders) would show them as modified in git.
find storage bootstrap/cache -type d -exec chmod ug+rwx {} +
mkdir -p "$COMPOSER_HOME" && chown -R www-data:www-data "$COMPOSER_HOME"

echo "[memo-api] 3/6 PHP dependencies"
if [ ! -f vendor/autoload.php ]; then
  as_app_user composer install --no-interaction --prefer-dist --no-progress
fi

echo "[memo-api] 4/6 Application key"
if ! grep -q '^APP_KEY=base64:' .env; then
  as_app_user php artisan key:generate --force
fi

echo "[memo-api] 5/6 Waiting for MySQL at ${DB_HOST}:${DB_PORT}"
# PHP/PDO is the same driver Laravel uses. (Alpine's mysql-client is the MariaDB
# client, which rejects the self-signed TLS certificate of MySQL 8.)
db_ready() {
  php -r 'try { new PDO(sprintf("mysql:host=%s;port=%s", getenv("DB_HOST"), getenv("DB_PORT")), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'
}
tries=0
until db_ready; do
  tries=$((tries + 1))
  if [ "$tries" -ge 60 ]; then
    echo "[memo-api] MySQL did not answer after 2 minutes." >&2
    exit 1
  fi
  sleep 2
done

echo "[memo-api] 6/6 Database tables and sample data"
as_app_user php artisan config:clear
as_app_user php artisan migrate --force
as_app_user php artisan db:seed --force

echo "[memo-api] Ready: http://localhost:${APP_PORT:-82}/graphql"
exec "$@"

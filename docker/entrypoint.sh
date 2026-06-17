#!/bin/sh
# Production entrypoint for the Revoco app container.
#
# Runs on every `app` container start (not queue/scheduler — those exec directly).
# Order:
#   1. Fail fast if APP_KEY is missing (never generate in prod — invalidates sessions).
#   2. Populate the app_public shared volume with the baked public/ directory.
#   3. Ensure storage directories and SQLite file exist with correct ownership.
#   4. Run migrations (--force required in production environment).
#   5. Warm Laravel caches (config, routes, views).
#   6. exec the container command (php-fpm by default).

set -e

# ---------------------------------------------------------------------------
# 1. Fail fast on missing APP_KEY
# ---------------------------------------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY is not set. Refusing to start." >&2
    echo "       Generate a key with: php artisan key:generate --show" >&2
    echo "       Then set APP_KEY=<value> in your environment / .env file." >&2
    echo "       Never auto-generate APP_KEY in production — it invalidates all sessions." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# 2. Populate the app_public shared volume.
#
#    The prod image bakes the built public/ directory into /var/www/html/public
#    but the app_public Named Volume is mounted at the same path, hiding the
#    image layer. To make nginx (which mounts the same volume read-only) see
#    the files, we copy them from the baked staging directory (_public_src).
#
#    The Dockerfile copies public/ to /var/www/html/_public_src before the
#    volume-mount path is created, so the staging directory is always available.
# ---------------------------------------------------------------------------
PUBLIC_SRC=/var/www/html/_public_src
PUBLIC_DST=/var/www/html/public

if [ -d "${PUBLIC_SRC}" ]; then
    # Sync baked public/ files into the shared volume (overwrite on upgrade).
    cp -a "${PUBLIC_SRC}/." "${PUBLIC_DST}/"
fi

# ---------------------------------------------------------------------------
# 3. Ensure storage directories and SQLite file
# ---------------------------------------------------------------------------
# The app_storage volume is mounted at /var/www/html/storage.
# Laravel expects storage/{app,framework/{cache,sessions,testing,views},logs}.
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/storage/database

# Create the SQLite file if it does not exist yet.
touch /var/www/html/storage/database/database.sqlite

# Fix permissions (www-data owns everything inside storage).
chown -R www-data:www-data /var/www/html/storage

# ---------------------------------------------------------------------------
# 4. Run database migrations (app service only; queue/scheduler skip this).
# ---------------------------------------------------------------------------
php artisan migrate --force --no-interaction

# ---------------------------------------------------------------------------
# 5. Warm Laravel caches
# ---------------------------------------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# 6. Hand off to the container command (php-fpm by default).
# ---------------------------------------------------------------------------
exec "$@"

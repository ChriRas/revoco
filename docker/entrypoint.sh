#!/bin/sh
# Production entrypoint for the Revoco app container.
#
# Runs on every `app` container start (not queue/scheduler — those exec directly).
# Order:
#   1. Fail fast if APP_KEY is missing (never generate in prod — invalidates sessions).
#   2. Populate the app_public shared volume with the baked public/ directory.
#   3. Ensure storage directories and SQLite file exist.
#   4. Run migrations as root (--force required in production environment).
#   5. chown storage/ + bootstrap/cache/ to www-data (AFTER migrate, so DB file is included).
#   6. Warm Laravel caches (config, routes, views).
#   7. exec the container command (php-fpm); its workers drop to www-data via Pool config.

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
    # Wipe then sync: ensure the volume mirrors the image exactly.
    # Without the wipe, renamed/removed Vite content-hashed chunks from a prior
    # deploy accumulate in the persistent volume and are never cleaned up.
    # Guard against an unset or empty PUBLIC_DST before the destructive delete.
    if [ -z "${PUBLIC_DST}" ]; then
        echo "ERROR: PUBLIC_DST is not set. Refusing to wipe." >&2
        exit 1
    fi
    find "${PUBLIC_DST}" -mindepth 1 -delete
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

# ---------------------------------------------------------------------------
# 4. Run database migrations (app service only; queue/scheduler skip this).
# ---------------------------------------------------------------------------
# Runs as root. The SQLite file (and containing directory) may be root-owned
# after creation above; we chown AFTER migrate so the final DB file is
# www-data-owned before php-fpm takes over.
php artisan migrate --force --no-interaction

# ---------------------------------------------------------------------------
# 5. Fix ownership AFTER migrate (migrate may create or modify the DB file).
# ---------------------------------------------------------------------------
# storage/  — framework dirs, logs, DB file and directory.
# bootstrap/cache/ — config/route/view cache files (written next).
# Both must be writable by www-data after the privilege drop below.
chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# ---------------------------------------------------------------------------
# 6. Warm Laravel caches (still running as root — files land in bootstrap/cache,
#    which is now www-data-owned so php-fpm can overwrite them at runtime).
# ---------------------------------------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# 7. Hand off to the container command (php-fpm by default).
#
#    php-fpm's master process must remain root so it can spawn and manage
#    worker children. The worker pool itself drops to www-data via the
#    `user = www-data / group = www-data` directives in www.conf (the default
#    for the official php-fpm Alpine image). Passing php-fpm through su-exec
#    would prevent it from opening its error_log fd and break FPM initialization.
#    The privilege drop is therefore php-fpm-internal, not entrypoint-level.
# ---------------------------------------------------------------------------
exec "$@"

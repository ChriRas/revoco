#!/bin/sh
# Minimal entrypoint for queue/scheduler containers.
#
# Only checks that APP_KEY is set (same fail-fast rule as the app entrypoint),
# ensures storage directories exist and are www-data-owned (root-only step),
# then drops to www-data via su-exec before exec-ing the container command.
# Migrations and cache warming are handled by the `app` container on startup.

set -e

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY is not set. Refusing to start." >&2
    exit 1
fi

# Ensure storage directories exist and are writable by www-data.
# The queue worker writes processed job results, logs, and may write to the DB.
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/storage/database

chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# Drop from root to www-data before exec-ing the queue/scheduler command.
exec su-exec www-data "$@"

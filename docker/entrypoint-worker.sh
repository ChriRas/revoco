#!/bin/sh
# Minimal entrypoint for queue/scheduler containers.
#
# Only checks that APP_KEY is set (same fail-fast rule as the app entrypoint)
# then execs the container command directly. Migrations and cache warming are
# handled by the `app` container on startup — workers just need storage access.

set -e

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY is not set. Refusing to start." >&2
    exit 1
fi

exec "$@"

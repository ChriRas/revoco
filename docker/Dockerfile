# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# base — shared PHP 8.5 runtime (php-fpm) on Alpine. No Composer, no Node, no
# tests. This is the foundation of the production image: small and lean.
# opcache, pdo_sqlite and sqlite3 are already built into the official php image,
# so only the missing extensions are compiled here.
# php:8.5-fpm-alpine tracks the current Alpine release (3.24.x as of 2026-06).
# ---------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
        icu-libs \
        icu-data-full \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        linux-headers \
    && docker-php-ext-install \
        bcmath \
        intl \
        pcntl \
        zip \
    && apk del .build-deps

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# with-composer — base + Composer + git/unzip. Shared by `dev` and `build`.
# Deliberately NOT inherited by `prod`, which must stay Composer-free.
# ---------------------------------------------------------------------------
FROM base AS with-composer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip
ENV COMPOSER_HOME=/tmp/composer

# ---------------------------------------------------------------------------
# dev — Composer toolchain image (no Node — that lives in the `node` service).
# Application code is bind-mounted at runtime; this image also runs the tests.
# ---------------------------------------------------------------------------
FROM with-composer AS dev

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# build — install production PHP dependencies (no dev deps, optimized autoloader).
# Source is copied in (vendor/, node_modules/, tests/ excluded via .dockerignore).
# ---------------------------------------------------------------------------
FROM with-composer AS build

# composer install runs post-autoload scripts (artisan package:discover). That runs
# key-free here by design — no .env is copied in (only .env.example ships).
COPY . .
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --prefer-dist

# ---------------------------------------------------------------------------
# assets — build front-end assets with Vite on Node 24 (Active LTS).
# ---------------------------------------------------------------------------
FROM node:24-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# prod — runtime image. FROM base => no Composer, no Node, no dev deps, no tests.
# Carries only application code, the optimized --no-dev vendor, and built assets.
# The entrypoint script handles storage perms, migrations, and cache warming on
# every container start (running as root), then drops to www-data via su-exec
# before exec-ing the container command (php-fpm). It also fails fast if APP_KEY
# is missing.
# ---------------------------------------------------------------------------
FROM base AS prod

# su-exec: lightweight setuid helper used to drop from root to www-data in the
# entrypoint after root-only setup steps (mkdir, chown, migrate, cp) are done.
RUN apk add --no-cache su-exec

COPY . .
COPY --from=build /var/www/html/vendor ./vendor
COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

# Install the prod entrypoints and make them executable.
# entrypoint.sh      — full bootstrap for the `app` service (perms, migrate, cache).
# entrypoint-worker.sh — minimal bootstrap for queue/scheduler (APP_KEY check only).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/entrypoint-worker.sh /usr/local/bin/entrypoint-worker.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/entrypoint-worker.sh

# Bake the entire public/ directory (incl. built assets) into a staging area
# (_public_src) that is NOT hidden by the app_public volume mount (which mounts
# at public/). The entrypoint syncs _public_src → public/ on every container
# start, keeping the volume in sync with the image after upgrades.
COPY --from=assets /app/public/build ./public/build
RUN cp -a ./public /var/www/html/_public_src

# Create the volume-mount target and storage directories; set ownership.
# The app_storage volume mounts at storage/; app_public at public/.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        storage/database \
    && chown -R www-data:www-data storage _public_src public

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

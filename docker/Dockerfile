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
# ---------------------------------------------------------------------------
FROM base AS prod

COPY . .
COPY --from=build /var/www/html/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

CMD ["php-fpm"]

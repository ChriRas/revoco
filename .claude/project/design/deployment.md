# Deployment (generic)

> This public repo ships the app as generic, env-driven Docker containers. Operator-specific deployment (real domains, reverse proxy/TLS, CI runner, secrets, branding overlay) lives in a **separate private infra repository** — never here.

## What this repo provides

### Dockerfile (`docker/Dockerfile`)
Multi-stage build: `base → with-composer → dev` (toolchain) and `build`/`assets → prod` (runtime).
- `base` — PHP 8.5-fpm-alpine + extensions (bcmath, intl, pcntl, zip, pdo_sqlite).
- `dev` — adds Composer + git/unzip; app code is bind-mounted at runtime.
- `build` — Composer `--no-dev --optimize-autoloader` install.
- `assets` — Node 24-alpine; builds Vite front-end assets.
- `prod` — FROM base; copies app + no-dev vendor + baked assets; no Composer/Node/tests.
  - Built assets are baked into `public/_build_src` (staging dir) to avoid being overridden by the `app_public` volume mount at `public/build`.
  - Includes `entrypoint.sh` (full bootstrap for `app`) and `entrypoint-worker.sh` (minimal, for `queue`/`scheduler`).

### Compose structure (decided slice-006)
- `docker-compose.yml` — generic **prod** base:
  - `web` — nginx 1.30-alpine; serves `app_public` volume (read-only) + proxies PHP to `app:9000`.
  - `app` — prod image; full entrypoint (perms → migrate → caches → php-fpm).
  - `queue` — prod image; minimal entrypoint → `php artisan queue:work --tries=3`.
  - `scheduler` — prod image; minimal entrypoint → `php artisan schedule:work`.
  - `ntfy` — optional profile (`--profile ntfy`); self-hosted ntfy for push.
  - Named volumes: `app_storage` (SQLite + storage/), `app_public` (built assets shared with nginx), `ntfy_data`.
- `docker-compose.override.yml` — **dev** deltas (auto-merged when no `-f` flag):
  - Swaps `app` to `target: dev` image + source bind-mount; resets entrypoint to php-fpm.
  - Overrides `web` with full source bind-mount.
  - Puts `queue` / `scheduler` behind `--profile tools` (not started in dev).
  - Adds profiled `node` service (Vite).

**Dev flow:** `docker compose up` (or `task up`) auto-merges the override → dev stack. Taskfile uses plain `docker compose` (no `-f`).
**Prod flow:** `docker compose -f docker-compose.yml up -d`.

### Prod entrypoint behavior (`docker/entrypoint.sh`)
Runs on every `app` container start:
1. **Fail fast if `APP_KEY` is missing** — exits non-zero; never calls `key:generate`.
2. Ensures `storage/{app,framework/{cache,sessions,testing,views},logs,database}` dirs exist and are `www-data`-owned.
3. Creates `storage/database/database.sqlite` if not present.
4. Syncs baked assets from `public/_build_src` → `public/build` (shared `app_public` volume) so nginx always gets the current image's assets.
5. `php artisan migrate --force --no-interaction`.
6. `php artisan config:cache` + `route:cache` + `view:cache`.
7. `exec "$@"` → php-fpm.

Worker entrypoint (`docker/entrypoint-worker.sh`): APP_KEY check only, then exec.

### Static serving without a bind-mount
Assets are baked into the image at `public/_build_src`. On container start, the entrypoint syncs them into the `app_public` named volume (mounted at `public/build`). nginx mounts the same volume read-only and serves all requests from `/var/www/html/public` (including `/build/...`). No bind-mount needed in prod.

### Smoke test (`task smoke`)
Locally: `task smoke` builds the prod image, boots the generic stack with a throwaway APP_KEY, polls for HTTP 200 on `/`, tears down. Reused in the release CI workflow.

### CI platform: GitHub Actions + ghcr.io (decided slice-006)
- **`.github/workflows/ci.yml`** — test gate on PR + push to `main`: Pint `--test` · PHPStan max · Pest.
- **`.github/workflows/release.yml`** — on `v*` SemVer tag: build prod image → smoke → push to `ghcr.io/<owner>/revoco` (SemVer + `latest`). Uses `docker/build-push-action` v7 + buildx GHA cache.
- Actions versions (verified 2026-06): `actions/checkout@v6`, `docker/setup-buildx-action@v4`, `docker/build-push-action@v7`, `docker/login-action@v4`.
- Registry owner derived dynamically via `${{ github.repository_owner }}` — no hardcoding.

## Configuration (per operator, via `.env`)
- App: `APP_KEY` (required, fail-fast), `APP_URL`, `APP_TIMEZONE`, locale.
- Branding: `APP_THEME` (neutral default) + logo/copy/links.
- Mail: direct SMTP credentials (operator's mail server); sender address.
- Push: `NTFY_ENABLED`, `NTFY_SERVER`, `NTFY_TOPIC`, optional token.
- Queue: `QUEUE_CONNECTION=database` (default; uses SQLite on `app_storage` volume).
- SQLite path inside container: `/var/www/html/storage/database/database.sqlite`.

## Out of scope here (→ private infra repo)
- Real domains, DNS, reverse proxy (TLS), and any access restriction (e.g. VPN-only staging).
- CI deploy workflows bound to a specific runner/host, environments, server paths.
- The shared ntfy server instance.
- The brand overlay assets (logo, theme, copy) for a specific operator.

## Safety invariant
- Non-production environments must never send real mail/push (`MAIL_MAILER=log`, push off).

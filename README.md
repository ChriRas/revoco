<p align="center">
  <img src="docs/revoco-logo.svg" alt="Revoco" width="280">
</p>

<p align="center">
  A self-hosted, single-merchant electronic withdrawal form implementing the<br>
  § 356a BGB statutory right of withdrawal (mandatory from 2026-06-19).<br>
  Neutral by default, configurable per <code>.env</code>, open-source (AGPL-3.0).
</p>

---

## Requirements

- Docker (Engine 25+) and Docker Compose (v2.24+)
- [Task](https://taskfile.dev) (go-task) — optional, but used for all
  convenience commands

---

## Development

```bash
# First run — build image, install deps, build assets, generate key, migrate
task init

# Start the dev stack (http://localhost:8580)
task up

# Run the quality gate (Pint + PHPStan + Pest)
task check

# Start the Vite dev server (HMR) — separate terminal
task dev
```

The dev stack uses a source bind-mount so every local edit is reflected immediately.
`task up` auto-merges `docker-compose.override.yml` (the dev overlay) with the base
`docker-compose.yml`.

---

## Production deployment

### 1. Generate an application key (once, before first boot)

```bash
docker run --rm ghcr.io/chriras/revoco:latest \
    php artisan key:generate --show
```

`ghcr.io/chriras/revoco` is the official published image. If you fork and build
your own, substitute your (lowercase) GHCR owner.

Copy the output (`base64:...`) — this becomes `APP_KEY` in your environment.
**Never** let the container generate its own key on boot; see the entrypoint.

### 2. Prepare your environment file

```bash
cp .env.example .env
# Edit .env: set APP_KEY, APP_URL, MAIL_*, OPERATOR_EMAIL, OPERATOR_PASSWORD, …
```

### 3. Boot the prod stack

```bash
docker compose -f docker-compose.yml up -d
```

The `app` container:
1. Fails fast if `APP_KEY` is missing (exits non-zero — never auto-generates).
2. Creates storage directories and the SQLite file on the persistent volume.
3. Runs `migrate --force`.
4. Warms config/route/view caches.
5. Starts `php-fpm`.

nginx (`web`) serves static files from the `app_public` shared volume and
proxies PHP requests to `app:9000`.

### 4. Provision the operator account (once)

```bash
docker compose -f docker-compose.yml exec app \
    php artisan app:operator --email=admin@example.com --password=changeme
```

Or set `OPERATOR_EMAIL` / `OPERATOR_PASSWORD` in `.env` and run `task operator`.

### 5. Optional: ntfy push notifications

```bash
# Start with the bundled ntfy service
docker compose -f docker-compose.yml --profile ntfy up -d
```

Configure `NTFY_ENABLED=true`, `NTFY_SERVER`, `NTFY_TOPIC` (and optionally
`NTFY_TOKEN`) in `.env`.

---

## Smoke test

Build the prod image and run a local end-to-end smoke test (HTTP 200 on `/`):

```bash
task smoke
```

Runs locally and in the release CI workflow.

---

## Configuration reference

Deployment configuration is via environment variables. See [`.env.example`](.env.example)
for the full list with inline documentation. Content-level settings — the legal pages
(imprint & privacy), consumer languages, withdrawal scope, and the withdrawal-notification
recipient — are managed by the operator in the Filament panel and persist in the database,
so changing them needs no redeploy. Key environment variables:

| Variable | Required | Default | Notes |
|---|---|---|---|
| `APP_KEY` | Yes | — | Generate with `key:generate --show`. Fail-fast if missing. |
| `APP_URL` | Yes | `http://localhost` | Full public URL incl. scheme. |
| `APP_TIMEZONE` | No | `UTC` | Consumer-local time for withdrawal timestamps. |
| `APP_THEME` | No | `neutral` | Visual theme token set (`data-theme`). |
| `DB_DATABASE` | No | `/var/www/html/storage/database/database.sqlite` | SQLite path inside container. |
| `QUEUE_CONNECTION` | No | `database` | Use `database` for the bundled SQLite queue. |
| `MAIL_MAILER` | No | `log` (safe) | Set to `smtp` in prod and configure `MAIL_*`. |
| `MERCHANT_NOTIFICATION_EMAIL` | No | — | Fallback recipient for the operator notification. The in-panel setting takes precedence; if neither is set, the imprint e-mail is used. |
| `NTFY_ENABLED` | No | `false` | Opt-in ntfy push (no PII sent). |

---

## CI / Release

GitHub Actions are configured in [`.github/workflows/`](.github/workflows/):

- **`ci.yml`** — runs `composer audit` (security) + `Pint --test` + PHPStan (max)
  + Pest on every PR and push to `main`.
- **`release.yml`** — on a `v*` SemVer tag: builds the prod image, runs the
  smoke test, and pushes to `ghcr.io/chriras/revoco` (SemVer + `latest`). A fork
  publishes under its own lowercased owner.
- **`security-audit.yml`** — scheduled `composer audit` (~every 2 days) that opens
  an issue if a locked dependency has a known advisory. Dependabot
  (`.github/dependabot.yml`) complements it with weekly update PRs.

---

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).

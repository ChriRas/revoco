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

All configuration is via environment variables. See [`.env.example`](.env.example)
for the full list with inline documentation. Key variables:

| Variable | Required | Default | Notes |
|---|---|---|---|
| `APP_KEY` | Yes | — | Generate with `key:generate --show`. Fail-fast if missing. |
| `APP_URL` | Yes | `http://localhost` | Full public URL incl. scheme. |
| `APP_TIMEZONE` | No | `UTC` | Consumer-local time for withdrawal timestamps. |
| `APP_THEME` | No | `neutral` | Visual theme token set (`data-theme`). |
| `DB_DATABASE` | No | `/var/www/html/storage/database/database.sqlite` | SQLite path inside container. |
| `QUEUE_CONNECTION` | No | `database` | Use `database` for the bundled SQLite queue. |
| `MAIL_MAILER` | No | `log` (safe) | Set to `smtp` in prod and configure `MAIL_*`. |
| `NTFY_ENABLED` | No | `false` | Opt-in ntfy push (no PII sent). |

---

## Branding the form

The form is neutral by default. Its whole appearance is driven by one `--wf-*` CSS
token contract, switched via `data-theme` (`APP_THEME`). Concrete brand overlays —
real colours, fonts and logos — never ship in this public repo; they live in your
private infra repo / deployment mount (see
[`.claude/project/design/theming.md`](.claude/project/design/theming.md)).

### The `design-adoption` skill

To match a shop's look without hand-writing CSS, this repo ships a **deploy-time
Claude-Code skill**, `design-adoption` (in
[`.claude/skills/design-adoption/`](.claude/skills/design-adoption/SKILL.md)) — an
authoring tool, not an app-runtime feature. Run it inside a Claude Code session:
give it the shop's URL and it scans the site, extracts the corporate identity
(colours, fonts, logo), and produces a ready-to-place brand overlay plus a
placement report.

Under the hood it calls a deterministic Artisan command you can also run directly:

```bash
task artisan -- revoco:make-theme \
  --slug=myshop \
  --accent='#009eaa' --fg='#26262e' \
  --font='"Barlow", sans-serif' \
  --logo-url='https://myshop.example/logo.svg' \
  --brand-name='My Shop' \
  --output=./myshop-theme.css
```

The command validates every value against the `--wf-*` contract, rejects malformed
input, and never overrides accessibility-critical tokens (the focus ring stays
intact). The result is **operator-reviewed and operator-placed**: move the overlay
into your deployment, set `APP_THEME=<slug>`, wire the logo via `REVOCO_LOGO_URL`,
and review the rendered form before going live. Nothing is auto-published, and
brand assets are never committed to this repo.

---

## Legal pages (Impressum & privacy)

Revoco serves an Impressum (§ 5 DDG) and a privacy policy at `/impressum` and
`/datenschutz`, backed by operator-editable settings in the Filament **Legal** panel
(neutral "not configured yet" placeholders until filled). The operator is the data
controller and authors the substantive text — Revoco ships only the mechanism.

### The `legal-extraction` skill

If the operator already publishes an Impressum and privacy policy elsewhere (their shop,
a CMS), this repo ships a **deploy-time Claude-Code skill**, `legal-extraction` (in
[`.claude/skills/legal-extraction/`](.claude/skills/legal-extraction/SKILL.md)), to
import them instead of copying field by field. Give it the two URLs and the locale; it
scrapes the pages, extracts the § 5 DDG fields and the privacy content, and hands a JSON
payload to a deterministic Artisan command:

```bash
task artisan -- revoco:import-legal --locale=de --input=./legal-payload.json
```

The command validates the payload against the settings schema (unknown key or malformed
e-mail → nothing written), sanitises scraped HTML, scopes per-locale fields to `--locale`,
and **refuses to overwrite already-populated fields** unless `--overwrite` is given — your
reviewed legal text is never silently replaced. The import is **operator-reviewed**: open
Filament → Legal, correct every field, and check the rendered pages before relying on them.
Revoco makes no legal-correctness guarantee.

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

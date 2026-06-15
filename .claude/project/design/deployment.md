# Deployment (generic)

> This public repo ships the app as generic, env-driven Docker containers. Operator-specific deployment (real domains, reverse proxy/TLS, CI runner, secrets, branding overlay) lives in a **separate private infra repository** — never here.

## What this repo provides
- `docker/Dockerfile` — multi-stage (`base → with-composer → dev`; `build`/`assets → prod`); the prod image carries app code + `--no-dev` vendor + built assets, no Composer/Node/tests.
- **Compose = base + override** (decided slice-006): `docker-compose.yml` is the generic **prod** base — `web` (nginx) · `app` (php-fpm, prod image) · `queue` (`queue:work`) · `scheduler` (`schedule:work`); `docker-compose.override.yml` carries the **dev** deltas (bind-mount, `target: dev`, profiled `node`). Plain `docker compose up` auto-merges the override → dev; prod runs `docker compose -f docker-compose.yml up`. The Taskfile (no `-f`) stays dev.
- **SQLite** on a persistent named volume (`app_storage`) — no separate DB service. nginx serves the prod `public/` without a bind-mount (shared `app_public` volume populated by the app entrypoint, or a baked stage — finalized at build).
- **Prod entrypoint:** on boot — ensure storage/SQLite perms, **fail fast if `APP_KEY` is missing** (never `key:generate` in prod), `migrate --force` (app service only), then `config:cache` + `route:cache` + `view:cache`. Same prod image for app/queue/scheduler (different commands).
- `.env.example` — all configuration via env: app, theme/branding, SMTP, ntfy, queue, etc. (placeholders only).
- Optional `ntfy` compose profile for standalone users who want a self-contained push server.
- **CI = GitHub Actions** (decided slice-006): test gate (`task check`) on PR + push `main`; on a SemVer tag (`v*`) build the prod image, smoke-test it (`curl` the form route → 200), and push to **`ghcr.io`**. CI reuses the Task targets.

## Configuration (per operator, via `.env`)
- App: `APP_KEY`, `APP_URL`, `APP_TIMEZONE`, locale.
- Branding: `APP_THEME` (neutral default) + logo/copy/links.
- Mail: direct SMTP credentials (operator's mail server); sender address.
- Push: `NTFY_ENABLED`, `NTFY_URL`, `NTFY_TOPIC`, optional token.

## Out of scope here (→ private infra repo)
- Real domains, DNS, reverse proxy (TLS), and any access restriction (e.g. VPN-only staging).
- CI deploy workflows bound to a specific runner/host, environments, server paths.
- The shared ntfy server instance.
- The brand overlay assets (logo, theme, copy) for a specific operator.

## Safety invariant
- Non-production environments must never send real mail/push (`MAIL_MAILER=log`, push off).

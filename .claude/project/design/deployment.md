# Deployment (generic)

> This public repo ships the app as generic, env-driven Docker containers. Operator-specific deployment (real domains, reverse proxy/TLS, CI runner, secrets, branding overlay) lives in a **separate private infra repository** — never here.

## What this repo provides
- `Dockerfile` + `docker-compose.yml` — generic stack: `web` (nginx) · `app` (php-fpm) · `queue` (`queue:work`) · `scheduler` (`schedule:work`).
- **SQLite** on a persistent volume (`app_storage`) — no separate DB service.
- `.env.example` — all configuration via env: app, theme/branding, SMTP, ntfy, etc. (placeholders only).
- Optional `ntfy` compose profile for standalone users who want a self-contained push server.
- CI: tests (on PR + push) and an image build (on SemVer tag) to a container registry.

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

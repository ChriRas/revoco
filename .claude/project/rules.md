# Rules

> How we build, always. Every rule verifiable against State. Keep under ~80 lines.

## Stack & Tools

- **Language:** PHP / Laravel — latest stable at scaffold time. Verify exact versions live before scaffolding.
- **Frontend:** Blade + Controller + FormRequest (no Livewire); theming via CSS `data-theme`, active theme via `APP_THEME`.
- **i18n:** Laravel localization (`lang/<code>/*`), all user-facing strings as keys; default `de`. More languages = new lang files.
- **Operator backend:** Filament — one `Withdrawal` resource behind login.
- **Database:** SQLite (file on a persistent volume).
- **Queue:** database driver + worker (e-mails + ntfy push run async).
- **Mail:** direct SMTP via `.env` (operator's mail server).
- **Push:** ntfy client, toggleable via `.env` (`NTFY_ENABLED`); the server is operator infra.
- **Test/Lint/Static:** Pest · Laravel Pint · PHPStan/Larastan (level max).
- **Infra:** Docker, GitHub Actions (CI: tests + image build to a registry). Generic, env-driven containers; operator deployment is out of this repo.
- **Package Manager:** Composer (PHP), npm (assets).

## Container & Dev Environment

- **Base images:** Alpine + current stable/LTS, verified live (never Debian oldstable):
  `php:8.5-fpm-alpine`, `nginx:1.30-alpine`, `node:24-alpine`.
- **Runtime:** php-fpm + nginx (two containers) — not FrankenPHP/Sail; no dev/prod drift.
- **Multi-stage Dockerfile** (`base → with-composer → dev`; `build`/`assets → prod`): the
  prod image is Composer-, dev-dependency- and test-free.
- **Node is build-only** — kept out of the PHP image, run as a profiled `node` compose
  service (Vite).
- **Task (go-task)** is the single host orchestrator; all PHP/Node tooling runs in
  containers (no local runtimes). `task check` = Pint + PHPStan + Pest is the pre-commit gate.
- **SQLite** persists as a bind-mounted file in dev (no DB service); dev stack on `localhost:8580`.

## Personality

- **Stack-Pack:** stack-php-laravel

## Operational Language

- **Chat:** German — all conversation with the user is in German.
- **Commits:** English.
- **Comments:** English.
- **All committed artifacts** — code, comments, documentation (incl. `.claude/` project docs), `README`/`CLAUDE.md` — are **English**.
- **Only exception:** end-user-facing UI text (form, e-mails, legal copy) in its target language (default German), authored as translation keys.

## Workflow Rules

- The withdrawal submit must never fail on an external dependency (SMTP, push, shop) — async via queue is mandatory.
- Tests green before commit; Pint + PHPStan (level max) before commit.
- No direct push to `main` — always via PR.
- Keep versions current; upgrades are their own slices behind green gates.

## Code Conventions

- Domain/business logic in Services/Actions, not in controllers.
- E-mail sending and push run in queue jobs, never inline in the request.
- User-facing strings as translation keys — no hardcoded German in markup.
- Branding (logo, copy, theme) via config/env, not hardcoded.
- Compact comments; multi-line as `/** … */` (PHP), `{{-- … --}}` in Blade. No references to non-committed paths (`research/`).

## Tabus (Anti-Patterns)

- No hard blocking of the submit via spam signals or captcha — signals classify only.
- No mandatory fields beyond name / contract identification / e-mail; no advertising in the acknowledgment e-mail.
- **No operator/infra specifics in this public repo** — real domains, IPs, hosting, reverse-proxy/VPN details, secrets, and brand assets live in the private infra repo. Ship placeholders + `.env.example` only.
- Non-production environments never send real mail/push (`MAIL_MAILER=log`, push off).
- The `research/` folder is read-only and never committed.

## Deployment

- **Branch model:** trunk-based, feature branches via PR.
- **CI/CD (this repo):** tests on PR + push `main`; image build on SemVer tag → registry. Generic, env-driven containers.
- **Operator deployment:** out of scope here — reverse proxy/TLS, domains, environments, runner, secrets, branding overlay live in the **private infra repo**. Generic guidance: `design/deployment.md`.
- **License:** AGPL-3.0.
- **Pre-deploy checks:** Pest, Pint, PHPStan green.

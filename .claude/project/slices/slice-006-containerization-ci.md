# Slice 006 — Containerization & CI

> Completed: 2026-06-17
> Commits: 562f6b1..c274f00 (branch slice-006-containerization-ci, built in the main
> checkout without a worktree per operator request; ~9 commits) merged into main via
> GitHub PR #4 — the first slice whose CI was verified for real on the PR.

## What

Ships the app as a generic, env-driven **production stack** and wires up **GitHub
Actions** (quality gate on PR/push; image build + smoke + push to ghcr.io on a SemVer
tag). The multi-stage Dockerfile from slice-001 is reused, not rebuilt.

- **Compose = base + override.** `docker-compose.yml` is the generic prod base (`app`
  prod image no bind-mount, `web` nginx, `queue`, `scheduler`, SQLite on `app_storage`,
  optional `ntfy` profile); `docker-compose.override.yml` carries the dev deltas, so
  plain `docker compose up` / `task up` stays dev.
- **Prod entrypoints** (run as root for setup, then drop): fail fast if `APP_KEY` is
  missing (never `key:generate`), `migrate --force`, `config`/`route`/`view` caches;
  worker entrypoint for queue/scheduler. **Non-root runtime** — php-fpm workers and the
  queue/scheduler processes run as `www-data` (the php-fpm master stays root per the
  standard FPM model); the SQLite DB is chowned to `www-data` after migrate.
- **Static serving without bind-mount** — nginx serves `public/` from a shared
  `app_public` volume that the app entrypoint **wipes then re-syncs** from the image on
  every start (no stale Vite chunks across upgrades).
- **`task smoke`** — build the prod image, boot the stack, `curl /` → 200, teardown.
- **GitHub Actions** — `ci.yml` (Pint · PHPStan max · Pest on PR + push `main`),
  `release.yml` (on a `v*` tag: build prod image, smoke, push to
  `ghcr.io/<owner>/revoco`). Action majors verified live (checkout@v6, setup-buildx@v4,
  build-push@v7, login@v4).
- **README** deployment section + the **Revoco logo** (`docs/revoco-logo.svg`).
  `.env.example` prod completeness; `design/deployment.md` updated.

## Why

§-356a app must ship as a reproducible, generic OSS stack others can self-host. The
base+override split keeps one source of truth for the stack while preserving slice-001's
dev ergonomics. Fail-fast `APP_KEY` avoids silently rotating session/encryption keys on
restart. Non-root runtime is safe-by-default for arbitrary deployments.

## The test-environment leak (the slice's defining issue)

The first build shipped a **red gate wrongly dismissed as "pre-existing"** (main was
green at 58). Root cause: the prod compose base sets ~25 app-config env vars on the
`app` service, and the dev `app` service **inherits them via the base+override merge**.
As real OS env vars they shadowed the non-forced `phpunit.xml` values: `APP_ENV` →
CSRF 419 on POST tests; `QUEUE_CONNECTION=database` → the `ShouldQueue` delivery
listener never ran synchronously. `phpunit.xml` `force="true"` does **not** beat a real
OS env var.

**Fix (reviewer-confirmed as the right layer):** pin the behaviour drivers at the
**test-runner boundary** — `RUN_TEST` in `Taskfile.yml` locally, `-e` flags in `ci.yml`
in CI — leaving DB to phpunit's `:memory:`. The dev *runtime* keeps the prod drivers
(database queue/cache/session), which is correct dev/prod parity. → **promoted to
`rules.md`.**

CI surfaced three further bugs invisible locally, each fixed: `--env-file .env` injected
prod drivers as real env; `-e DB_DATABASE=:memory:` broke in-memory connection sharing;
`key:generate` ran without a mounted vendor → empty APP_KEY (replaced with a fixed
throwaway CI test key).

## Decisions

- **Compose = base + override** → promoted to `rules.md`.
- **Test-runner driver pinning** (RUN_TEST ↔ ci.yml, kept in sync) → promoted to `rules.md`.
- **Fail-fast APP_KEY; never auto-generate.**
- **One prod image, three roles** (app/queue/scheduler; shared image, different commands).
- **Non-root prod** — workers + queue/scheduler as `www-data`; master root (standard FPM).
- **`app_public` wipe-before-sync** so the volume exactly mirrors the image.
- **CI = GitHub Actions + ghcr.io**; image push on `v*` tag only. (Deployment detail in
  [`design/deployment.md`](../design/deployment.md).)

## Review (Phase 8)

2 Heavy/needs-rethink → both resolved option A (user decision): **H1** non-root via
`su-exec` + correct chown ordering (verified: `ps` shows workers/queue as `www-data`,
DB owned by `www-data`); **H2** wipe-before-sync. 3 Light fixed (restart policy on
app/web, RUN_TEST↔ci.yml cross-ref comments, ntfy port default). 2 Light noted
(worker cold-boot crash-loop self-heals via `restart: unless-stopped`; README compose
floor). A `task smoke` teardown bug (folded-scalar with literal newlines → exit 127)
was found and fixed.

`task check` green (58 passed / 180); `task smoke` green (HTTP 200); **PR #4 CI green**.

## Follow-ups

- **Worker cold-boot crash-loop:** queue/scheduler crash until the app finishes the
  first migrate; self-heals via `restart: unless-stopped`. Document in operator notes.
- **release.yml ghcr push** is exercised only by a real `v*` tag, not by a PR — verify
  with a `v0.0.0-test` tag before the first real release.

## How (Diagram)

```
DEV:   docker compose up (base + override)  → app(dev, bind-mount) · web · profiled workers/node
PROD:  docker compose -f docker-compose.yml up  → app(prod) · web · queue · scheduler  [+ntfy profile]
              app entrypoint (root): APP_KEY check → migrate --force → wipe+sync public/ → caches
                                     → chown www-data → php-fpm (workers = www-data)
CI:    PR/push  → ci.yml: build dev image → Pint · PHPStan · Pest (drivers pinned via -e)
       v* tag   → release.yml: build prod → smoke (curl / → 200) → push ghcr.io/<owner>/revoco
```

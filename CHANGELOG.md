# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] — 2026-08-20

Maintenance release. No functional changes and no configuration changes — this release
only refreshes the dependency tree on top of 1.0.0, closes three high-severity npm
advisories in the build toolchain, and republishes the Filament panel assets to match
the upgraded package. The full quality gate (Pint, PHPStan level max, 220 Pest tests)
is green, and `npm audit` reports zero vulnerabilities.

### Security

- **shell-quote 1.8.4 → 1.9.0** (via `concurrently` 9.2.4) — quadratic-complexity denial
  of service in `parse()` (CWE-407).
- **postcss 8.5.15 → 8.5.25** (via `vite`) — path traversal in source-map auto-loading
  that could expose arbitrary `.map` files (GHSA-r28c-9q8g-f849).
- **nanoid 3.3.16 → 3.3.18** — infinite loop in custom generators when `size` is zero
  (GHSA-2v37-7h3g-55p8).

All three are build-time dev dependencies. None affects the shipped production image,
which contains no Node toolchain.

### Changed

- **Filament 5.6.8 → 5.7.6** across all packages, with the published panel assets under
  `public/js/filament/` and `public/css/filament/` rebuilt to match.
- **Laravel framework 13.19.0 → 13.26.1**, Livewire 4.3.3 → 4.4.1, Symfony components
  8.1.0/8.1.1 → 8.1.2/8.1.4, Carbon 3.13.2, CommonMark 2.10.0.
- **Guzzle 7.13.2 → 8.0.2** (with `promises` 3.0.1, `psr7` 3.0.0, `uri-template` 2.0.0) —
  a transitive major upgrade pulled in by the framework. Revoco calls no Guzzle API
  directly; the ntfy push client goes through Laravel's HTTP client facade.
- Dev toolchain: Pint 1.29.3 → 1.30.5, PHPStan 2.2.2 → 2.2.8, Pest 4.7.5 → 4.7.8,
  PHPUnit 12.5.30 → 12.5.33, Mockery 1.6.14.

### Notes

- Version constraints in `composer.json` and `package.json` are unchanged — this is a
  lockfile refresh, so upgrading requires no operator action beyond pulling the new
  image.
- Operator deployment specifics (real domains, reverse proxy, secrets, brand assets)
  live in a separate private infrastructure repository, never in this public repo.

## [1.0.0] — 2026-07-08

First stable release. Revoco is a self-hosted, single-merchant electronic withdrawal
form for the German § 356a BGB statutory right of withdrawal (mandatory since
2026-06-19) — neutral by default, configurable per `.env`, open source (AGPL-3.0). The
full feature set — form, submit, async acknowledgment/notification mail and ntfy push,
Filament operator backend, DE/EN i18n, operator self-service configuration, and
containerized deployment — was validated across the 0.5.0–0.7.0 pre-releases. This
release stabilizes it as 1.0.0 and adds the two deploy-time authoring skills.

### Added

- **`design-adoption` authoring skill** — a deploy-time Claude-Code skill that scans a
  shop's website, extracts its corporate identity (colours, typography, logo), and
  generates a ready-to-place `--wf-*` brand theme overlay plus a placement report so the
  form mirrors the shop. Backed by the deterministic `revoco:make-theme` Artisan command,
  which emits only known contract tokens and preserves the accessibility invariants.
- **`legal-extraction` authoring skill** — a deploy-time Claude-Code skill that scrapes
  the operator's existing Impressum and privacy-policy pages and loads them into the
  DB/Filament legal settings, via the deterministic `revoco:import-legal` command
  (schema-validated, HTML-sanitized, refuses to clobber reviewed content without
  `--overwrite`). Reviewed in the Filament panel before it goes live.
- Both skills run **at deploy time only** — the AI never touches the running app, and all
  output is operator-reviewed, never auto-published.

### Changed

- Dependency refresh: Pest 4.7.5, Laravel Pint 1.29.3, Filament settings plugin 5.6.8.

### Notes

- Operator deployment specifics (real domains, reverse proxy, secrets, brand assets)
  live in a separate private infrastructure repository, never in this public repo.

## [0.7.0] — 2026-07-06

Bug-fix and polish release from the 0.6.0 staging round, ahead of the stable 1.0.0.
It fixes operator notification delivery, adds two rich-editor conveniences, brands the
admin panel, and standardizes the PHP baseline on 8.5. Intended for a second staging
validation in a real environment before 1.0.0.

### Fixed

- **The operator notification e-mail now reaches the operator.** It previously only
  sent when the env-only `MERCHANT_NOTIFICATION_EMAIL` was set (unset on staging → no
  mail). The recipient is now operator-managed on a new **Notifications** settings
  page, decoupled from the mail *from* address (a no-reply may send while alerts go to
  e.g. shop@…), resolved as panel setting → env → imprint e-mail. A **"send test
  e-mail"** action confirms delivery, and the spam verdict now **leads** the subject
  line (`⚠ SPAM-VERDACHT: …`) instead of being appended.
- **Admin panel branding.** The panel showed "Laravel"; it now carries the Revoco
  name, logo (light/dark variants), favicon and page title on every deployment,
  independent of `APP_NAME`.

### Added

- **"Paste HTML" button** in the privacy and imprint-addendum rich editors — opens a
  modal, sanitizes the pasted markup (`Str::sanitizeHtml`) and inserts it, so a law
  firm's HTML privacy policy can be dropped in without fighting the editor.
- **Sticky rich-editor toolbar** — the formatting toolbar stays pinned in view while
  scrolling a long legal text.

### Changed

- **PHP baseline raised to 8.5.** The Dockerfile already ran `php:8.5-fpm-alpine`;
  `composer.json` now requires `^8.5` (with a pinned resolution platform), aligning
  every PHP-version reference.

### Notes

- Pre-release for staging validation before the stable 1.0.0.

## [0.6.0] — 2026-07-05

Feature release ahead of the 1.0 stabilization. Building on the 0.5.0 legal-minimum
baseline, an operator can now configure all legally required content in the panel — no
code changes to go live — and the project is prepared for public, community use under
AGPL-3.0. This release is intended for staging validation in a real environment before
the stable 1.0.0.

### Added

- **Operator-managed legal content** — § 5 DDG imprint maintained as DB-backed Filament
  settings and served at `/impressum`; imprint core fields (name, address, e-mail) are
  required before the site is considered configured.
- **Per-locale legal fields** — imprint address and legal texts are maintainable per
  language, each field prefixed with its language flag, DST-aware.
- **Unconfigured-content safeguards** — a loud panel banner and a public setup notice warn
  when imprint/privacy content is missing; the banner clears immediately after saving.
- **Operator-managed consumer locales** — the consumer-facing languages are enabled and
  disabled via DB-backed settings.
- **Bilingual landing page** — a GitHub Pages site (German + English) with a curated
  screenshot set.

### Changed

- **Shared footer** — the consumer and legal pages now share one footer (the Design-16
  GitHub source mark); the legacy footer was retired and inline SVGs were extracted into
  reusable icon components.

### Notes

- Public-repository hardening accompanies this release: branch/tag rulesets (PR-only
  `main`, maintainer-only `v*` releases), read-only default workflow permissions, a
  `CONTRIBUTING.md` describing the fork/PR/release workflow, and a fixed scheduled
  `composer audit` (now `--locked`, with auto-closing of its tracking issue).

## [0.5.0] — 2026-06-30

First public release under **AGPL-3.0**. Implements the legal minimum of the § 356a BGB
electronic withdrawal function (mandatory from 2026-06-19): a consumer submits a
withdrawal declaration, it is stored, and the statutory acknowledgment e-mail plus an
operator notification are sent. Neutral by default, configurable per `.env`.

### Added

- **Withdrawal form** — neutral-first, i18n-ready Blade form (no Livewire); theme via
  `APP_THEME` (`data-theme` token swap), accessibility (ARIA, focus, reduced-motion),
  honeypot anti-spam.
- **Submit & persistence** — `FormRequest` validation of the three mandatory fields only,
  SQLite storage (Europe/Berlin timestamp + consumer locale), success page, soft
  rate-limit + spam flag (signal only — the submit never blocks).
- **E-mails & push** — consumer acknowledgment (§ 356a Abs. 4, advertising-free) and
  merchant notification via SMTP; opt-in, data-minimal ntfy push; all delivered async
  through the database queue so the submit never fails on an external dependency.
- **Operator backend** — Filament panel with a read-mostly `Withdrawal` resource
  (list/search/detail + `handled` triage toggle) behind login; the stored record is
  immutable.
- **Containerization & CI** — multi-stage Dockerfile (php-fpm + nginx, non-root prod),
  generic env-driven Compose, `task` orchestration; GitHub Actions run Pint + PHPStan
  (max) + Pest, and build/smoke-test/push the prod image to GHCR on a `v*` tag.
- **Internationalization** — German + English, in-form flag language switcher (cookie +
  middleware), and per-locale, DST-aware date/timezone in the mails (e.g. `CEST`/`MESZ`).
- **Open source** — AGPL-3.0 licence, an AGPL § 13 source-code link in the form footer
  (`REVOCO_SOURCE_URL`), and neutral public documentation.

### Notes

- Operator deployment specifics (real domains, reverse proxy, secrets, brand assets)
  live in a separate private infrastructure repository, never in this public repo.

[1.0.1]: https://github.com/ChriRas/revoco/releases/tag/v1.0.1
[1.0.0]: https://github.com/ChriRas/revoco/releases/tag/v1.0.0
[0.7.0]: https://github.com/ChriRas/revoco/releases/tag/v0.7.0
[0.6.0]: https://github.com/ChriRas/revoco/releases/tag/v0.6.0
[0.5.0]: https://github.com/ChriRas/revoco/releases/tag/v0.5.0

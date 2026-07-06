# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.7.0]: https://github.com/ChriRas/revoco/releases/tag/v0.7.0
[0.6.0]: https://github.com/ChriRas/revoco/releases/tag/v0.6.0
[0.5.0]: https://github.com/ChriRas/revoco/releases/tag/v0.5.0

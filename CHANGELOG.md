# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.5.0]: https://github.com/ChriRas/revoco/releases/tag/v0.5.0

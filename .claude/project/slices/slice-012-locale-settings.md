# Slice 012 — Locale Settings (Filament, DB-backed)

> Completed: 2026-07-01
> PR: #13 (→ epic-001)

## What

The operator manages the consumer form's offered languages + default in the Filament
panel (DB) instead of `.env`. Introduces the `spatie/laravel-settings` +
`filament/spatie-laravel-settings-plugin` foundation for epic-001.
`App\Settings\LocaleSettings` (`available: list`, `default: string`) is seeded from the
current `.env` (de,en / default de) and edited on a new Filament "Localization" page.

## Why

First slice of the epic's env→DB config split — a non-technical operator manages offered
languages without editing files. Behaviour-preserving on upgrade (seed from `.env`).

## Decisions

- Settings foundation introduced here (spatie/laravel-settings + Filament plugin, Filament
  v5-verified); sets the pattern for the rest of epic-001. See `design/configuration.md`.
- `App\Support\ConsumerLocales` is the single settings seam — controller + switcher resolve
  through it unchanged; only this class re-points from config to `LocaleSettings`.
- Divergent operator default takes effect (deviation from "middleware unchanged"): the
  no-cookie path falls back to `ConsumerLocales::default()` and always calls `setLocale()`.
  Behaviour-preserving for the seeded config.
- § 356a fallback in the seam: `available()/default()` catch spatie `MissingSettings` and
  fall back to the framework base locale, so an unseeded settings row can never 500 the
  withdrawal form or its submit.
- Default auto-selects the sole remaining locale (UX): unchecking the current default jumps
  it to the only language left.
- `APP_AVAILABLE_LOCALES` retired; `APP_LOCALE`/`APP_FALLBACK_LOCALE` (Laravel base + seed)
  and `BACKEND_LOCALE` (operator UI) stay in `.env`.
- Shipped vs. enabled locales: the CheckboxList options are the shipped locales (`lang/`
  dirs); the operator enables a subset.

## Review

Independent read-only review — no gating findings. Fixes: `MissingSettings` fallback in
`ConsumerLocales` (+ `ConsumerLocalesTest`), migration `strict_types`/`down()`, Filament
`default` scoped to the enabled locales.

## Gates

Pint · PHPStan larastan level max · Pest 89 passed (294 assertions).
Packages pinned: spatie/laravel-settings 3.9.0, filament/spatie-laravel-settings-plugin
v5.6.7 (Filament v5.6.7). Merged via PR #13.

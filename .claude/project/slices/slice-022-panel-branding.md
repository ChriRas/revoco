# Slice 022 — Admin panel branding (Revoco name, logo, favicon, title)

> Completed: 2026-07-06
> Commits: 833ac95 (PR #34)
> Origin: staging finding **B** (0.6.0) — small bug.

## What

The Filament admin panel (login page, header, browser `<title>`, favicon) now
renders as **Revoco** instead of falling back to "Laravel".
`AdminPanelProvider::panel()` gained `brandName('Revoco')`, `brandLogo` +
`darkModeBrandLogo` (light/dark SVG wordmarks), `brandLogoHeight`, and `favicon`.
The brand assets are served from `public/img/`. The neutral consumer form is
unchanged.

## Why

`AdminPanelProvider` set no branding, so Filament fell back to
`config('app.name')` — which resolves to the "Laravel" default on a deployment
that does not set `APP_NAME` (the prod image ships no dev `.env`). Hardcoding the
brand in the provider makes the panel correct out-of-the-box on **every**
deployment, independent of env.

## Decisions

- **Explicit `brandName('Revoco')`, not reliance on `APP_NAME`.** The prod image
  ships no dev `.env`; an operator who does not set `APP_NAME` would otherwise see
  "Laravel".
- **Revoco branding in the panel does not violate consumer neutrality.** The
  neutrality rule governs the consumer-facing withdrawal form (operator-brandable
  via `APP_THEME`/`logo_url`); the admin panel is Revoco's own operator tool, so
  Revoco branding there is intended.
- **Panel branding is fixed (Revoco), not operator-overridable** for now — the
  operator asked for the Revoco mark in the backend; per-operator panel branding
  is out of scope.
- **SVG-only assets** — `revoco-logo.svg`, `revoco-logo-dark.svg`,
  `revoco-favicon.svg`. No raster `.ico`/`.png` favicon fallback was added; the v5
  panel serves the SVG favicon directly.

## Commits

- `833ac95` — feat(panel): brand the admin panel as Revoco (logo, dark logo,
  favicon, title)

Files: `app/Providers/Filament/AdminPanelProvider.php`,
`public/img/revoco-logo.svg`, `public/img/revoco-logo-dark.svg`,
`public/img/revoco-favicon.svg`, `tests/Feature/PanelBrandingTest.php`.

## Follow-ups

- Archived retroactively during the 2026-07-08 plan/state reconciliation (shipped
  via PR #34 without a formal Phase 6–9 pass).
- If a deployment target needs a raster favicon, add an `.ico`/`.png` fallback.

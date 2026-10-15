# Revoco — Screenshot Set (Landing-Page Source Material)

Curated screenshots of the Revoco withdrawal app, produced from a locally seeded
**fake** dev database for the GitHub-Pages landing page.

- **Sessions:** the consumer and legal-page shots (below, "kept") come from the
  2026-07 session. All operator-panel shots were retaken on **2026-10-15** in one
  session of the **1.2.0** panel (slice-036); the panel shows `Revoco 1.2.0` at the
  sidebar foot, without an update badge.

- **Language:** every screenshot is **English**. The consumer form is German by
  default; here it was switched to English via the built-in language switcher
  (`/locale/en`). The operator backend ran with `BACKEND_LOCALE=en`.
- **Captured from:** the dev stack on `http://localhost:8580` (Laravel 13 /
  Filament 5, SQLite), via `agent-browser` (headless Chromium, light mode).
- **Retina:** desktop shots are 2× (CSS 1440×900 → 2880×1800 px); mobile shots
  are 3× on an iPhone-14 viewport (CSS 390×844 → 1170×2532 px). "Full page"
  means the image is the full scroll height, so its pixel height is larger.

> **All data is fictitious and neutral by design** — test persona *Max Mustermann*
> et al., `example.com` / `example.org` addresses, an invented company
> *"Muster Shop GmbH"*, and an invented teal brand theme *"Muster Shop"*
> (`data-theme="muster-shop"`, accent `#0d9488`). No real brands, logos, people,
> or legal texts appear. The imprint/privacy bodies are sample placeholder copy.

---

## Consumer-facing — desktop (English) — kept from the 2026-07 session

| File | Shows | Lang | Viewport |
|---|---|---|---|
| `form-neutral.png` | Withdrawal form, **neutral** default theme, empty state | en | Desktop 1440×900 |
| `form-branded.png` | Withdrawal form, **branded** theme (Muster Shop teal + logo) | en | Desktop 1440×900 |
| `form-validation.png` | Form after an empty submit — inline field validation errors | en | Desktop 1440×900 |
| `success.png` | Confirmation page after a valid submit (neutral theme) | en | Desktop 1440×900 |
| `success-branded.png` | Confirmation page after a valid submit (branded theme) | en | Desktop 1440×900 |
| `imprint.png` | Imprint / §5 DDG page, populated (Muster Shop GmbH) | en | Desktop, full page |
| `privacy.png` | Privacy-policy page, populated (sample copy) | en | Desktop, full page |

## Consumer-facing — mobile (English) — kept from the 2026-07 session

| File | Shows | Lang | Viewport |
|---|---|---|---|
| `form-branded-mobile.png` | Branded withdrawal form, mobile hero | en | Mobile 390×844 |
| `form-neutral-mobile.png` | Neutral withdrawal form, mobile | en | Mobile 390×844 |
| `success-mobile.png` | Confirmation page, mobile (neutral) | en | Mobile 390×844 |
| `imprint-mobile.png` | Imprint page, mobile | en | Mobile, full page |

## Operator backend — Filament (English) — session 2026-10-15

All at desktop 1440×900 (2880×1800 px) unless noted; the version `1.2.0` shows at
the sidebar foot of every shot except the login screen and the banner shot (see the
note below).

| File | Shows | URL / state |
|---|---|---|
| `backend-login.png` | Panel login screen | `/admin/login`, empty form, nothing focused |
| `backend-warning-banner.png` | Setup-completeness banner ("Setup pending — … Imprint, Privacy Policy"), captured **before** `ScreenshotLegalSeeder` ran | `/admin/withdrawals`, Inbox, top of page |
| `backend-list.png` | Inbox tab: live preview of the newest row (Max Mustermann) and the list. **The panel home** (no dashboard) | `/admin/withdrawals`, top of page, pointer away from the list |
| `backend-list-spam.png` | Spam tab: 2 red open rows, 2 auto-sorted ("Spam (automatic)"), 1 operator-confirmed | `/admin/withdrawals?tab=spam`, scrolled 255 px |
| `backend-list-search.png` | Inbox with the search `Muster` (4 of the 11 Inbox rows match by name) | `/admin/withdrawals`, search `Muster`, scrolled to the end |
| `backend-list-signal-filter.png` | Inbox filtered by one signal ("Form filled in implausibly fast"): 2 rows, one "Not spam (your decision)". Source material, not on the landing page | `/admin/withdrawals`, filter Signal, panel closed, scrolled 133 px |
| `backend-detail.png` | Detail of a yellow row (Sabine Musterfrau): submitter, statement, triage with signal labels, status, and Mark as spam / Not spam / Mark handled in the header | `/admin/withdrawals/{id}` |
| `backend-statistics.png` | Statistics page, period **Year**: six tiles and the monthly history chart (Jan–Oct; Nov, Dec empty) | `/admin/statistics`, scrolled 100 px so tiles and chart fit |
| `backend-settings-locale.png` | Localization settings (Deutsch + English offered, default Deutsch) | `/admin/manage-localization` |
| `backend-settings-scope.png` | Withdrawal-scope settings (goods and services on, digital off) | `/admin/manage-withdrawal-scope` |
| `backend-settings-legal.png` | Legal settings, privacy tab with sample copy in both languages (**full page**, 2880 px wide) | `/admin/manage-legal`, `--full` |

---

## Notes for the design session

- **No separate dashboard.** The Filament panel has no dashboard widget; its home
  page *is* the withdrawals list (`backend-list.png`). The Statistics page is its own
  sidebar entry.
- **No dark mode.** The public form ships a single light palette; the operator shots
  are light mode (`--color-scheme light`). The branding axis is instead shown as
  **neutral vs. branded**.
- **Neutral vs. branded** is the key visual story: `form-neutral*` is the
  out-of-the-box default; `form-branded*` shows the same form after a deploy-time
  brand overlay (generated by `revoco:make-theme`) + logo + brand name.
- **Spam and handled states** are visible in `backend-list.png` (spam-level badges,
  handled check marks) and `backend-list-spam.png` (verdict notes under the badge).
- **Dates** render as `DD.MM.YYYY HH:MM` regardless of UI language.
- **Version foot under the banner:** with the setup banner showing, the sidebar is
  pushed down by the banner height, so the version line at its foot falls just below
  the 900 px viewport. `backend-warning-banner.png` therefore does not show it.

## Reproducing the operator session

Seed data is fictitious and fixed (no Faker, no randomness), in two seeders that
refuse to run in production and are kept out of the production image
(`.dockerignore`): `ScreenshotSeeder` (withdrawals, twelve months of daily-counter
history, operator settings) and `ScreenshotLegalSeeder` (placeholder imprint and
privacy copy, kept separate because the banner shot needs the legal settings empty).

1. Back up the developer's `database/database.sqlite` (the next step wipes it).
2. Start the dev stack with the panel language and the version set through the
   container environment, e.g. a compose override file passed as a third `-f` with
   `BACKEND_LOCALE: en`, `REVOCO_VERSION: "1.2.0"`, `REVOCO_UPDATE_CHECK: "false"`
   (the version source is `config('revoco.version')`, baked from `REVOCO_VERSION`).
3. `php artisan migrate:fresh --seeder=ScreenshotSeeder` inside the app container,
   then create a throwaway operator with `php artisan app:operator --email=operator@example.com --password=<anything>`.
4. `agent-browser --color-scheme light`, then `set viewport 1440 900 2`; log in,
   capture `backend-login` (before login) and `backend-warning-banner`.
5. `php artisan db:seed --class=ScreenshotLegalSeeder`, then capture the remaining
   shots as in the table above, looking at each one before moving on.
6. Regenerate the WebP derivatives (`docs/assets/README.md`), run
   `bash docs/assets/check-images.sh`, stop the stack and restore the database file.

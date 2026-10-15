# Slice 036 — Docs Screenshots

> Completed: 2026-10-15
> Commits: e87a9ef..18f5b6f (branch epic-004-operator-insights, autopilot)
> Epic: [epic-004 — Operator Insights](./epic-004-operator-insights.md)

## What

The public docs show the 1.2 operator panel: every `backend-*` screenshot was retaken in one fictitious-data
session (light mode, 1440x900 at 2x), three new shots were added (Spam tab, Statistics, signal filter), the WebP
derivatives were regenerated, and both landing pages show six operator figures (Inbox with live preview, Spam tab, detail,
Statistics, setup banner, scope settings) with captions, alt texts and real `width`/`height`. The showcase paragraph names
the Statistics page and the version display.

## Why

Slices 033-035 changed the panel (daily counters, Statistics page, version line); the screenshots still showed the
pre-spam-levels panel and the manifest said "Outdated since slice-025". Runs last so one session shows all three.

## Walk-through

1. `ScreenshotSeeder` / `ScreenshotLegalSeeder`: fixed data, no Faker, refuse in production, excluded from the image
   (`.dockerignore`). Twelve months of counters come from slice-033's live path: historical rows are created with a past
   `created_at` (the `created` observer counts them) and then deleted. Pest test `ScreenshotSeederTest` (6 tests).
2. Session on the dev stack (compose override for `BACKEND_LOCALE=en`, `REVOCO_VERSION=1.2.0`, update check off, so `.env`
   stayed untouched): `migrate:fresh --seeder`, throwaway operator `operator@example.com`, `agent-browser` light mode;
   dev SQLite backed up before and restored after (hash verified), dev stack stopped as it was found.
3. `cwebp` derivatives (1440 px), landing pages EN/DE rewritten (grid 4 to 6 figures), legal figure height 2108 to 1649.
4. `docs/screenshots/manifest.md` rewritten (session, seeding/capture procedure, per-shot table);
   `docs/assets/check-images.sh` plus a README line.
5. Verified by `verify-run.sh` (9/9: seeder test, dockerignore, new/retaken shots, page references, image consistency,
   html-validate, lychee, `task check`).

## Observations from the render

Statistics page renders cleanly (tiles, stacked monthly chart, legend).
With the setup banner showing, the sidebar keeps its full viewport-minus-topbar height but starts below the banner, so the
version line at its foot is pushed ~40 px below the 900 px viewport (it shows after scrolling, and in every shot without
the banner); `backend-warning-banner` therefore shows no version. The filter panel's date inputs follow the browser locale
(`tt.mm.jjjj` here), so no shot shows the open filter panel.

## Decisions

- **Runs last, after all three siblings** (2026-10-15) — `Depends-On: slice-033, slice-034, slice-035`; source: the
  human ("wenn wir diese Anpassungen alle gemacht haben, dann … die Screenshots", memory `docs-screenshots-refresh`,
  design record `operator-insights.md` request 4).
- **"EN/DE" means both landing pages, one English screenshot set** (2026-10-15) — the DE page has always shown the
  English shots (slice-020, `manifest.md`: "every screenshot is English"); the memory's how-to-apply asks for "width/height
  in both landing pages", not a German set. Judgment call: no German-language panel shots; `[R]` at the gate if wanted.
- **README gains no screenshots** (2026-10-15) — the README carries only the logo today; there is nothing to retake, and
  adding images would grow the entry. Judgment call; `[R]` at the gate if the README should show one.
- **Only panel shots are retaken** (2026-10-15) — the consumer form changed only in hidden elements since the 2026-07
  set (render token, honeypot hint: `git diff e2a123d..HEAD -- resources/views/withdrawal/form.blade.php`), so form,
  success, legal-page and mobile shots and `og-image.png` stay. Every panel shot is retaken, used on a page or not, so
  the set comes from one session and shows the new sidebar (Statistics entry, version foot).
- **New shots: Spam tab, Statistics, signal filter** (2026-10-15) — the Spam tab and Statistics join the landing-page
  operator grid (4 → 6 figures, an even 2-column grid); `backend-list-signal-filter` is manifest source material only.
  Not added: notification and network-signal settings shots (not named by the entry).
- **Version shown is `1.2.0`, no update badge** (2026-10-15) — the docs ship with 1.2.0 (design record, round 2: "the
  rest ships as 1.2.0"). A badge would name a release that does not exist and make the product look outdated in its own
  marketing; the badge is described in text only. Judgment call. If slice-035 offers no way to set the version on the
  dev stack, the session sets it through whatever source slice-035 reads and records how in the manifest.
- **Light mode for all panel shots** (2026-10-15) — `agent-browser --color-scheme light`; resolves slice-020's follow-up
  that `backend-settings-legal.png` was captured dark.
- **Statistics shot is a viewport shot, period "Year"** (2026-10-15) — a full-page shot would break the row heights of
  the 2-column grid; "Year" fills the history chart with monthly bars from the seeded 12 months. Judgment call.
- **Reproducible seeders, not shipped** (2026-10-15) — two seeder classes (legal content separate, because the
  warning-banner shot needs it empty); fixed data instead of Faker; production refusal plus a `.dockerignore` entry
  (rules.md: prod image is dev-dependency- and test-free). Counters go through slice-033's live path (its `created`
  observer), so the seeder breaks loudly, in its Pest test, when the counter schema changes.
- **Revision P1-3: counter history via create-then-delete** (2026-10-15; plan-architect round 1, finding P1-3,
  applied). slice-033 offers two entry points, and neither fits as the plan first named them:
  - Its backfill sits in a migration's `up()` and cannot be called from a seeder.
  - Its recorder runs only from the `created` / `updated` observer of a saved `Withdrawal`. Seeding 12 months of rows
    that way would leave every historical row in the Inbox.
  - The seeder therefore creates the historical rows as models with a past `created_at`, so the observer counts
    each on that day, and then deletes them. slice-033 has no `deleted` handler (its plan: "No `deleted` handler"),
    so the counts survive as after the purge, and the list keeps only the visible set.
  - `Withdrawal` today has no soft deletes and no model events of its own (`app/Models/Withdrawal.php`), so the
    delete leaves nothing behind and the create sends no mail. Mails go out from `StoreWithdrawal`, which the
    seeder does not call.
  - `ScreenshotSeederTest` asserts both halves: 12 months of history, and only the planned rows in the list.
- **Spam-tab and search/filter shots are scrolled, the others at the top** (2026-10-15) — the live preview takes the upper half
  of the Inbox page, so the Spam, search and signal-filter shots are scrolled to the table (the page's end) to show the rows
  and verdict notes; the Statistics shot is scrolled 100 px so tiles and the whole chart fit. Judgment call, recorded in the manifest.
- **Version and panel language set through a compose override, not `.env`** (2026-10-15) — `.env` is the developer's file
  (`BACKEND_LOCALE=de`); a scratch override with `BACKEND_LOCALE=en`, `REVOCO_VERSION=1.2.0`, `REVOCO_UPDATE_CHECK=false`
  needed no change to it. The manifest describes the same route.
- **Fictitious network data** (2026-10-15) — real ASN organisation names are real brands, so seeded rows use the
  documentation ASN range 64496–64511 (RFC 5398) with invented network names; notification recipient and all e-mails
  on `example.com` / `example.org` (not `merchant.com`, a real domain).
- **Capture steps live in the manifest, no capture script** (2026-10-15) — the session needs judgment per shot
  (preview row, filter state, scroll position); the manifest records URL and state per shot instead. Judgment call.
- **Dev data is backed up and restored** (2026-10-15) — `migrate:fresh` wipes the dev SQLite file; the session copies
  it aside first and restores it afterwards (memory `self-verify-dont-delegate-testing`: clean up after verification).
- **Copy scope** (2026-10-15) — this slice owns the screenshot showcase (figures, captions, alt texts, its paragraph);
  feature copy elsewhere (features grid, FAQ, README text) belongs to the feature slices or the release docs sync.
- **A local image-consistency script, not a CI step** (2026-10-15) — `docs/assets/check-images.sh` turns the manual
  "update width/height if the aspect changes" note into a command; wiring it into `docs.yml` was not asked for.
  Judgment call.
- **Refactor candidate (autopilot, not applied):** `ScreenshotSeeder::seedVisibleRows()` keeps the Inbox and Spam rows as positional 12-13 element arrays — named keys (or a small value object) would make the data table readable and safer to edit.
- **Durable capture** — no cross-cutting design knowledge surfaced beyond these decisions; the session procedure itself
  becomes durable in `docs/screenshots/manifest.md`.

## Commits

- `e87a9ef` — test(seeders): add fictitious seeders for the documentation screenshots
- `18f5b6f` — docs(screenshots): retake the panel screenshots for 1.2

## Follow-ups

- With the setup-pending banner showing, the sidebar keeps its full height below the banner, so the version line at its foot sits about 40 px below a 900 px viewport (seen in the screenshot session; recorded in docs/screenshots/manifest.md).

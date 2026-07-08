# Slice 020 — Landing Page (GitHub Pages)

> Completed: 2026-07-05
> Commits: e2a123d..81c4603 (PR #27)

## What

Revoco now has a public face: a bilingual static landing page (English at `/`,
German at `/de/`) served as GitHub Pages straight from `main → /docs`. It explains
to non-lawyers why a statutory withdrawal form is required, shows the product in 19
curated screenshots (neutral vs. branded, mobile, operator backend, legal pages),
and walks prospects to their own deployment in four steps. Previously the project
only had the README in a still-private repo.

## Why

- The page targets developers and agencies — the people who actually deploy a
  Docker container — and frames the merchant benefits (withdrawal-form duty in
  force since 2026-06-19, data sovereignty, branding) around that.
- Product and page should read as one system: the landing page borrows the app's
  neutral `--wf-*` design language and brand-mark colours on its own `--lp-*`
  token contract.
- Zero build tooling keeps the public repo lean: two hand-maintained HTML pages
  don't amortize a static-site generator.

## Decisions

- **Audience: developers/agencies primary** — technical-sober tone; Docker/`.env`/
  AGPL and quick start prominent; merchant benefits as framing. *Why not*
  merchant-first: merchants rarely deploy Docker themselves.
- **Deploy path: GitHub Pages from `main` → `/docs`** — changes ride the normal PR
  flow; screenshots already live under `docs/`. *Why not* a gh-pages branch: extra
  publish step, page leaves the review flow.
- **i18n: two full static pages** (`/` EN + `/de/` DE, `hreflang` + visible
  switch) — works without JS, indexable per language. *Why not* a JS switcher:
  no-JS users would see EN only; weaker SEO for DE.
- **No static-site generator** — plain hand-written HTML/CSS; duplication across
  two locales accepted. *Why not* a generator: one page × two locales doesn't
  amortize a build step.
- **Design language: neutral `--wf-*` tokens reused** for brand coherence between
  product and page; Revoco brand blue (`#1f63e6`) from the logo as page accent.
- **Automatic dark mode** (user-requested in Phase 5) — `prefers-color-scheme`
  override block on the same `--lp-*` token contract, `color-scheme: light dark`,
  dark logo variant swapped via `<picture>`. Product screenshots stay light inside
  their browser frames by design.
- **Sections chosen:** withdrawal-duty explainer, AI authoring-skills, FAQ, and an
  ntfy push highlight (instant push on incoming withdrawal — attractive for small
  shop operators). A dedicated tech-stack section was not selected.
- **"Widerruf" outranks the paragraph number** (Phase 5 feedback) — copy leads
  with "withdrawal form / Widerrufsformular"; § 356a BGB follows in parentheses or
  a trailing sentence. Legal facts (EU Directive 2023/2673, in force 2026-06-19,
  no transition period) stay intact.
- **Deadline framing retired** (Phase 5 feedback) — the date has passed: "since"
  replaced "from" everywhere, and the final CTA pivoted to replacing an interim
  fix / setting up a new shop, keeping the "deployed in an afternoon" hook.
- **Full-width copy over readable-measure caps** (Phase 5 feedback) — `max-width`
  removed from section intros, showcase descriptions, skill note and FAQ; column-
  bound copy (hero lead, push section) keeps its measure.
- **Features grid fixed at 2 × 3** (Phase 5 feedback) — explicit
  `repeat(3, minmax(0, 1fr))` at ≥60rem instead of auto-fit's 4 + 2 packing.
- **Screenshots: originals + derivatives** — retina originals in
  `docs/screenshots/` (source of truth, `manifest.md`), pages serve scaled WebP
  derivatives from `docs/assets/img/` (640 KB total vs. 4.2 MB);
  `docs/assets/README.md` documents regeneration.
- **Permanent CI validation for `docs/`** — html-validate + lychee on every change
  to `docs/**` (user chose permanent CI over one-shot checks). Lychee invocation
  verified for real via Docker; `--base-url` proved invalid and was dropped (all
  links are relative).
- **Repo is still private** — own-repo and Pages URLs 404 anonymously, so both are
  `--exclude`d in lychee; **drop the two excludes after go-live**. GitHub Pages
  (free plan) needs the repo public before sub-task 6 can complete.
- **Grid tracks use `minmax(0, 1fr)`** where cards contain `nowrap`/`pre` content —
  plain `1fr` let min-content widen the track past a 390px viewport (603px
  measured); found via browser overflow probe.
- **Inline-SVG exemption for the static docs page** — the no-inline-SVG rule
  targets Blade components in app code; the dependency-free page has no component
  system. Documented exemption, not a precedent for app code.
- **Console tokens renamed** in Phase 7 (`--lp-dark*` → `--lp-console-*`) — "dark"
  became ambiguous once a real dark mode existed.
- **Observation:** `backend-settings-legal.png` was captured with Filament in dark
  mode, unlike the other (light) backend shots. Accepted for now; regenerating it
  light needs a new screenshot session.

## Commits

- `e2a123d` — docs(screenshots): add curated landing-page screenshot set
- `ef66026` — feat(docs): add bilingual GitHub Pages landing page
- `9ec850e` — ci(docs): validate landing page HTML and links
- `81c4603` — ci(docs): exclude gnu.org from the link check (timeouts on GitHub
  runners, discovered on the first real CI run)

## Follow-ups

- ~~Go-live steps (sub-task 6)~~ **done 2026-07-05**: repo public, Pages enabled
  (`main` + `/docs`, status `built`), live smoke green on both URLs (200, hero
  content, CSS + images delivered), temporary lychee excludes removed.
- Optionally re-capture `backend-settings-legal.png` in light mode for visual
  consistency with the other backend screenshots.

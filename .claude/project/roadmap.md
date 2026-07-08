# Roadmap

> Long-term phases for Revoco. Detail in `intent.md`, `rules.md`, `design/`.

## Phases

### Phase 1 — Scaffold & quality gates
Laravel scaffold (latest stable), SQLite, Pint/Pest/PHPStan (level max), README (EN), Docker dev setup, `.env.example` (incl. branding/theme/delivery/ntfy variables). Git already exists.

Status: done — Laravel scaffold, SQLite, quality gates, Docker/Task dev setup (slice-001).

### Phase 2 — Withdrawal form (neutral-first, i18n-ready)
Theme mechanism from the prototype (`data-theme`, `--wf-*`): neutral default + optional brand overlay, selected via `APP_THEME`. Static Blade form, strings as translation keys (`lang/de`), a11y (ARIA, focus, reduced-motion), honeypot. Branding (logo/copy/links) via `.env`.

Status: done — neutral-first Blade form, theme mechanism, a11y, honeypot (slice-002).

### Phase 3 — Submit, persistence & success
FormRequest validation (the 3 mandatory fields only), store withdrawal in SQLite (Europe/Berlin timestamp, locale), success page, soft rate-limit + spam flag (signal only).

Status: done — FormRequest validation, SQLite persistence, success page, spam flag (slice-003).

### Phase 4 — E-mails & push
Two mailables (consumer acknowledgment § 356a (4) + merchant notification) via direct SMTP; ntfy push client (toggleable, data-minimal). DB queue + worker.

Status: done — acknowledgment + notification mailables, ntfy push, DB queue/worker (slice-004).

### Phase 5 — Operator backend
Filament panel with a `Withdrawal` resource (list, search, detail, "handled") behind login.

Status: done — Filament Withdrawal resource (list/search/detail/handled) behind login (slice-005).

### Phase 6 — Containerization & CI
Dockerfile + `docker-compose.yml` (generic, env-driven: web/app/queue/scheduler, SQLite volume), optional `ntfy` compose profile, CI (tests + image build to a registry). Operator-specific deployment lives in the private infra repo.

Status: done — multi-stage Dockerfile + compose, CI (tests + image build) (slice-006).

### Phase 7 — i18n expansion (post-launch)
More languages (en/…) as lang files + a language switcher in the form.

Status: done — English consumer language + in-form flag switcher (slice-009),
per-locale e-mail date format (slice-010).

### Phase 8 — Public release
Finalize license (AGPL-3.0), neutral default docs, public repo.

Status: done — AGPL-3.0 LICENSE, package identity, AGPL §13 source link, CHANGELOG, neutral docs; released as v0.5.0.

### Phase 9 — Footer attribution redesign (Design 16)
Replace the "Quelltext" text link with the hover-expand double-mark (ring-tile +
Octocat); centered two-row footer, normal-weight legal links, shared Blade
component, AGPL §13 preserved via `aria-label`; mobile/responsive check.

Status: done — hover-expand Design-16 footer (ring-tile + Octocat), centered two-row
layout, normal-weight legal links, shared `<x-wf-footer>` Blade component, AGPL §13 via
`aria-label` (slice-011, form + success); legacy footer retired and the legal pages
migrated to the shared component (slice-017). Inline SVGs also extracted into reusable
`components/icons/` components along the way.

### Phase 10 — Operator self-service configuration & legal pages
Move operator-editable **legal & operational content** from `.env` to **DB/Filament**
(`spatie/laravel-settings` + Filament plugin); `.env` keeps infra, secrets and visual
identity (theme overlay, brand name, logo — content branding stays in `.env`). SMTP
stays in `.env` (+ read-only panel status). Epic **operator-configuration** (epic-001),
4 slices: locale-settings (bootstraps the settings foundation + offered languages /
fallback chain) · legal-content (Impressum + Datenschutz: multilingual + fallback chain
+ per-page link override + internal routes; footer re-wire) · missing-content-warning
(panel banner + fresh-install setup gate, gated on config completeness) · withdrawal-scope
(toggles, display only, never gate the submit). Per-slice legal research at planning time
(DDG imprint fields; BGB withdrawal categories). Intent ratified (env→DB, 2026-07-01).
See `design/configuration.md` + `design/legal-compliance.md`.

Status: done — epic-001 (operator-configuration) shipped: locale-settings (slice-012),
legal-content privacy (slice-013) + imprint § 5 DDG (slice-014), missing-content-warning
(slice-015), withdrawal-scope (slice-016). Operator-editable legal & operational content
now lives in DB/Filament via `spatie/laravel-settings`; `.env` keeps infra, secrets and
visual identity.

### Phase 11 — Authoring skills (AI) — separate epic
Deploy-time Claude-Code skills (not app runtime): (a) website/design adoption →
a per-deployment design document; (b) privacy-policy scrape + translate → an
operator-reviewed draft. AI output is always operator-reviewed, never
auto-published.

Status: done — epic-002 (authoring-skills) shipped: design-adoption (slice-018,
`revoco:make-theme` overlay generator) + legal-extraction (slice-019,
`revoco:import-legal` Impressum/Datenschutz importer). Deploy-time Claude-Code skills
paired with Pest-tested artisan commands; all output operator-reviewed via Filament,
never auto-published. The cross-cutting "deterministic command core + AI wrapper"
pattern is captured in `design/authoring-skills.md`.

## Releases (optional)

| Version | Date | Highlights |
|---|---|---|
| 0.7.0 | 2026-07-06 | PHP 8.5 baseline (Docker-first); dependency refresh (Pest 4.7, Pint 1.29, Filament settings 5.6.8). |
| 0.6.0 | 2026-07-06 | Staging-findings round: operator-managed notification recipient + test mail + prominent spam subject (021), admin panel Revoco branding (022), paste-HTML editor button (023), sticky editor toolbar (024). |
| 0.5.0 | 2026-06-30 | First public release (AGPL-3.0): full feature set — form, submit, async mails/push, operator backend, containerization/CI, DE/EN i18n + DST-aware mail timestamps. |

> The earlier 0.1.0 / 0.2.0 entries (legal minimum; containerization + backend) were
> development milestones, folded into the 0.5.0 public release rather than tagged
> separately.

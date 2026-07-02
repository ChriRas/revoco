# Slice 014 — Legal Content: Imprint (§ 5 DDG structured fields)

> Completed: 2026-07-01
> PR: #18 (→ epic-001) · commits edd894c, 1666428, 28fdd3a

## What

Adds the § 5 DDG imprint as a first-class, operator-managed feature. Extends
`App\Settings\LegalSettings` with 17 structured fields + a per-locale addendum + an
`imprint_link` external override. `GET /impressum` renders the structured data with i18n
labels via the slice-013 resolver; 302-redirects when a link is set. Ships a Filament
"Impressum" tab, per-locale address fields, language-flag field labels, required markers on
the § 5 core, and a wider settings panel. The footer imprint link migrates from config to
`LegalPages` (both legal links now DB-backed). A completeness helper (name + address +
email) feeds slice-015.

## Why

The withdrawal-form footer must link to a valid imprint (§ 5 DDG two-click rule). Operators
now enter structured content in the panel → an internal, localized page with completeness checks.

## Decisions

- Structured § 5 DDG field set verified against the primary source (`design/legal-compliance.md`).
- Data locale-independent + i18n labels + per-locale addendum — **except the postal address**,
  which is per-locale (ß/ss, "Deutschland"/"Germany"); other fields stay single-value;
  identifiers (email/phone/VAT/register) are language-invariant. (Reworked pre-merge on user feedback.)
- Completeness = name + address (default locale) + email; register/tax/professional stay
  optional; an external `imprint_link` alone also satisfies it. Contact channel = **email**
  (§ 5 DDG names it; phone is a recommended *additional* channel per EuGH C-298/07, not a substitute).
- Extends `LegalSettings` + adds a tab (reuses the slice-013 resolver/route/fallback/override)
  rather than a new settings class.
- § 356a: the resolver + controller catch `MissingSettings` and fall back → an unseeded store
  never 500s the form/submit.
- UX: language flag before each per-locale field label (reuses the consumer flag partials);
  settings page widened to `Width::Full`; § 5 core fields marked visually required
  (`markAsRequired`, no hard validation — preserves the external-link + partial-save paths).

## Review

Independent read-only review — no gating findings. Bounded fixes: orphaned `imprint_url`
config/env removed, per-locale-address rework, `.wf-imprint` CSS, addendum-only + whitespace
test cases, stale-comment fix.

## Gates

Pint · PHPStan larastan level max · Pest 141 passed (503 assertions). Merged via PR #18.

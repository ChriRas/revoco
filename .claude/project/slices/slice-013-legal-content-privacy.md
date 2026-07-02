# Slice 013 — Legal Content: Privacy Policy (legal-page pattern)

> Completed: 2026-07-01
> PR: #15 (→ epic-001)

## What

The operator maintains the privacy policy multilingually in the Filament panel (or links to
an external one); consumers see it on an internal route (`/datenschutz`) in their language.
Establishes the reusable **legal-page pattern** — internal route + per-language
resolution/fallback + external-link override + footer re-wire — that the imprint slice reuses.

## Why

Move the privacy policy from a static/config link into operator-managed DB content, and lay
the shared pattern for all legal pages in the epic.

## Decisions

- Legal-page pattern established here (route + resolver + fallback chain + external-link
  override + footer re-wire); slice-014 reuses it. See `design/legal-compliance.md`.
- Privacy policy = per-language rich text (no structured fields).
- Source precedence: **override link > internal content > empty**. An override URL → the
  footer links external and the internal route 302-redirects there.
- Empty state = neutral "not configured yet" placeholder — never fabricated legal text. The
  loud warning + setup gate live in slice-015.
- Fallback chain is configurable (ordered locale list, default `[default_locale]`); a locale
  without content falls back along the chain.
- Rich text is safe-rendered/sanitized (the operator is the sole trusted author).
- Only the privacy footer link is re-wired here; the imprint link stays on
  `config('revoco.imprint_url')` until slice-014.

## Gates

Pint · PHPStan larastan level max · Pest green (incl. `LegalPageResolverTest`). Merged via PR #15.

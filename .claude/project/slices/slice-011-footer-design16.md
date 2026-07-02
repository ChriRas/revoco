# Slice 011 — Footer Design 16 (Hover-Expand Attribution Mark)

> Completed: 2026-07-02
> Commits: 63b7f89 (feat) + docs commit (PR #22)

## What

The consumer form (`/`) and success page (`/success`) now share one Blade footer
component (`<x-wf-footer>`) carrying the Design-16 attribution mark — a blue ring-tile
+ GitHub Octocat (inline SVG) that expands the "Revoco App on GitHub" label on hover /
keyboard focus, on its own centered row so the legal links never shift. The old
standalone "Quelltext" text link is retired on these two pages, and their previously
duplicated footer markup is unified into the single component.

## Why

- The AGPL §13 corresponding-source hint becomes a polished, self-documenting mark
  while staying reachable for assistive tech (the English accessible name rides on the
  anchor `aria-label`, present before the label expands).
- Normal weight (400) instead of the mockup's bold Space Grotesk, so it fits the neutral
  form and needs no Google-Font dependency (system font stack, inline SVG only);
  `prefers-reduced-motion` drops only the grow animation.
- The mark sits on its own centered row below the legal links, so the expanding label
  never pushes them (no layout jump).

## Decisions

- **Design 16 (Hover-Expand)** chosen over variants 09 (static pill) and 15 (popover)
  from the footer shortlist. *Why not* 09/15: 16 is space-saving and jump-free while
  still surfacing the full slogan on interaction.
- **Legal links + expand label in normal weight (400)**, not the mockup's bold
  Space-Grotesk-600. *Why:* matches the neutral form and avoids a Google-Font dependency.
- **Mark on its own centered row; label expands to the side there** — so row 1 (legal
  links) never shifts. *Why not* inline with the links: an inline expand would reflow them.
- **AGPL §13 link moved from the "Quelltext" text to the mark**, kept reachable before
  hover via `aria-label="Revoco App on GitHub"` (the slogan stays English-only by design —
  a brand slogan, not a translation key).
- **Footer extracted to one shared `<x-wf-footer>`** — ends the form/success duplication.
- **Scope confined to form + success** (Build-time reality check: the identical footer
  also lives on `legal/page` + `legal/imprint`). *Consequences:* the `wf.footer.source`
  key is **retained** (still used by the two legal footers), and a fresh `.wf-foot` base
  class was introduced instead of mutating the legacy `.wf-page-foot` those legal pages
  still use — zero-regression coexistence, clean later migration. The footer's `--wf-*`
  colours carry neutral fallbacks because it renders outside the themed `.wf-card`.
- **Links keep reading `config()` this slice**; re-wire to DB/Filament settings is
  deferred to the configuration epic (S3 legal content).
- **Phase 7 (Refactor) skipped** — small, clean single-layer slice; surfaced candidates
  judged low value or harmful to the later `.wf-page-foot` removal.

## Commits

- `63b7f89` — feat(footer): replace the consumer footer with the Design-16 GitHub source mark
- docs(slice): archive slice-011 and remove its plan

## Follow-ups

> Phase-8 review found 1 Heavy + 2 Light findings — **all fixed in-phase** (focus-ring
> token fallback, footer colour-token fallbacks, legacy-footer coexistence guard test).
> No findings carried over. One natural future-slice candidate:

- Migrate the two legal pages (`legal/page`, `legal/imprint`) to `<x-wf-footer>` too,
  then delete the legacy `.wf-page-foot` block and the `wf.footer.source` key. Tracked
  implicitly by the coexistence guard test in `FooterTest`.

# Slice 017 — Legal Footer Migration (retire the legacy footer)

> Completed: 2026-07-02
> Commits: 0ae2822 (feat) + docs commit (PR #23)

## What

The two legal pages (imprint `/impressum`, privacy `/datenschutz`) now render the same
shared `<x-wf-footer>` Design-16 footer as the form and success pages. This finishes the
migration slice-011 deliberately deferred: the old three-link footer with its "Quelltext"
text link is gone from every consumer page, and the legacy remnants — the `.wf-page-foot`
CSS block and the `wf.footer.source` translation key — are removed project-wide.

## Why

- Consistency + cleanup: slice-011 brought the Design-16 mark only to form + success
  (deliberately scoped so as not to touch other slices' pages unasked), leaving a
  transitional state with two parallel footer implementations. This slice ends that
  coexistence — `.wf-foot` is now the only footer class.
- The slice-011 `FooterTest` coexistence guard (which pinned the legal pages' old state)
  was intentionally inverted rather than deleted — it existed precisely to hold until
  this migration.

## Decisions

- **Executes the migration slice-011 deferred** — both legal views swap their inline
  `<footer class="wf-page-foot">` for `<x-wf-footer />`; the legacy CSS block and the
  `wf.footer.source` key are deleted. *Why not* keep the coexistence: slice-011's archive
  named this migration as the planned next step; two parallel footers were only ever a
  transitional state.
- **The `--wf-page-foot-fg` colour token is retained** — only the `.wf-page-foot` *class
  rules* were removed; the token is still referenced (with a literal fallback) by the
  `.wf-foot` declarations. A rename to `--wf-foot-fg` was considered and declined as
  cosmetic (Phase 7 skipped).
- **The slice-011 coexistence guard test is intentionally inverted** — from "legal pages
  keep the legacy footer" to "legal pages carry `.wf-foot`, no `.wf-page-foot`, no
  Quelltext". *Why not* delete it: the inverse is the load-bearing migration marker.
- **Phase 7 (Refactor) skipped** — pure removal/cleanup slice; no structural candidate
  worth the change.

## Commits

- `0ae2822` — feat(footer): migrate the legal pages to the shared footer, retire the legacy one
- docs(slice): archive slice-017 (legal-footer-migration)

## Follow-ups

> Phase-8 review found 1 Light finding (stale component docstring), **fixed in-phase**.
> No findings carried over.

- (none) — the shared footer now covers all four consumer pages; the legacy footer is
  fully retired. This closes the follow-up recorded in the slice-011 archive.

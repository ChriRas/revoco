# Slice 024 — Sticky rich-editor toolbar

> Completed: 2026-07-06
> Commits: 2bdbdce (PR #36)
> Origin: staging finding **D** (0.6.0) — comfort feature.

## What

The Filament RichEditor toolbar now **sticks to the top** of the scroll area
(below Filament's own sticky topbar) while editing a long legal text, so the
formatting buttons stay reachable instead of scrolling out of view. Scoped CSS
(`position: sticky`) applied to the panel RichEditors only; no PHP behavior
change beyond registering the stylesheet.

## Why

Long pasted privacy/imprint texts pushed the toolbar out of view, forcing the
operator to scroll back up to format. Pinning the toolbar keeps it usable
throughout a long edit.

## Decisions

- **CSS `position: sticky`, no native flag** — Filament v5 exposes no built-in
  sticky-toolbar option, so this is a scoped CSS customization, kept minimal and
  panel-scoped so it does not leak onto other components.
- **Stick below the topbar, not at `top: 0`** — Filament's own topbar is sticky;
  the editor toolbar offsets by its height (with an opaque background + `z-index`
  above content) so it never renders underneath.
- **Implementation diverged from the plan** — instead of a `FilamentAsset`-
  registered stylesheet, the CSS is injected via a Filament panel **render hook**
  wired in `AppServiceProvider`, rendering the Blade partial
  `resources/views/panels/sticky-editor-toolbar.blade.php`. Same effect, thinner
  wiring than a registered asset file. The test asserts the hook/partial is
  present on the panel.
- **Visual feature → Phase-5 is the real gate** — automated coverage is thin by
  nature (registration only); correctness is confirmed hands-on against a long
  real text.

## Commits

- `2bdbdce` — feat(panel): sticky rich-editor toolbar for long legal texts

Files: `app/Providers/AppServiceProvider.php`,
`resources/views/panels/sticky-editor-toolbar.blade.php`,
`tests/Feature/StickyEditorToolbarTest.php`.

## Follow-ups

- Archived retroactively during the 2026-07-08 plan/state reconciliation (shipped
  via PR #36 without a formal Phase 6–9 pass).

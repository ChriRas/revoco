# Slice 031 — Live Preview

> Completed: 2026-10-14
> Commits: f3765ed..f6c56b8 (branch slice-031-live-preview, PR against epic-003-spam-handling)

## What

The withdrawal list has a live preview at the very top, above the "Posteingang / Spam" tabs. By default it shows the
newest withdrawal of the active tab — receipt time, name, e-mail, order number, goods/service, spam level, reasons
and decision; hovering or keyboard-focusing a row switches it to that row at once (no request), leaving restores the
default. The panel always has the same size; text that is too long ends in an ellipsis. From the Phase-5 test the list
also names a decision that overrides the level below the badge ("Kein Spam (Ihre Entscheidung)", "Spam (Ihre
Entscheidung)"), and confirmed spam (operator or auto-sorted) can no longer be marked handled and shows no
"still open" clock. Change request CR3 from the hands-on test of PR #87.

## Why

- The list shows only receipt time, name, order number and level; judging a row meant opening each one.
- The current page's data is embedded, so switching is instant and costs the server nothing per mouse move.
- A fixed size keeps the list from jumping; scrolling inside the panel is impossible because moving into it leaves
  the row.
- The level is the system's view at receipt and the operator's decision overrides it — without the note a red row in
  the inbox looked like a bug; confirmed spam is closed, so there is nothing to handle.

### Walk-through

`ListWithdrawals::content()` renders the preview view before the tabs and the table (Filament's default layout, render
hooks kept); its data comes from `WithdrawalPreview` — `newestIn($activeTab)` for the default and `dataFor()` for the
current page's rows. An Alpine component listens on the document: a `mouseover` / `focusin` inside a table row reads
the record id from the row's `wire:key` and shows that row's data, anything else restores the default. The preview's
`wire:key` is a hash of its data, so every Livewire re-render replaces it and Alpine starts over on the current rows.
Fields have fixed line counts and are clamped. The "Spam-Stufe" column shows `Withdrawal::verdictNote()` below the
badge; `WithdrawalActions::toggleHandled()` is hidden on the view page and disabled in the row for confirmed spam.

## Decisions

- **Panel content (human)** — declaration + triage: receipt time, name, e-mail, order number, full subject, spam
  level, reasons, decision.
- **Default = newest of the active tab (human)** — search, filters, sort and pagination do not change it.
- **Mouse leaves the table → back to the default; keyboard focus switches too; touch shows the default only
  (human).**
- **Client-side switching, no Livewire round trip** — the current page's data embedded as escaped JSON keyed by
  record id. *Why not* a request per hover: lag and server load.
- **Own branch and PR (human)** — `slice-031-live-preview` cut from `epic-003-spam-handling` (needs the spam fields),
  PR against the epic branch, retargeted to `main` after PR #87 merges. *Why not* CRAFT's in-place merge into the
  trunk: `rules.md` forbids direct pushes to `main`.
- **Fixed size with per-field line limits (human: "fixed value")** — received 1, name / e-mail / order 2,
  goods/service 4, reasons 3, decision 2; ellipsis, full text in the detail view; an empty tab keeps the size.
  *Why not* overflow scrolling (first offered): the panel cannot be reached without leaving the row.
- **Order: preview, then tabs, then table (human).**
- **Decision notes below the level badge (human)** — "Kein Spam (Ihre Entscheidung)" on a corrected yellow / red row,
  "Spam (Ihre Entscheidung)" on a confirmed green / yellow row, "Spam (automatisch)" as before. *Why not* a badge
  showing the decision: the original level stays traceable.
- **Confirmed spam needs no handling (human)** — row toggle disabled with tooltip "Spam – keine Bearbeitung nötig"
  (keeps the icon row aligned), header toggle hidden, no clock in the "Bearbeitet" column; existing `handled_at`
  values kept; a "not spam" correction makes the row handleable again.
- **No Filament theme** — the panel uses `x-filament::section` / `x-filament::badge` plus a scoped `<style>` with
  Filament's `--gray-*` variables; the three badge colours are pre-rendered and toggled with `x-show`.
- **Browser check** — agent-browser 0.35.1's `hover` / `mouse move` fire no DOM `mouseover` (independent of this code);
  hover was verified with dispatched events and real `Tab` presses, the human verified real mouse hover in Phase 5.
- **Phase 7 refactors (human-picked)** — one `WithdrawalActions::toggleHandled()` for header and row;
  `Withdrawal::verdictNote()` holds the override-note rule.
- **Phase 8** — round 1 clear: 3 Light local fixes (unused array, UTF-8-safe re-init key, page-2 test), 1 Light
  follow-up (below).

## Commits

- `f3765ed` — feat(panel): name overriding verdicts and keep confirmed spam out of handling
- `f6c56b8` — feat(panel): live preview of a withdrawal above the list

## Follow-ups

- R1-4 Light · Rethink · "Confirmed spam needs no handling" is not yet reflected in the handled filter (Spam tab, "Nur
  unbearbeitete" lists confirmed spam with an empty cell) nor in the view page's status field ("Nicht bearbeitet"
  placeholder for confirmed spam); whether the filter should leave confirmed spam out is a product decision.

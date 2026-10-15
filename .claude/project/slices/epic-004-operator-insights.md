# Epic 004 — Operator Insights

> Completed: 2026-10-15 (started 2026-10-15)
> Slices: 4/4 landed · autopilot run on epic-004-operator-insights, plan gate approved after two plan-review rounds
> Merge: 01ff285 (Merge epic-004: Operator Insights)

## Vision

Since 1.1.0, Revoco sorts spam, but the operator cannot see what that sorting does over time: how many declarations were genuine, suspected or spam, which signals fire, and how often the engine was wrong. Confirmed spam is deleted after 7 days, so that picture has to be kept in anonymous daily counters that outlive the rows. When the epic is done, a Statistics page shows genuine / suspected / spam / auto-sorted per day, month, year and all time, with false alarms, missed spam and a per-signal breakdown. The withdrawals list can be filtered by signal, and the panel shows the running version with a badge when a newer stable release exists. Afterwards the documentation screenshots are retaken. Out of scope: the shop integration (roadmap Phase 12), any change to the scoring rules, and any external call in the request path.

## Slices (4/4)

- [slice-033 — daily-spam-counters](./slice-033-daily-spam-counters.md) — anonymous daily counters (day × level × outcome × signal) kept on submit and every verdict change, a backfill on deploy, and a signal filter in the withdrawals list.
- [slice-034 — statistics-page](./slice-034-statistics-page.md) — a Statistics page with a Today / Month / Year / All time switch, six figure tiles, a stacked history chart and a per-signal table linking to the filtered list.
- [slice-035 — version-badge](./slice-035-version-badge.md) — the release image knows its version, a daily opt-out GitHub Releases check, and the version with an update badge at the sidebar foot.
- [slice-036 — docs-screenshots](./slice-036-docs-screenshots.md) — every panel screenshot retaken with fictitious data, three new shots, and six operator figures on both landing pages.

## Epic Decisions

- **Design record** (2026-10-15) — both planning rounds (data basis, counting, display, update check, page and version placement, release slot) live in `.claude/project/design/operator-insights.md`.
- **Counters ship first** (2026-10-15) — planned: `daily-spam-counters` released ahead as 1.1.1 before 2026-10-22, so the backfill counts live's 55 campaign rows before their purge. The autopilot run has no per-slice halt, and the whole epic finished the same day, so it ships together as 1.2.0 — still before 2026-10-22.
- **Shop integration deferred** (2026-10-15) — roadmap Phase 12 moves behind this epic (roadmap Phase 13).

## Open follow-ups

- The epic's own: the badge follows published GitHub Releases, not bare tags — a release needs a GitHub Release (v1.1.0 has none) for the update check to see it.
- Slice follow-ups live in their archives: slice-033 (verdict race on the counters; the live preview ignores the active filters), slice-036 (the version line slides below the viewport while the setup banner shows).

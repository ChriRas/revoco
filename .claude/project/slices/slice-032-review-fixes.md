# Slice 032 — Review Fixes before the Merge of PR #87

> Completed: 2026-10-15
> Commits: e7548da..9ce97fe (branch epic-003-spam-handling, folded into PR #87)
> Epic: [epic-003 — Spam Handling](./epic-003-spam-handling.md)

## What

Before PR #87 merges, the valid points of an independent review are resolved: a failing spam signal can no longer
cost a submit; the digest loses nothing after a skipped run and counts the auto-sorted rows with their purge date; the
acknowledgment log's column names what it records (`claimed_at`); AI agents get a hidden note to fill the form like a
human; the docs describe the purge of auto-sorted rows and the consciously accepted residual risk.

## Why

- § 356a: a declaration must never be lost — a signal error that 500s the submit, or a digest that skips red rows,
  works against that.
- The AI-agent risk is a real gap in slice-030's rationale (an agent fills in under 3 s, so `honeypot` +
  `fill_time_too_fast` can catch a genuine, agent-filed declaration). The human keeps the rule, steers agents with a
  hint and will sharpen the rule if genuine declarations get caught.
- The external reviewer did not know the project's decisions; each point was read against them (Decisions below).

### Walk-through

On submit `SpamScorer::firesSafely()` wraps each signal, reports an exception and scores with the rest. Each morning
`SpamDigestWindow::since()` collects everything since the last queued digest (at most 7 days back); the command stores
the new timestamp only after queueing; the auto-sorted rows appear as one count line with the earliest purge date and a
Spam-tab link. The agent hint sits next to the honeypot field, invisible to humans and screen readers.

## Decisions

- **External review read against our decisions** — H1 (auto-sorted rows vanish unnoticed): deliberate CR5 trade-off,
  but a real rationale gap for AI agents; **kept as an accepted risk** (human), with a hint for agents, the digest count
  line, 7 days to correct, and the plan to drop `fill_time_too_fast` / `render_token_invalid` from the second-signal
  set if genuine declarations show up. H2 (throwing signal): fixed. M1 (24-hour digest): fixed. M2 (spam link without
  login): kept — slice-026 design. M3 (`sent_at`): renamed. M4 (PR description): refreshed. M5 (learned network):
  overstated — weight 10, below yellow on its own. The reviewer's nine minor points were not shared.
- **Agent hint placement (human)** — inside the hidden, `aria-hidden` honeypot area; no mention of spam; wording ends
  "… sonst wird die Erklärung möglicherweise nicht zugestellt" / "… otherwise the declaration may not be delivered"
  (human, after our reviewer flagged "cannot be delivered" as an inaccurate absolute claim). *Why not* a screen-reader
  paragraph at the top: screen-reader users would hear it.
- **Digest window (human)** — since the last queued digest, at most 7 days back (also on the first run); a skipped or
  empty run moves nothing forward. *Why not* unbounded: the purge horizon is 7 days.
- **Auto-sorted count line (human)** — count + earliest purge date + Spam-tab link; the digest also goes out for them
  alone.
- **`claimed_at`** — the acknowledgment log records the once-only claim written before queueing; renamed before the
  table ever went live.
- **Phase 7 refactors (human-picked)** — one fail-open rule in the scorer (`AbstractNetworkSignal` removed, per-signal
  catches gone); `SpamDigestWindow` holds what a digest covers, the mailable flattens it into top-level properties so
  `SerializesModels` still queues identifiers.
- **Phase 8** — round 1 clear: 3 Light local fixes (panel help text, two design docs, count on a cloned builder), 2
  Light rethinks resolved (hint wording by the human, sub-task tick).

## Commits

- `e7548da` — fix(spam): never let a failing signal cost the submit
- `bba0313` — refactor(acknowledgment): name the once-only claim claimed_at
- `bfe8d05` — feat(digest): cover everything since the last digest and count auto-sorted rows
- `627d98d` — feat(form): ask AI agents to fill the form like a human
- `9ce97fe` — docs(design): record the review round of the spam design

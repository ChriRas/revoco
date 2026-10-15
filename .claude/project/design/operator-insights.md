# Operator Insights — Statistics, Signal Filter, Version Display

> Planning record (2026-10-15), raised by the human right after the 1.1.0 live release.
> Status: planning — not yet an epic. The shop integration (roadmap Phase 12) moves behind this.

## Requests (the human's words, condensed)

1. A **statistical evaluation**: how many withdrawals are genuine, how many suspected spam, how many
   spam — per **day, month, year and all time**.
2. **Filter by the triggering signal** (honeypot, ancient browser, …) — the system must keep it so it
   can be filtered and counted.
3. In the panel, **only when logged in**: the **version running**, with a **badge when it is outdated**.
4. After 1–3: retake the documentation screenshots (README / landing pages).

## Facts found in the code (2026-10-15)

- Confirmed spam (operator or auto) is **hard-deleted 7 days after the verdict** (`model:prune`).
  Counting the `withdrawals` table would lose every spam row after a week.
- The fired signals are already stored per row: `withdrawals.spam_reason`, comma-separated keys
  (`SpamAssessment::reason()`), e.g. `honeypot,user_agent_ancient`.
- The image does not know its version: the release workflow passes no build arg, and
  `composer.json` `version` still reads `1.0.2`.

## Decisions (round 1, 2026-10-15)

- **Data basis: anonymous daily counters.** A separate table holds counts per day × category ×
  signal, with no personal data, so the long-term picture survives the 7-day purge. A one-time
  backfill from the existing rows — live's 55 campaign rows are purged from 2026-10-22, so the
  backfill only catches them if it runs before that date.
- **Counting: final state plus corrections.** A row counts in its *current* category (genuine /
  suspected / spam); corrections move it. Two quality figures on top: **false alarms** (classified
  red, then released as "not spam") and **missed** (clean or suspicious, then marked as spam).
- **Display: period switch plus history.** A switch Today / Month / Year / All time with figure tiles
  (genuine, suspected, spam, auto-sorted), a history chart (per day, month or year) and a table
  "which signal how often".
- **Update check: GitHub daily, opt-out.** The scheduler queries the public GitHub Releases API once
  a day (anonymous request, no operator data sent), opt-out via `.env`. The badge shows only for a
  newer **stable** release. No external call in the request path.

## Working assumptions (to confirm or correct)

- Categories: *genuine* = clean, plus suspicious not confirmed as spam, plus released ("not spam");
  *suspected* = yellow without a verdict; *spam* = red, confirmed or auto-sorted.
- A row with several signals counts once per signal in the signal table, but once in the category
  totals.
- The running version is baked into the image at release build time (build arg from the tag).

## Decisions (round 2, 2026-10-15)

- **Statistics page:** its own navigation entry "Statistics" next to "Withdrawals"; the panel home stays the
  withdrawals list.
- **Signal filter in both places:** a multi-select "Signal" filter in the withdrawals list, and the per-signal
  breakdown on the statistics page; a click in the signal table opens the list filtered by that signal.
- **Version placement:** discreetly at the foot of the sidebar ("Revoco 1.1.0"); when a newer stable release
  exists, a coloured badge "1.2.0 available" linking to its GitHub release notes. Logged-in panel only.
- **Release slot: epic-004 `operator-insights`, counters first.** Slices: (1) daily counters + backfill + list
  signal filter, (2) statistics page, (3) version display + update badge, then the documentation screenshots.
  Slice 1 ships ahead as a small release so the backfill runs on live **before 2026-10-22** (the 55 campaign
  rows); the rest ships as 1.2.0. The shop integration (roadmap Phase 12) moves behind this epic.

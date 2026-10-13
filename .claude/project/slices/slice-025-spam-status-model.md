# Slice 025 — Spam Status Model

> Completed: 2026-10-13
> Commits: eeedb9e..90ce69e (branch epic-003-spam-handling, epic-003 autopilot run)
> Epic: [epic-003 — Spam Handling](./epic-003-spam-handling.md)

## What

Every withdrawal now carries a spam level (green / yellow / red) and an optional operator verdict ("spam" / "not spam"
with a timestamp). The operator can set the verdict in the Filament panel on a row, on the view page, or in bulk. The
list opens on an "Inbox" tab (green, yellow, and rows corrected to "not spam"); red and confirmed-spam rows sit under
a "Spam" tab. A daily `model:prune` hard-deletes rows confirmed as spam more than 7 days ago. Unconfirmed rows are never
deleted.

## Why

The level (the system's call, which the later score engine will set) and the verdict (the operator's word) are separate
columns, so a correction never loses the machine verdict and a re-score cannot undo the operator. The old `spam` bool was
migrated into the level (true to red) and dropped, which keeps today's behaviour: red rows still get no acknowledgment.
The 7 days count from the confirmation, so the operator has a full week to undo a mis-click, and "not spam" cancels the
pending purge. Both verdict actions fire a domain event that sibling slices will listen to; delivery itself is untouched.

### Walk-through

A submit with a honeypot or throttle hit stores `spam_level = red`; the listener withholds the acknowledgment for red
only, and the merchant mail and push treat any non-green level as suspicious. In the panel, the row, header and bulk
actions all call `MarkWithdrawalAsSpam` / `MarkWithdrawalAsNotSpam`. These are idempotent: they set the verdict and
`spam_verdict_at`, and fire `WithdrawalConfirmedAsSpam` / `WithdrawalMarkedNotSpam` only on a real change. The `inbox()`
and `spamFolder()` scopes let the verdict override the level for tab membership, and `Withdrawal::prunable()` selects
confirmed spam older than 7 days for the scheduled prune.

## Decisions

- **Human (plan gate `[R]`, 2026-10-13) — the amended rule is on the epic branch:** "df37446 wird auf den Epic-Branch
  cherry-gepickt". Done as `16e8ac5` on `epic-003-spam-handling`. Its `rules.md` now permits the spam status/score
  mutation and the 7-day purge of confirmed spam (never unconfirmed), and `design/spam-handling.md` carries rounds 2–4.
  This answers the former NEEDS-HUMAN question. The rest of the note (weights/thresholds, DB-IP link, 028 not split)
  concerns sibling plans and changes nothing here.
- **Sources:** levels, default view, single + bulk actions, 7-day purge of confirmed spam only, and "unconfirmed red
  never purged" come from `design/spam-handling.md` rounds 3 + 4 and the epic Vision. Never block, never purge the
  unconfirmed: the `rules.md` Tabus, as amended on 2026-10-13.
- **Judgment — two columns, not one status:** `spam_level` (the system's classification, which slice-027's score
  engine will set) and `spam_verdict` + `spam_verdict_at` (the operator's word) are stored separately. The verdict
  overrides the level for tab membership and purge. *Why:* the machine verdict stays visible for audit after a
  correction, a re-score can never silently undo the operator, and slices 026/028/029 can tell "was red, cleared" from
  "always green".
- **Judgment — replace the `spam` bool, don't keep both:** it is migrated into `spam_level` (true → red, false → green)
  and dropped, so there is one source of truth. *Why red, not yellow:* today `spam = true` withholds the acknowledgment
  (hotfix 1.0.3). Mapping it and the honeypot/throttle signals to red keeps that behaviour unchanged. Which signal maps
  to which level is slice-027's call.
- **Judgment — 7 days count from confirmation (`spam_verdict_at`), not from receipt:** the design says "confirmed
  spam … purged after 7 days". Counting from confirmation gives the operator a full week to undo a mis-click with "Not
  spam", which also cancels the pending purge.
- **Judgment — purge via Laravel `Prunable` + scheduled `model:prune`** (present in the installed Laravel 13.35),
  not a custom command. It is a hard delete. `Prunable` (not `MassPrunable`) keeps model events firing per row.
- **Judgment — delivery untouched:** channel gating per level, mail links, the digest, and re-sending the
  acknowledgment on "not spam" belong to slice-026 (`spam-delivery-routing`). This slice only ports the existing gating
  from `spam` to `spam_level` without changing behaviour. In its place it ships the two domain events as the seam where
  slice-026 (re-send acknowledgment), slice-028 (learn networks) and slice-029 (store/remove the e-mail hash) attach
  listeners.
- **Judgment — action availability:** "Mark as spam" shows when the verdict is not `spam`. "Not spam" shows when the
  verdict is `spam`, or the level is yellow or red and no "not spam" verdict exists. Both ask for confirmation (a
  "not spam" will send mail once slice-026 lands). Bulk actions skip rows where they would be a no-op.
- **Judgment — UI shape:** Filament list tabs ("Inbox" default / "Spam"); `Filament\Schemas\Components\Tabs\Tab` and
  `HasTabs::getTabs()` are verified in the vendored Filament v5.10. Yellow is highlighted by the level badge's warning
  colour, not by custom row classes, which would need a compiled panel theme. Labels: en "Clean" / "Suspicious" /
  "Spam", de "Unauffällig" / "Verdächtig" / "Spam".
- **Refactor candidate (autopilot, not applied):** add `Withdrawal::isSuspicious()` (level is not green) and `isRed()` for the repeated `spam_level !== SpamLevel::Green/Red` checks in the listener, mail envelope, mail view and `SpamActions` — one named rule instead of four comparisons.
- **Refactor candidate (autopilot, not applied):** in `SpamActions`, share the label/icon/color/modal setup between each row action and its bulk twin — the two copies of each differ only in `visible()` and the `action()` body.
- **Not included (not asked for, not invented):** who set the verdict (`verdict_by`), a "deleted on" date in the
  infolist, tab count badges, and a configurable retention period.
- **Durable capture:** no cross-cutting design knowledge beyond the above. The model is slice-scoped until it lands;
  the `delivery.md` update is a sub-task.

## Commits

- `eeedb9e` — feat(spam): model spam level and operator verdict on withdrawals
- `248acf7` — feat(panel): add inbox/spam tabs and spam verdict actions
- `7b3f325` — feat(spam): purge confirmed spam seven days after confirmation
- `90ce69e` — docs(design): describe spam levels in delivery and legal-compliance design

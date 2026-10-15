# Slice 033 — Daily Spam Counters

> Completed: 2026-10-15
> Commits: 0b3b317..1fb15a2 (branch epic-004-operator-insights, autopilot)
> Epic: [epic-004 — Operator Insights](./epic-004-operator-insights.md)

## What

Every withdrawal is now counted per receipt day in a new `withdrawal_daily_counts` table, without personal data: one total row per (day, level, outcome) with `signal = ''`, plus one row per fired signal key. The table stays current on submit and on every verdict change, a purge never reduces a count, and the migration backfills every existing row on deploy. The withdrawals list gets a multi-select "Signal" filter.

## Why

- Confirmed spam rows are purged after 7 days (live's 55 campaign rows from 2026-10-22), so the operator-insights statistics page (slice-034) needs figures that outlive the rows.
- Counting the state (level x outcome) instead of a category keeps the figures recomputable if the category definitions are corrected later.

## Walk-through

1. The migration creates the table (unique on day, level, outcome, signal; `signal` NOT NULL default `''`) and backfills it in the same `up()` with a chunked, self-contained read of `withdrawals`.
2. `App\Statistics\Outcome` (none / spam_auto / spam_operator / not_spam) and `Category`, with the mapping methods `category()`, `isAutoSorted()`, `isFalseAlarm()`, `isMissed()` that slice-034 reads.
3. `App\Statistics\DailyCountRecorder`: `record()` for a new row, `move()` for a state change; one transaction of additive upserts; any `Throwable` is reported and swallowed so counting never costs a submit or a verdict.
4. `App\Observers\WithdrawalObserver` (`#[ObservedBy]`): `created` records, `updated` moves when `spam_level` / `spam_verdict` / `spam_verdict_source` changed. No `deleted` handler.
5. `WithdrawalResource`: a multi-select `signal` filter, options from `config('revoco.spam.weights')` labelled by `SpamReasonLabels::render()`, matched comma-delimited with escaped LIKE wildcards.

## Decisions

- **Counters store the state, not the category** (2026-10-15, planning judgment):
  - The dimensions are `day × level × outcome × signal`, where `outcome` is `none` | `spam_auto` |
    `spam_operator` | `not_spam`.
  - The category, auto-sorted, false-alarm and missed figures are pure functions of (level, outcome).
  - Why: the category definitions in `design/operator-insights.md` are working assumptions "to confirm or
    correct". Once the rows are purged, a stored category could never be recomputed. A stored state survives any
    later reclassification, and at most 3 × 4 × 15 rows per day stay negligible.
  - This refines the design record's "day × category × signal" and does not change its intent.
- **Category mapping** (2026-10-15; the design record's working assumptions, adopted as the planning basis):
  - *genuine* = green without a verdict, or any level with `not_spam`. This covers the yellow released row and a
    green row marked spam and then corrected back.
  - *suspected* = yellow without a verdict.
  - *spam* = verdict spam (operator or auto, any level) or red without a verdict.
  - Unjudged red counts as spam. The working assumption says "red, confirmed or auto-sorted" and leaves unjudged
    red rows (for example honeypot-only) unplaced. They sit in the Spam tab and level red reads "spam" in round 3,
    so they are spam. This is a judgment call; correcting it changes only the mapping, never the stored data.
  - Each row counts once in the category totals.
- **Quality figures by current state** (2026-10-15; design record round 1, "final state plus corrections"):
  - *false alarm* = level red with `not_spam`. A released auto-sorted row counts here and leaves auto-sorted.
  - *missed* = level green or yellow with an operator `spam` verdict.
  - *auto-sorted* = `spam_auto` (currently auto-sorted).
  - A yellow row released as not spam is no false alarm: round 1 defines false alarms as "classified red".
- **Multi-signal counting** (2026-10-15; design record working assumption):
  - Each fired key gets its own bucket. A row with several signals counts once per signal, and once in the total
    row (`signal = ''`).
  - `''` is used instead of NULL because a SQLite unique index treats NULLs as distinct, which would break the
    upsert.
- **Day = receipt day** (2026-10-15, planning judgment):
  - Counts sit on the day of `created_at` in the app timezone (`config/app.php`: `Europe/Berlin`, also the stored
    wall time).
  - A later verdict moves the row between states on its receipt day, never onto the verdict day. "How many
    declarations came in on day X, and what are they now" stays consistent.
- **Purge never touches the counters** (2026-10-15; design record round 1): the counts have no foreign key and no
  `deleted` handler, so `model:prune` deletes only the row.
- **Recording point = model observer, synchronous** (2026-10-15, planning judgment):
  - One observer catches every writer of the spam columns: the submit with auto-sorting, both actions, bulk, and
    the mail links. A queued listener could run after a later verdict change and mis-order the decrement.
  - Verified in `vendor/laravel/framework` v13.36.0 `Model::performUpdate()`: `updated` fires after
    `syncChanges()` and before `syncOriginal()`, so `getOriginal()` still holds the old state.
- **Atomic increments via upsert** (2026-10-15; verified in v13.36.0 `SQLiteGrammar::compileUpsert`): a keyed
  `update` value is compiled as a parameter, so an `Expression` gives
  `count = count + excluded.count` (SQLite `ON CONFLICT … DO UPDATE`). A decrement is the same statement with a
  negative delta, which keeps concurrent campaign submits race-free. Buckets that fall to 0 are kept.
- **A counting failure never costs the submit** (2026-10-15; rules.md Tabus and the § 356a never-block rule, the
  same pattern as `SpamScorer`'s signal isolation): the recorder catches and `report()`s. A drift of one is
  accepted over a failed receipt.
- **Backfill as a migration, not an artisan command** (2026-10-15, planning judgment):
  - `docker/entrypoint.sh` runs `php artisan migrate --force` on every app start. The 1.1.1 deploy therefore
    backfills exactly once, with no operator step to forget before 2026-10-22.
  - Migrations run before php-fpm starts, so no submit falls between the backfill and live counting.
  - The migration is self-contained (`DB::table`, inline state derivation, no app-class import), so later
    refactors never break `migrate` on a fresh install. A parity test pins it to the live recorder.
  - It counts every existing row, including the 55 operator-confirmed red rows on live.
- **Revision P1-2 — filter labels through the one rendering point** (2026-10-15; plan-architect round 1, finding
  P1-2; `design/spam-handling.md` → Labels): the signal filter's option labels are built with
  `App\Spam\SpamReasonLabels::render($key)` per key of `config('revoco.spam.weights')` instead of a direct
  `panel.spam_signal.<key>` lookup, so the panel has no second rendering path (slice-034 uses the same helper).
  The raw-key fallback, the lang keys (en + de), the options and the query are unchanged.
- **Counts survive any row deletion — contract for slice-036** (2026-10-15; plan-architect round 1, finding P1-3,
  informational): the observer handles only `created` and `updated`; there is no `deleted` (or `forceDeleted`)
  handler and no foreign key, so deleting a withdrawal row by any path — `model:prune`, or slice-036's cleanup of
  the historical rows it creates for the screenshots — never decrements a counter. The purge-survival test pins
  this. No new scope.
- **No Eloquent model for the counters yet** (2026-10-15, planning judgment): this slice writes through the query
  builder (upserts). The read side (period aggregation, a model if useful) belongs to slice-034.
- **No personal data** (2026-10-15; design record round 1): a counter row holds day, level, outcome, signal key
  and a count, with no withdrawal ID, so nothing links back to a declaration after the purge.
- **Signal filter semantics** (2026-10-15, planning judgment; design record round 2, "multi-select Signal
  filter"):
  - OR: a row matches when it carries any of the selected signals. This is the usual multi-select reading and what
    one click from the statistics page needs.
  - The options are the keys of `config('revoco.spam.weights')`: all 14 signals, each weighted. Each label is
    `SpamReasonLabels::render($key)`, and a key without a label shows raw.
  - The match is `',' || spam_reason || ','` LIKE `'%,<key>,%'`, so one key can never match inside another.
  - The filter applies inside the active tab (Inbox / Spam).
- **URL contract for slice-034** (2026-10-15; verified in `vendor/filament/filament` v5.10.2
  `ListRecords`: `#[Url(as: 'filters')]`, `#[Url(as: 'tab')]`): the filter is named `signal`, so the statistics page
  links to `…/withdrawals?tab=<inbox|spam>&filters[signal][values][0]=<key>`. A test covers the URL form.
- **Release** (2026-10-15; epic decision "Counters ship first"): the slice carries the CHANGELOG `[1.1.1]` entry.
  Merging to `main`, tagging `1.1.1` and deploying to live before 2026-10-22 happen after the epic's review
  checkpoint (`after slice-033`) and are the human's steps, outside this slice. `composer.json`'s stale `version`
  is left to slice-035 (version-badge).
- **Build deviations** (2026-10-15, Phase 4):
  - The counter table has no surrogate `id` (the unique key is the identity), exactly the columns the plan lists.
  - The failure-isolation test breaks the counter write by dropping the table instead of binding a throwing
    fake: the recorder itself catches, so a fake would bypass the catch under test.
  - Zero-count buckets stay in the live table (decided), so the parity test compares non-zero buckets only.
  - `SpamStatusModelTest` and `AcknowledgmentResendTest` roll back by migration count; both steps were raised by
    one for the new migration.
  - The live preview above the list defaults to the newest row of the tab regardless of filters (existing
    design), so the URL-form test makes the matching row the newest.
- **Refactor candidate (autopilot, not applied):** move `signalOptions()` and `whereSignalIn()` out of `WithdrawalResource` into a small `App\Spam` helper next to `SpamReasonLabels` — slice-034's statistics page links to the same signals and may want the same option list.
- **Refactor candidate (autopilot, not applied):** give `DailyCountRecorder::buckets()` a small `Bucket` value object instead of the four-key array shape — it is passed through three methods and the keyed-array form is verbose.
- **Scope guard** (2026-10-15): no statistics UI, no aggregation queries, and no change to scoring, purge or the
  withdrawal row. Counter rows are a separate table, so the immutability rule (rules.md Tabus) is untouched.

## Commits

- `0b3b317` — feat(statistics): count withdrawals in anonymous daily counters
- `38f9cc6` — feat(panel): filter withdrawals by spam signal
- `1fb15a2` — docs(changelog): add the 1.1.1 entry for the daily spam counters

## Follow-ups

- R1-1 Light · Rethink · The verdict actions check-then-write without a lock: two near-simultaneous verdict requests on one row (a double-submitted mail-link POST, panel plus link) both call `move()` and leave counter drift; a conditional update in the Mark actions would close it, outside this slice's scope
- R1-2 Light · Rethink · The live preview's default row ignores the active filters (`WithdrawalPreview::newestIn($activeTab)`, slice-031 design), so slice-034's deep link `?tab=…&filters[signal][values][0]=<key>` can show a preview row without the selected signal

# Slice 034 — Statistics Page

> Completed: 2026-10-15
> Commits: 06f32a2..98326de (branch epic-004-operator-insights, autopilot)
> Epic: [epic-004 — Operator Insights](./epic-004-operator-insights.md)

## What

A "Statistics" entry in the operator panel (after "Withdrawals") with a period switch (Today / Month / Year / All time),
six figure tiles, a stacked history chart and a per-signal table whose counts open the withdrawals list filtered by that
signal and period. Everything is read from slice-033's anonymous daily counters, so the figures outlive the 7-day spam purge.

## Why

- The operator wants to see how much spam arrives, how well the scoring sorts it and where it errs (false alarms, missed),
  without the purged rows.
- Reading the stored state (level x outcome) through slice-033's `Outcome` mapping keeps every category definition in one place.

## Walk-through

1. `App\Models\WithdrawalDailyCount` is the read-side model over the counter table (`totalRows()` / `signalRows()` scopes).
2. `StatisticsPeriod` (calendar periods in the app time zone) and `Granularity` (day / month / year buckets) feed
   `SpamStatistics`: `totals()` and `history()` sum total rows, `signals()` sums per-signal rows; each groups by
   `level, outcome` and maps through `Outcome` in PHP. Day and month buckets are zero-filled.
3. `App\Filament\Pages\Statistics` is a plain Page with `HasFiltersForm` (`period` toggle buttons, URL-persisted), hosting
   three non-discovered widgets: `StatisticsOverview` (6 stats), `StatisticsHistoryChart` (stacked bar), `SignalBreakdownTable`
   (array records, links to `WithdrawalResource` index with tab, signal filter and `date_range`).
4. `panel.statistics.*` in `lang/en/panel.php` and `lang/de/panel.php`; `WithdrawalResource` gets `$navigationSort = 1`.
5. Tests: `SpamStatisticsTest` (reader, every level x outcome state against the mapping) and `StatisticsPageTest` (access,
   navigation order en/de, period switch, widget figures, links, German labels, empty state, end-to-end submit + verdict).

## Decisions

- **Counter contract read from slice-033's plan** (2026-10-15, cross-slice; rewritten for plan-architect finding P1-1 —
  source: `.claude/plans/slice-033-daily-spam-counters.md`):
  - C1: table `withdrawal_daily_counts` — `day` (date), `level` (`green|yellow|red`), `outcome`
    (`none|spam_auto|spam_operator|not_spam`, slice-033's `App\Statistics\Outcome`), `signal` (string, `''` default),
    `count`; unique on `day, level, outcome, signal`; no personal data, no withdrawal id.
  - C2: the counters store the **state**, not the category. Per state a **total row** (`signal = ''`) counts each
    declaration once; one **per-signal row** per fired key counts a declaration once per signal. The reader therefore
    keeps the two apart: tiles and chart sum total rows only, the per-signal table sums per-signal rows only — mixing
    them would count a declaration once plus once per signal.
  - C3: category (genuine / suspected / spam) and the auto-sorted / false-alarm / missed flags are pure functions of
    `(level, outcome)` in slice-033's `App\Statistics` category mapping. `SpamStatistics` groups by `level, outcome` and
    maps every group through it; it holds no category definition of its own, so a later correction of the mapping
    (slice-033 calls its definitions working assumptions) changes the page without touching this slice. The exact
    class/method names are whatever slice-033 lands; this slice calls them as built.
  - C5: counts sit on the **receipt day** (`created_at`, app timezone); a verdict moves a declaration between states on
    that day — so the list's `date_range` filter on `created_at` selects the same rows the counts stand for.
  - C6: slice-033 ships **no counter model** and writes through the query builder. This slice adds the read-side
    `App\Models\WithdrawalDailyCount` (slice-033 hands it over: "a model if useful belongs to slice-034"), and tests seed
    through it — never through a model of slice-033's.
  - C7: the withdrawals list has a multi-select table filter named `signal` (OR over the selected keys, inside the active
    tab), reachable as `?tab=<inbox|spam>&filters[signal][values][0]=<key>` (Filament 5 `ListRecords`:
    `#[Url(as: 'filters')]`, `#[Url(as: 'tab')]`, verified in vendor v5.10.2).
  (C4 of the first draft — a category split per signal stored by slice-033 — is gone: the split comes from mapping the
  per-signal rows' `level, outcome`.)
- **Revision for plan-architect finding P1-1** (2026-10-15) — applied in full: own counter model for reading and
  seeding, grouping by `level, outcome` (+ bucket / `signal`) mapped through slice-033's mapping, total rows and
  per-signal rows read apart. Touched: Vertical Slice Definition, Effect 1, Test Strategy setup and reader test,
  Sub-Tasks, the counter-contract and tab-link decisions.
- **Built-in Filament widgets on a plain Page with `HasFiltersForm`** (2026-10-15) — `StatsOverviewWidget`, `ChartWidget`
  and `TableWidget` with array records fit all three parts; the page-filter wiring (`filters` → `pageFilters`) is
  Filament's own (`Page::getWidgetsSchemaComponents()`, v5.10.2). Not a `Dashboard` subclass: the panel deliberately has
  no dashboard, its home is the withdrawals list (design round 2). Widgets set `$isDiscovered = false`.
- **Default period: Month** (2026-10-15, judgment call) — a fuller first picture than Today, which is often 0.
- **Periods are calendar periods, not rolling windows** (2026-10-15, judgment call) — Month = the current calendar month,
  Year = the current calendar year, Today = the current day, all in the app timezone (`APP_TIMEZONE`, default
  Europe/Berlin), All time = from the first counter day. No browsing to earlier months: the entry asks for a period
  switch, and the history chart shows the past.
- **Chart buckets follow the design's "per day, month or year"** (2026-10-15, judgment call) — Month → days of the month,
  Year → months of the year, All time → years. Today has no finer grain in daily counters, so it shows the current
  month per day like Month. All time shows a single bar until a second year of counters exists — accepted rather than
  inventing an adaptive grain.
- **Chart series = the three categories, stacked** (2026-10-15) — auto-sorted is a part of spam, so a fourth stacked
  series would count those rows twice; auto-sorted, false alarms and missed are tiles only.
- **Per-signal table splits counts by category, each count links to its tab** (2026-10-15, judgment call) — the list
  splits Inbox (unjudged green/yellow, released) from Spam (red, confirmed); a single link per signal would land in the
  Inbox tab by default and hide e.g. every honeypot row. Genuine / suspected counts link to Inbox, spam counts to Spam.
  This lines up exactly with slice-033's mapping and the list's scopes (`Withdrawal::scopeInbox`: `not_spam` or
  unjudged green/yellow = genuine + suspected; `scopeSpamFolder`: verdict spam or unjudged red = spam).
- **List links carry the period's date range** (2026-10-15, judgment call) — via the list's existing `date_range` filter,
  so "honeypot · this month" opens this month's rows, not all of them; All time sets no date range. The list can still
  show fewer rows than the counter (purged spam) — the table note says so.
- **No README / landing-page change in this slice** (2026-10-15) — the screenshots and their text are slice-036
  (`docs-screenshots`); the release's docs sync covers the feature description.
- **Build deviations** (2026-10-15, Phase 4):
  - `StatisticsPeriod` exposes `firstDay()` / `lastDay()`, not `from()` / `until()`: a backed enum already owns a static `from()`.
  - The period filter is not persisted in the session (`persistsFiltersInSession()` is false): the URL is the only state, so a bare visit always opens Month.
  - The note under the signal table is a table footer row (`contentFooter()` takes a view that receives the columns), shown only while the table has rows.
  - Filament widgets are lazy, so a full HTTP page render holds only placeholders; the widget figures are asserted by mounting each widget with `pageFilters`, the period URL by `Livewire::withQueryParams()`.
  - Live-preview caveat for the deep links (slice-033 follow-up R1-2) is untouched: the list's preview still shows the newest row of the tab regardless of the signal filter.
- **Refactor candidate (autopilot, not applied):** extract the `StatisticsPeriod::fromFilter($this->pageFilters['period'] ?? null)` line that all three widgets repeat into one small trait (for example `ReadsStatisticsPeriod`) next to them — one place for how a widget reads the page filter.
- **Refactor candidate (autopilot, not applied):** give `SpamStatistics::totals()` / `history()` a small value object (`StatisticsTotals`, `HistoryBar`) instead of the keyed arrays with their six / five literal keys — the shapes are repeated in the reader, the widgets and the tests.
- Durable capture: no cross-cutting design knowledge beyond these lines; the counter contract (stored state, mapping
  in `App\Statistics`, total vs. per-signal rows) belongs in `.claude/project/design/operator-insights.md` — left to the
  master / slice-033, since this planner does not touch shared files during a parallel planning round.

## Commits

- `06f32a2` — feat(statistics): aggregate the daily counters per period
- `98326de` — feat(panel): add the statistics page

## Follow-ups

- none

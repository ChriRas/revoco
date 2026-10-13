# Slice 027 — spam-score-signals

> Completed: 2026-10-13
> Commits: 9adf44a..a4f3a19 (branch epic-003-spam-handling, epic-003 autopilot run)
> Epic: [epic-003 — Spam Handling](./epic-003-spam-handling.md)

## What

Every withdrawal submit is now scored by a non-blocking engine (`App\Spam\SpamScorer`). Eight weighted signals
(honeypot, throttle, render token, fill time, ancient user agent, gibberish, name without space, equal field
lengths) add up to a score; yellow >= 30 and red >= 70 sort it into `spam_level`. Score, level and the fired signal
keys are stored on the row, and the operator sees the keys as translated labels in the panel infolist, the merchant
mail and the spam digest. The consumer is still always redirected to the success page.

## Why

The honeypot and the soft rate limit were the only signals and both meant red. The epic needs a graded
classification so that suspicious but possibly real submits (yellow) are still delivered, while clear bot patterns
(red) are withheld. Behaviour signals (fill time via a signed render timestamp, user agent) catch bots that fill
the honeypot correctly. Weights and thresholds are the proposal the human accepted at the plan gate.

### Walk-through

1. `FormRenderToken` issues an encrypted render time; the form carries it in a hidden `rendered` field that survives a
   validation re-render through `old()`.
2. `app/Spam/` holds the `SpamSignal` contract, `SubmissionContext`, `SpamAssessment`, `SpamScorer` and one class
   per signal under `Signals/`. The signals are the container tag `spam.signals` (`AppServiceProvider`), the
   extension point for slice-028/029.
3. `config/revoco.php` -> `spam` holds weights, thresholds, minimum fill time and browser floors.
4. `StoreWithdrawalController` builds the transient context and asks the scorer; `StoreWithdrawal` writes
   `spam_score` (new migration, nullable), `spam_level` and `spam_reason`. The old `evaluateSpamSignals()` is gone.
5. `SpamReasonLabels::render()` is the one display point for `spam_reason`; en + de labels live under
   `panel.spam_signal`.
6. `submitCleanWithdrawal()` in `tests/Pest.php` is the shared clean-submit helper; the existing submit suites use it.
   Behaviour change covered by the tests: `throttle` alone is now yellow (delivered), not red.
7. `design/spam-handling.md` has a new "Score engine (slice-027)" section.

## Decisions

- **Human decision (plan gate `[R]`, 2026-10-13): the signal weights and thresholds below are the defaults, exactly as
  proposed** ("Gewichte/Schwellen wie vorgeschlagen"). This includes the behaviour change that `throttle` alone is
  yellow. It answers the former NEEDS-HUMAN question; `design/spam-handling.md` had listed "exact score weights and the
  yellow/red thresholds" as open, and red withholds the § 356a acknowledgment from a possibly real consumer.
- **Revision round 2 (human, plan gate `[R]`, 2026-10-13)**: the NEEDS-HUMAN line is replaced by the decision above,
  and the source note (P1-7) now records that `df37446` is on the epic branch as `16e8ac5`. The lines that pointed at
  the open question (Config sub-task, P1-5, P1-7) now point at the answer. Nothing else changed.
- **Defaults** (points; yellow ≥ 30, red ≥ 70; accepted by the human at the 2026-10-13 gate). The design principle is: red needs the honeypot or at least
  two independent strong signals, and no other single signal reaches red (an analogy to round 3's "hit alone = yellow
  only").
  - `honeypot` 100, which alone is red. This keeps hotfix 1.0.3's "honeypot → no acknowledgment".
  - `content_gibberish` 45, `render_token_invalid` 40, `fill_time_too_fast` 40.
  - `throttle` 30, which alone is yellow. **This is a behaviour change:** today a throttled row gets `spam=true` and
    its acknowledgment is withheld. Bots rotate IPs, while NAT consumers may trip it (slice-003 follow-up).
  - `field_lengths_equal` 25, `user_agent_ancient` 25, `name_without_space` 15.
  - Examples: UA + too fast = 65 (yellow, an old device with autofill); gibberish + too fast = 85 (red); gibberish +
    UA = 70 (red); a name without a space alone = 15 (green).
- **Revision round 1 (plan-architect, 2026-10-13)** applied P1-1, P1-3 and P1-4. P1-5 and P1-7 need no change here; see
  the lines below.
- **P1-1: the scorer result is `SpamAssessment`**, not `SpamVerdict`. slice-025 owns `App\Enums\SpamVerdict` (the
  operator's Spam / NotSpam verdict, cast on `withdrawals.spam_verdict`). The rename covers the Domain, Engine,
  Persistence and Controller sub-tasks. The level column and enum are now named concretely after slice-025:
  `spam_level` / `App\Enums\SpamLevel`.
- **P1-3: one label layer for `spam_reason`** (`SpamReasonLabels`, translation key `panel.spam_signal.<key>`, raw key
  as fallback). The stored value stays the raw snake_case key list, because it is machine data that slice-028/029
  append to. Only the display is translated. Sibling slices add only lang keys (en + de), never another rendering
  path. Signal keys are snake_case throughout.
  - `panel.php`, not `mail.php`, holds the labels, so the panel and the mail share one source. The mail renders
    in its own locale through `Mailable::locale()`.
  - Proposed wording (judgment call, final wording in Phase 4), en / de:
    - `honeypot`: "Hidden honeypot field filled" / "Verstecktes Honeypot-Feld ausgefüllt"
    - `throttle`: "Too many submissions from one IP address" / "Zu viele Eingänge von derselben IP-Adresse"
    - `render_token_invalid`: "Form token missing or invalid" / "Formular-Token fehlt oder ist ungültig"
    - `fill_time_too_fast`: "Form filled in implausibly fast" / "Formular unplausibel schnell ausgefüllt"
    - `user_agent_ancient`: "Outdated or missing browser identifier" / "Veraltete oder fehlende Browser-Kennung"
    - `content_gibberish`: "Name or subject looks random" / "Name oder Gegenstand wirkt zufällig"
    - `name_without_space`: "Name without a space" / "Name ohne Leerzeichen"
    - `field_lengths_equal`: "Several fields of identical length" / "Mehrere Felder mit identischer Länge"
- **P1-3 scope edge** (judgment call): besides the two named places, the label layer is applied to any other place
  that renders `spam_reason` when this slice is built (slice-026's digest, if landed). This keeps one source of truth
  and avoids raw keys in one channel and labels in another. It adds no new display.
- **P1-4: shared clean-submit helper** in `tests/Pest.php`, built on Pest's `test()` proxy to the current test case
  (verified in the vendored Pest v4.7.8, `src/Functions.php`). The helper sends a browser UA explicitly, although
  Symfony's default `HTTP_USER_AGENT` (`Symfony`, verified in `vendor/symfony/http-foundation/Request.php`) is an
  unrecognised family that `user_agent_ancient` does not fire on. Pinning a real UA keeps sibling assertions stable if
  the UA rules ever tighten.
  - The helper travels forward rather than back-dating the token, as the finding names it. Travelling back with a
    callback would reset any frozen clock to real time (`Wormhole::handleCallback` calls `Carbon::setTestNow()`).
  - The forward travel (min fill + 1 s per submit) keeps throttle loops inside the 60 s window.
- **P1-5: no change.** slice-029 pins the `abused_address` weight against this slice's table. That table (keys and
  default weights above) stays as proposed; the human accepted it unchanged at the 2026-10-13 gate, so slice-029's
  red-push list needs no re-check.
- **P1-7: context only.** See the source note at the end of this list. The human brought `df37446` onto the epic
  branch (2026-10-13 gate).
- **Scope contract with slice-025** (judgment call). slice-025 owns `spam_level`, the `SpamLevel` / `SpamVerdict`
  enums and the operator mutations. This slice owns `spam_score`, the score→level mapping at submit time, the signal
  keys in `spam_reason` and their label layer. If slice-025 already adds a score column, this slice adopts slice-025's
  name and drops its own migration for that column. The level is computed once at submit time; this slice never
  re-scores existing rows.
- **Reuse `spam_reason` for the fired keys** (judgment call) instead of a new JSON column. The panel infolist and the
  merchant mail already show it, so the operator sees "why" without new UI. The legacy values `honeypot` / `throttle`
  stay valid keys. The value is short enough for `string(255)`.
- **Encrypted, not plain-signed, render token** (judgment call): `Crypt::encryptString` is APP_KEY-bound, authenticated
  (MAC) and hides the time from the bot. A decrypt failure is the `render_token_invalid` signal, never an exception
  to the consumer.
- **The token survives validation errors** via `old('rendered', …)`. Otherwise a consumer who fixes one missing field
  and resubmits within 3 s would be flagged "too fast".
- **No "too slow" signal**: a consumer may leave the tab open for hours, so an old token never fires.
- **Data minimisation**: the UA and the IP are evaluated in memory only and never persisted (slice-003's IP rule,
  extended to the UA by analogy). Only the signal keys and the score are stored.
- **Weights in config, not in `.env` or the panel** (judgment call). The entry asks for an engine and thresholds, not
  for an operator UI. `config/revoco.php` keeps tuning reviewable in code; a panel later is a separate slice.
- **UA floors are a judgment call** (Chrome/Firefox 100 date from 2022, Safari 14 from 2020). An unknown family never
  fires, to avoid flagging unusual browsers. Phase 4 must verify the current Chrome/Firefox/Safari UA formats live (UA
  reduction keeps the major version), per the global knowledge-verification rule. The helper's browser-UA constant
  is checked in the same step.
- **Gibberish heuristic** is deliberately narrow. A consonant-run or vowel-ratio rule was rejected because German
  compounds (`Angstschweiß`) would fire. The known false positive (`iPhoneProMax`, 3 transitions) stays yellow alone.
- **UA formats verified live (Phase 4, 2026-10-13)**: Chrome stable is 156 (Google version history API) and the UA is
  reduced to `Chrome/<major>.0.0.0` (MDN User-Agent reference shows `Chrome/143.0.0.0`, Edge `Edg/143.0.0.0`, Opera
  `OPR/…`); Firefox stable is 158 with `Firefox/<major>.0` (Mozilla product-details); Safari is `Version/26.0
  Safari/605.1.15` (MDN). The floors 100/100/14 and the `Chrome/156.0.0.0` constant in `tests/Pest.php` hold. iOS
  Chrome (`CriOS/`) and Firefox (`FxiOS/`) carry neither `Chrome/`, `Firefox/` nor `Version/`, so they never fire.
- **Phase 4 judgment calls**: `Throttle::fires()` counts the submit (a rate-limiter hit) unless the limit is already
  exceeded, so a honeypot submit now counts toward the window (before, it returned early). The context carries
  `honeypotFilled` (bool) instead of the field value. `SpamAssessment::reason()` builds the stored list. The infolist
  gets no extra score entry (not asked for). The migration-rollback step counts in `SpamStatusModelTest` (3 → 4) and
  `AcknowledgmentResendTest` (2 → 3) grew by this slice's later migration. The digest's raw reason now goes through
  `SpamReasonLabels` (slice-026 landed). `WithdrawalFactory::yellow()` now carries reason `throttle` (was the
  placeholder `suspicious`) and the factory states carry a score.
- **Refactor candidate (autopilot, not applied):** split counting from judging in `Throttle` (a `fires()` that also records a rate-limiter hit is a hidden side effect; the scorer could call an optional "after assess" step for stateful signals) — it makes every signal a pure function of the context, which slice-028/029 would like to rely on.
- **Refactor candidate (autopilot, not applied):** extract the `rendered`-token scraping from the form HTML (duplicated in `submitCleanWithdrawal()` and three tests in `SpamScoreSignalsTest`) into one Pest helper — one place to change if the field markup moves.
- **Source note** (P1-7): this plan was written against commit `df37446` (design rounds 2–4 and the amended
  immutability rule in `rules.md`, from branch `fix/suppress-ack-for-spam`). Per the human's gate decision it has been
  cherry-picked onto `epic-003-spam-handling` as `16e8ac5`, verified in the checkout: `.claude/project/` is identical
  between the two commits, `design/spam-handling.md` shows rounds 1–4 and `rules.md` carries the spam amendment. The
  sources this plan cites are therefore on the epic branch before Phase 4.

## Commits

- `9adf44a` — feat(spam): score submits with weighted content and behaviour signals
- `6306c4f` — feat(spam): render spam reasons as translated signal labels
- `a4f3a19` — docs(design): document the spam score engine

## Follow-ups

- R1-3 Light · Rethink · SpamScorer::assess() does not isolate a failing signal: an exception from fires() (likely with slice-028 GeoIP/ASN lookups and slice-029 memory) would 500 the submit and lose the § 356a receipt; whether a failing signal counts as not fired and is reported, and where that rule lives (scorer or signal contract), is a design call to record before slice-028

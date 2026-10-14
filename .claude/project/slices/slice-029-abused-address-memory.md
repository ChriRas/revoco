# Slice 029 — Abused-Address Memory

> Completed: 2026-10-13
> Commits: 40d557d..6884905 (branch epic-003-spam-handling, epic-003 autopilot run)
> Epic: [epic-003 — Spam Handling](./epic-003-spam-handling.md)

## What

Confirming a withdrawal as spam now remembers an HMAC-SHA256 hash of its normalised e-mail address (trim +
lowercase, key derived from `APP_KEY`) in the new `abused_addresses` table for one year after the last confirmation. A
later submit from that address gets the `abused_address` signal (weight 30 = the yellow threshold): yellow on its own,
never red, never blocked. "Not spam" deletes the entry; the daily `model:prune` removes expired ones. No plaintext is stored.

## Why

Repeat abusers re-use addresses, but a real victim whose address was abused must still get the acknowledgment
and be able to withdraw, so a hit is only suspicion (design record rounds 2-3). The data is pseudonymous personal data,
hence the hash, the sliding one-year expiry and the legal-compliance / README notes.

### Walk-through

1. `abused_addresses` migration and `AbusedAddress` model (`MassPrunable`, `last_seen_at < now()-1 year`).
2. `AbusedAddressHasher` (normalise + HMAC under a domain-separated subkey of `APP_KEY`) and `AbusedAddressMemory`
   (`remember` / `forget` / `isKnown`, the last filtering expiry itself).
3. Two listeners, `RememberAbusedAddressOnSpam` / `ForgetAbusedAddressOnNotSpam`, on slice-025's two events. Every
   path (panel single, bulk, signed mail link) goes through `MarkWithdrawalAs(Not)Spam`, which fires them.
4. `SubmissionContext` got a nullable `email`; `StoreWithdrawalController` passes it. The `AbusedAddress` signal is appended
   to the `spam.signals` tag and catches any Throwable (fail-open, reported).
5. Weight in `config/revoco.php`, labels `panel.spam_signal.abused_address` (en + de), daily prune in `routes/console.php`.
6. Tests: `tests/Feature/AbusedAddressMemoryTest.php` (23 incl. hasher unit), docs in `legal-compliance.md` and `README.md`.
   The two existing migration-rollback tests got their step count raised by one.

## Decisions

- **Settled by sources:** the scheme is HMAC-SHA256 with an app-held key, the address is normalized by trim +
  lowercase, the expiry slides on `last_seen_at` over about one year, "not spam" removes the entry, and a hit alone is
  yellow only, never red (epic `## Decisions Made During This Epic`; design record rounds 2–3). The data is
  pseudonymous personal data, processed under legitimate interest. The operator's privacy policy must mention it, and
  Revoco never authors that text (design record round 2).
- **Source gap, flagged for the gate:** the design record's decision rounds 2–4 and the `rules.md` immutability
  amendment are in commit `df37446`, on branch `fix/suppress-ack-for-spam` only. They are **not** on
  `epic-003-spam-handling`, whose checkout still has the round-1 draft and the old immutability tabu. This plan quotes
  the decisions it needs, so its build does not depend on that commit. Sibling slices (slice-025 above all) do depend
  on it, so the human should merge or cherry-pick it into the epic branch before the build (plan-architect P1-7, a
  note for the gate; nothing in this plan changes for it).
- **Revision (plan-architect P1-3, applied):** the signal key is `abused_address` (snake_case like every other key) in
  the config weight key, `spam_reason` and the tests. Its label comes through slice-027's single rendering point as
  `panel.spam_signal.abused_address` (en + de) instead of a label place of its own. The HMAC domain-separation label
  `revoco.abused-address` is not a signal key and stays as it is.
- **Revision (plan-architect P1-4, applied):** scenarios 2, 3, 4, 5 and 9 submit through slice-027's shared
  clean-submit helper and assert the score-0 baseline, so "clean" means no signal but the address hit. The helper's
  name `cleanSubmit()` follows slice-028's usage; if slice-027 names it otherwise, the build uses slice-027's name.
- **Revision (plan-architect P1-5, applied):** the weight is pinned at 30 (decision below) and scenario 2 asserts
  the exact score; the band test is kept as 3b.
- **Judgment — key source:** the HMAC key is a subkey derived from `APP_KEY` (domain-separated), not a new env
  variable. *Why:* "app-held key" without extra operator configuration, and `APP_KEY` is already required and
  fail-fast in prod. *Trade-off:* rotating `APP_KEY` silently resets the memory. That is acceptable for a soft signal
  that expires anyway, so `APP_PREVIOUS_KEYS` is not consulted.
- **Judgment — what slides the expiry:** only a new operator **confirmation** moves `last_seen_at`; a submit that hits
  the memory does not. *Why:* the memory is "addresses seen in confirmed spam". If unconfirmed hits slid it, a real
  consumer's own submits, or unreviewed bot traffic, could keep a victim's address flagged indefinitely. Storage
  limitation favours the narrower reading.
- **Judgment — "not spam" is literal:** "not spam" on any row (confirmed earlier or not) deletes the entry for that
  row's address, even if other rows with the same address are still confirmed spam. *Why:* the design says "not spam
  removes the entry", and the operator just vouched for that address. A later confirmation re-adds it.
- **Judgment — normalization stops at trim + lowercase:** no plus-tag stripping, no dot folding. *Why:* the design
  names exactly that, and inventing provider-specific rules is out of scope (`dont-invent-requirements`).
- **Judgment — expiry is enforced at lookup:** `isKnown` checks `last_seen_at` itself, and `model:prune` only reclaims
  storage. *Why:* the signal must not depend on whether the scheduler ran.
- **Judgment — fail-open:** a lookup error drops the signal and never fails the submit (rules.md: the submit must never
  fail; § 356a never-block).
- **Judgment — hook point:** remember/forget go into slice-025's single status-transition entry point, not a model
  observer. *Why:* Filament bulk actions can use query-builder updates that skip model events. One explicit domain call
  covers panel single, panel bulk and the slice-026 mail link.
- **Judgment — Depends-On includes slice-026:** test 2 asserts the victim-protection property (a yellow hit still
  gets the acknowledgment), and test 5 covers the mail-link "not spam". Both need slice-026's channel gating and
  links. Ordering it after 025/026/027 also matches the design record's round-4 slice sequence.
- **Judgment — default weight `abused_address` = 30 (revision P1-5, replaces the earlier band-only line).** Weigh this
  at the gate together with slice-027's NEEDS-HUMAN weight question; the band test (3b) stays as the guard.
  - *Source:* round 3 says "hit alone = suspicion only … spam needs at least one further signal (a real victim who
    later withdraws for real still gets the acknowledgment)". 30 is the lowest value that still makes a hit alone
    yellow (slice-027's proposed yellow ≥ 30), so it leaves the widest margin before red (70) for the victim.
  - *At 30, a hit plus one of these single signals is red (≥ 70):* `honeypot` 100 (red on its own anyway),
    `content_gibberish` 45 (75), `render_token_invalid` 40 (70), `fill_time_too_fast` 40 (70). These are the
    bot-behaviour and content signals, so a bot that re-uses the victim's address with its usual behaviour lands red.
  - *At 30, a hit plus one of these stays yellow:* slice-027's `throttle` 30 (60), `field_lengths_equal` 25 (55),
    `user_agent_ancient` 25 (55), `name_without_space` 15 (45); slice-028's `drop_network` 30 (60), `asn_drop` 20
    (50), `learned_network` 10 (40), `tor_exit` 5 (35), `country_outside_shipping` 4 (34). These are the signals a real
    consumer can trip (NAT, an old device, a one-word name, abroad / VPN). Two of them together can still reach red
    (e.g. hit + throttle + UA = 85).
  - *Residual victim risk:* a real victim who submits within `min_fill_seconds` of rendering (browser autofill), or
    whose token is invalid (e.g. a form page cached from before slice-027's deploy, or an `APP_KEY` rotation between
    render and submit), lands red and loses the acknowledgment. At any weight from 30 to 39 the red set above is the
    same, because no proposed weight lies between 31 and 39. Raising it to 40 or more would add `throttle` and
    `drop_network`; raising it to 45 or more would add the UA / field-length signals as well, so "one further signal"
    would read as "almost any further signal".
  - *Coupling:* if the human changes slice-027's yellow threshold above 30, this default must move with it (the band
    test fails until it does).
- **Judgment (build) — hook via the two slice-025 events, not inside the Action bodies:** `RememberAbusedAddressOnSpam` and
  `ForgetAbusedAddressOnNotSpam` listen on `WithdrawalConfirmedAsSpam` / `WithdrawalMarkedNotSpam`, as slice-028's learning
  listeners do. *Why:* the two Actions are the single transition entry point and fire the events exactly once per changed
  row, so the plan's "one explicit domain call" holds, and the code follows the established pattern.
- **Judgment (build) — `SubmissionContext::$email`:** the plan did not say how the signal gets the address; a nullable
  `email` was appended to the context (after `network`, so existing constructions stay valid).
- **Judgment (build) — hasher unit test lives in `tests/Unit/Spam/`:** Pest binds the Laravel `TestCase` only to `Feature`
  and `Unit/Spam`, and the hasher reads `config('app.key')`.
- **Judgment (build) — signal fail-open is its own try/catch**, not `AbstractNetworkSignal` (whose name and docblock are
  about network data). The two are the same four lines; unifying them is a Phase 7 candidate.
- **Refactor candidate (autopilot, not applied):** extract `AbstractNetworkSignal`'s fail-open `fires()` (catch Throwable, `report`, answer false) into a neutral base such as `FailOpenSignal` that both the network signals and `AbusedAddress` extend — one definition of the "a signal must never fail the submit" rule instead of two copies, which also closes slice-027's open R1-3 for every signal that reads local data.
- **Verified:** Laravel `v13.35.0` (composer.lock) ships `Illuminate\Database\Eloquent\MassPrunable` and the
  `model:prune` command. A `scheduler` service running `schedule:work` exists in `docker-compose.yml`.
- **Durable capture:** no cross-cutting design knowledge beyond the design record. The legal-compliance update is a
  sub-task, not written during planning.

## Commits

- `40d557d` — feat(spam): remember abused addresses of confirmed spam
- `5826fec` — feat(spam): add the abused-address signal
- `6884905` — docs: document the abused-address memory

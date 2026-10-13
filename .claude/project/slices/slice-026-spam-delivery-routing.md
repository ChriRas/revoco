# Slice 026 — Spam delivery routing

> Completed: 2026-10-13
> Commits: b0d971f..fec8a3e (branch epic-003-spam-handling, epic-003 autopilot run)
> Epic: [epic-003 — Spam Handling](./epic-003-spam-handling.md)

## What

Delivery now follows the spam level. Green rows get the acknowledgment, the merchant mail (with a "mark as spam" link)
and the push. Yellow rows get the same, with a "spam suspected" mail and push marker. Red rows get nothing per row:
the operator receives one morning digest with a "not spam" link per entry. Every mail link opens a confirmation page and
only its POST changes state. A "not spam" correction (mail link, panel row or bulk) sends the withheld acknowledgment
once, with the original receipt time.

## Why

A bot campaign turned the acknowledgment into spam sent to third parties, and per-row mails and pushes for every bot row
buried the operator. Level-based gating keeps the legal record (every row is stored) while silencing the noise. Mail
scanners prefetch links, so a GET must never mutate: link, confirmation page, POST. The acknowledgment log is a separate
insert-only table because the immutability rule allows only `handled_at` and the spam status to change on the row.

### Walk-through

`SendWithdrawalNotifications` asks `ChannelPolicy::for($level)` which channels apply. The acknowledgment is claimed in
`withdrawal_acknowledgments` (`insertOrIgnore` on a unique `withdrawal_id`) before it is queued, so any later path can
tell whether it already went out. `SendAcknowledgmentOnNotSpam` listens on `WithdrawalMarkedNotSpam` and claims the same
log, which covers the mail-link, panel and bulk paths with one rule. `SpamLinks` builds `temporarySignedRoute(..., absolute: false)`
links (7 days, plus a `state` parameter equal to the row's verdict at issue) prefixed with `APP_URL`; `signed:relative`
validates path and query only, so the link works behind a TLS-terminating proxy. The GET controller shows a PII-free page,
the POST controller applies the existing `MarkWithdrawalAs*` Action only while `state` still matches and redirects back
to the public origin. `revoco:spam-digest` collects red, unresolved rows of the last 24 hours (50 listed, the rest
counted) and queues `SpamDigest`; `SpamDigestSchedule` registers it at the panel-configured time and falls back to 07:00
if the setting cannot be read. The Notifications page gained the digest-time field, and the compose `queue` and
`scheduler` services got `APP_URL`.

## Decisions

- **Revision (human, plan gate `[R]`): `df37446` is cherry-picked onto the epic branch.** The human answered the
  former NEEDS-HUMAN question (should the rounds 2–4 design record and the `rules.md` immutability amendment come onto
  `epic-003-spam-handling` before this slice is built?) with yes. The commit is on the branch as `16e8ac5`: `rules.md`
  now permits spam status/score mutations, and `design/spam-handling.md` carries rounds 1–4. The Prerequisites
  sub-task no longer stops on it. The note's other points (weights and thresholds, the DB-IP link, not splitting
  slice-028) concern other entries and change nothing here.
- **Source of truth for this plan:** `design/spam-handling.md` rounds 2–4 (links, re-send, digest, levels) as on the
  epic branch since `16e8ac5`.
- **Channel matrix** as in Effect, from design round 3. "Every non-spam merchant notification carries 'mark as
  spam'" (round 2) → green **and** yellow carry the link. "Push as today" for yellow → today's spam-suspected push
  marker.
- **Signed URL instead of a stored random token.** The entry says "signed mail links"; round 2 says "random single-use
  token". Judgment: Laravel `temporarySignedRoute` (HMAC over `APP_KEY`, expiry), made single-use by a `state`
  parameter. An action applies only while the row's status equals the status at issue, so the first successful POST
  invalidates every outstanding link for that row. No token table, no extra column. Accepted trade-off: if the row
  later returns to exactly that status, an old link works again (it is still the operator's own action).
- **Revision (plan-architect P2-1, applied): path-relative signatures.** Laravel's absolute check
  (`UrlGenerator::hasCorrectSignature`, verified in the vendored v13.32.0) signs `getSchemeAndHttpHost()` plus the path.
  The worker builds the link from `APP_URL` (`https://…`). Behind the operator's TLS-terminating proxy the app sees
  `http`, because nothing in the repo sets trusted proxies, `forceScheme` or `fastcgi_param HTTPS`
  (`docker/nginx/default.conf`), and slice-028's `TRUSTED_PROXIES` defaults to empty. So every link could 403.
  Judgment: sign and validate relative to the path (`absolute: false` + `signed:relative`) rather than impose a
  deployment requirement. The HMAC still covers path, query and expiry under `APP_KEY`. Only the host binding is lost,
  and a single-merchant instance has one host. The absolute link in the mail, the form action and the PRG redirect come
  from `config('app.url')`, so the browser stays on the public `https` origin. A test opens an https-issued link over
  plain http. Out of scope: an `APP_URL` with a sub-path (not a supported deployment today).
- **Link TTL 7 days**, a code constant and not an env knob (none was asked for; matches the purge horizon).
- **No panel login on the confirmation page.** The signature is the credential (one click from a phone). To limit what
  a leaked or forwarded link exposes, the page is PII-free (entry number, receipt time, status only), `noindex`,
  `no-referrer`, and throttled.
- **Operator-facing locale:** the confirmation page and digest render in `app.default_locale` (rules.md, slice-009). New
  copy goes into `lang/de` only, following `lang/en/mail.php`'s deliberate absence of the operator subtree.
- **Acknowledgment log as a separate insert-only table** (not a column on `withdrawals`). The amended rule allows only
  `handled_at` and spam status/score to change on the row; a write-once column would be a third mutation. The unique
  `withdrawal_id` + `insertOrIgnore` also makes a double POST or bulk + link race send at most one ack.
- **Backfill:** existing `spam_level = green` rows count as acknowledged and existing `spam_level = red` rows as not
  acknowledged. slice-025 maps the old `spam` boolean into `spam_level` (false → green, true → red) and drops the
  column, so only `spam_level` exists when this migration runs. The backfill keys on the level alone, not on
  `spam_verdict`: the ack went out at submit by level, so a green row later confirmed as spam was still acknowledged,
  and a red row marked "not spam" under slice-025 alone got no re-send.
  Judgment: the condition is "level is not red" and not "level = green". slice-027 depends only on slice-025, so it can
  land before this slice, and slice-025's listener acknowledges yellow rows (it withholds the ack for red only). With
  no yellow rows the two conditions are the same.
  Accepted: red rows from before hotfix 1.0.3 did get the ack, so a later "not spam" on one of them sends a second,
  identical ack (it shows the original receipt time).
- **Revision (plan-architect P1-2, applied):** the ack-log backfill and its trade-off now key on slice-025's
  `spam_level` (green acknowledged, red not) instead of the dropped `spam` column. The yellow case is covered too, as
  described in the Backfill line above.
- **Re-send copy unchanged:** the existing acknowledgment already states the original receipt date and time (round 2),
  so no "delayed" wording is added (no invented requirement).
- **Digest scope:** red rows received in the 24 h before the run that the operator has not yet resolved (round 3: "only
  when there was spam in the last 24 h"). Yellow rows already had a per-row mail. A missed scheduler minute skips that
  day's digest; the rows remain in the panel's spam view. Accepted rather than adding last-sent state.
- **Digest entries carry name, e-mail, receipt time and reason**, because a "not spam" decision needs them and the
  same recipient already gets the full data per row. The list is capped at 50 entries plus "… and N more", so a bot
  wave cannot produce an unbounded mail.
- **Revision (plan-architect P2-2, applied): digest reasons through slice-027's label point.** slice-026 and slice-027
  both depend only on slice-025, so either can land first. If slice-027 is first, the digest renders `spam_reason`
  through `App\Spam\SpamReasonLabels` (`panel.spam_signal.<key>`, raw-key fallback). If slice-026 is first, slice-027's
  own sub-task routes the digest through it. Either way there is one label point and never a second mapping.
- **Digest time is interpreted in `config('app.timezone')`** (compose defaults it to UTC, `.env.example` to
  Europe/Berlin), the same zone the receipt times render in.
- **Lazy schedule registration:** the digest time comes from the DB, so it must not be read on every artisan boot (a
  fresh `migrate` would fail). Builder verifies the Laravel 13 mechanism.
- **`APP_URL` on worker and scheduler:** signed links are built while the queued mail renders. Without `APP_URL` there,
  they would point at `http://localhost`, which is a defect of this slice and not optional infra. With path-relative
  signatures (P2-1), `APP_URL` sets only where the link points, not whether it validates.
- **Contract with slice-025 (parallel planning):** this slice reads only slice-025's level and verdict and calls its
  actions; it never sets them itself. Exact names are adopted from slice-025's archive at build time. The hotfix
  invariant (honeypot → no ack) requires slice-025 to map honeypot rows to red; the regression test makes a mismatch
  visible.
- **Build finding (schedule boot):** Laravel 13 resolves the `Schedule` singleton on every artisan boot (the existing
  `routes/console.php` `Schedule::command('model:prune')` already does), so any code registering an event at resolve
  time, `withSchedule` or console routing alike, reads the settings row on every boot. The read is therefore guarded in
  `App\Spam\SpamDigestSchedule::time()`: any `Throwable` (missing table, missing SQLite file) or a non-`HH:MM` value
  yields 07:00. `schedule:work` forks a fresh `schedule:run` per minute, so a changed time applies without a restart.
- **Build judgment (route/page shape):** the confirm/apply controllers take the id as an integer and look the row up
  themselves (no implicit model binding), so a purged row returns the neutral 404 view with the page headers instead of
  Laravel's default error page. The 403 for a bad or expired signature is rendered by an `InvalidSignatureException`
  renderer in `bootstrap/app.php`, limited to `spam-link.*` routes. Order: `throttle:30,1`, `SpamLinkPage` (locale and
  headers), `signed:relative`. No flash message after a POST: the redirected GET reads the new status and reports the
  link as used, which already satisfies "reports the new status".
- **Build judgment (push):** `SendNtfyPush` keeps its `bool $spam` constructor, which the listener now fills from
  `ChannelPolicy::suspected`. Renaming the property to a level would break queued jobs serialized before the deploy.
- **Build judgment (ack claim first):** the listener claims the ack-log entry before queuing the mail, so a double
  dispatch or a bulk + link race sends at most one. A queue push that fails after the claim leaves an entry without a
  mail; the row is still in the panel. Accepted (no retry state added).
- **Build judgment (digest reasons):** `App\Spam\SpamReasonLabels` does not exist on the epic branch (slice-027 has not
  landed), so the digest shows `spam_reason` as stored and the plan's label test is not written. slice-027's own
  sub-task covers routing the digest through its label point.
- **Build judgment (existing tests):** `SpamStatusModelTest` rolls back three migrations now (the two new ones sort
  after slice-025's, the settings migration shares the `migrations` table). `WithdrawalDeliveryTest`: honeypot and
  throttle rows no longer notify the merchant; the "reflects the spam status" test renders a yellow row. The
  settings migration got a `down()` so the rollback test can re-apply it.
- **For Phase 9 (rules.md, not edited here):** `rules.md` Workflow Rules still says the delivery model is
  "ack-always"; since the 1.0.3 hotfix and this slice it is level-based (`design/delivery.md` is current).
- **Panel copy:** the panel's "not spam" confirmation text now also says the acknowledgment is sent if not yet sent
  (slice-025 had flagged that it would once this slice lands).
- **Refactor candidate (autopilot, not applied):** extract the "claim the ack log, then queue `WithdrawalAcknowledgment` in the row's locale" pair, now written in both `SendWithdrawalNotifications` and `SendAcknowledgmentOnNotSpam`, into one `SendAcknowledgment` action taking the trigger, so the once-only rule has a single home.
- **Refactor candidate (autopilot, not applied):** `ShowSpamLinkController` and `ApplySpamLinkController` repeat the row lookup, the neutral 404 view and the `state` comparison; a small `ResolvesSpamLink` helper (or one controller with two methods) would keep the link rules in one place.

## Commits

- `b0d971f` — feat(delivery): gate notification channels by spam level
- `dc96afa` — feat(spam): add signed mark-as-spam and not-spam mail links
- `1861bd4` — feat(spam): re-send the withheld acknowledgment on not spam
- `12b01fa` — feat(spam): send a daily digest of withheld spam
- `122a578` — build(compose): pass APP_URL to the queue and scheduler services
- `fec8a3e` — docs(design): document spam channel gating, mail links and digest

## Follow-ups

- R1-4 Light · Rethink · `throttle:30,1` on the spam-link routes is one global bucket behind a proxy without trusted proxies, so junk requests can drain it and real links get 429; needs real client IPs (slice-028's TRUSTED_PROXIES) or an infra-side limit

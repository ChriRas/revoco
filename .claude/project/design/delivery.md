# Delivery — Async E-mail & Push

> Cross-cutting delivery architecture. Referenced by the submit (Phase 3),
> e-mails/push (Phase 4), the operator backend (Phase 5), and containerization
> (Phase 6 — the queue worker is its own container). Legal content rules live in
> [`legal-compliance.md`](./legal-compliance.md).

## Why async

The withdrawal submit must **never fail on an external dependency** (SMTP, push).
§ 356a requires the receipt to succeed; the consumer gets the success page
immediately. All delivery therefore runs **off the request**, in queued jobs on a
**database queue** + worker. Inline sending in the request is prohibited.

## Flow

`StoreWithdrawal` action (Phase 3) persists the row, then fires the domain event
**`WithdrawalSubmitted`**. Queued listeners fan out to the channels:

1. **Consumer acknowledgment** (e-mail, § 356a Abs. 4) — queued for every row whose
   `spam_level` is **not** `red`, and recorded in the insert-only `withdrawal_acknowledgments`
   log (unique per withdrawal, removed with the row). *Revised 2026-10-13* (was: always, incl.
   spam): a production bot campaign submitted real third parties' addresses, turning the
   acknowledgment into spam sent from the operator's domain. A red row that the operator
   corrects to **not spam** (mail link, panel row or bulk) gets the withheld acknowledgment once,
   showing the original receipt time; the log makes that, and any later flip, at most one mail.
   Content: receipt confirmation, the declaration content, date + time in consumer time
   (Europe/Berlin), **no advertising**. See [`spam-handling.md`](./spam-handling.md).
2. **Merchant notification** (e-mail) — sent to the configured operator address for **green and
   yellow** rows; carries the full case data, a signed **"mark as spam"** link, and for yellow
   the "spam suspected" subject prefix and warning. **Red rows get no per-row mail**; they appear
   only in the morning digest (below).
3. **ntfy push** — sent **only when `NTFY_ENABLED`** and only for green and yellow rows;
   **data-minimal: no PII** (no name/e-mail/order). A bare "new withdrawal received" notice
   (+ the spam-suspected marker for yellow, + a link to the backend). Verify the ntfy publish
   API live at build time.

Which channel a level reaches is one table in `App\Delivery\ChannelPolicy`:

| Level | Acknowledgment | Merchant mail | Push |
|---|---|---|---|
| green | queued | queued, with "mark as spam" link | plain |
| yellow | queued | "spam suspected" + link | spam-suspected marker |
| red | not queued | not queued | not published |

### Mail links and the digest

- **Signed links.** The merchant mail and the digest carry links to `/spam/{withdrawal}/{action}`
  (`action` = `spam` | `not-spam`). A GET only shows a confirmation page (entry number, receipt
  time, status; no name/e-mail/subject; `noindex`, `no-referrer`) because mail scanners prefetch
  links; only the POST on the same URL changes state, through the panel's status Actions. The
  signature (`signed:relative`) covers path and query, not scheme or host, so a link issued under
  an `https://` `APP_URL` stays valid behind a TLS-terminating proxy; the form action and the
  redirect still point at `APP_URL`. Links expire after 7 days and are single-use through a
  `state` parameter (the row's verdict at issue). The `queue` and `scheduler` services need
  `APP_URL` so links rendered by the worker point at the deployment.
- **Digest.** `revoco:spam-digest` runs daily at the time set on the panel's Notifications page
  (`NotificationSettings::$spam_digest_time`, default 07:00, app timezone). It queues one mail to
  the notification recipient for red rows from the last 24 h that have no operator verdict
  yet (up to 50 entries with a "not spam" link each, then "… and N more", plus a link to the
  panel's spam tab). With none, or no recipient, it sends nothing. *(slice-030: a second section lists the
  unjudged yellow rows with a "mark as spam" link each. slice-032: the window runs from the last queued digest, at
  most 7 days back, instead of the last 24 h, and a count line names the auto-sorted rows with their purge date —
  see `spam-handling.md` round 6.)*

The `spam_level` (`green` / `yellow` / `red`) never gates **acceptance** (the row is always
stored); downstream it decides the channels above. The operator's verdict (`spam_verdict`:
`spam` / `not_spam`, with `spam_verdict_at`) is a separate column that overrides the level for
the list tabs and the 7-day purge; "not spam" releases the withheld acknowledgment. Both verdict
actions fire a domain event (`WithdrawalConfirmedAsSpam` / `WithdrawalMarkedNotSpam`) that
listeners attach to.

## Isolation & failure

- Each channel is an independent job — a push failure must not block the e-mails,
  and vice versa.
- Transient failures (SMTP/push) **retry with backoff**; exhausted attempts land
  in `failed_jobs` for operator visibility. The already-stored submit is never
  affected.

## Configuration (env contract)

All secrets are placeholders in `.env.example` only.

- **Mail:** `MAIL_*` (direct SMTP), a configured from-address/name.
- **Merchant:** `MERCHANT_NOTIFICATION_EMAIL`.
- **Push:** `NTFY_ENABLED` (default off), `NTFY_SERVER`, `NTFY_TOPIC`, optional
  auth token.
- **Queue:** `QUEUE_CONNECTION=database`.

## Non-production safety (tabu)

Non-production environments **never** send real mail/push: `MAIL_MAILER=log`,
`NTFY_ENABLED=false`. Tests use `Mail::fake()` / `Http::fake()`.

## Deployment note (Phase 6)

The worker (`php artisan queue:work`) is a **separate long-running process** — its
own container in the generic compose stack. Operator deployment specifics stay in
the private infra repo.

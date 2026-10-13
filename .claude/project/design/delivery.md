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

1. **Consumer acknowledgment** (e-mail, § 356a Abs. 4) — sent for every row whose
   `spam_level` is **not** `red`. *Revised 2026-10-13* (was: always, incl. spam): a production bot
   campaign submitted real third parties' addresses, turning the acknowledgment into
   spam sent from the operator's domain. A false positive (real consumer flagged) still
   has the stored record; the operator sees it in the backend. Content: receipt
   confirmation, the declaration content, date + time in consumer time
   (Europe/Berlin), **no advertising**. See [`spam-handling.md`](./spam-handling.md).
2. **Merchant notification** (e-mail) — **always sent** to the configured operator
   address; carries the full case data **and the spam level** (any level but green gets the subject prefix and
   warning) for triage.
3. **ntfy push** — sent **only when `NTFY_ENABLED`**; **data-minimal: no PII**
   (no name/e-mail/order). A bare "new withdrawal received" notice (+ optional
   spam-suspected marker, + a link to the backend). Verify the ntfy publish API
   live at build time.

The `spam_level` (`green` / `yellow` / `red`) never gates **acceptance** (the row is always
stored); downstream it changes what the operator sees and, for `red`, withholds the consumer
acknowledgment. The operator's verdict (`spam_verdict`: `spam` / `not_spam`, with
`spam_verdict_at`) is a separate column that overrides the level for the list tabs and the
7-day purge, but does not yet change delivery (slice-026). Both verdict actions fire a domain
event (`WithdrawalConfirmedAsSpam` / `WithdrawalMarkedNotSpam`) that later slices listen to.

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

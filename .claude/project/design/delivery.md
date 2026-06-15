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

1. **Consumer acknowledgment** (e-mail, § 356a Abs. 4) — **always sent**, including
   for `spam`-flagged rows (legal-maximum posture; the operator accepts the small
   backscatter risk). Content: receipt confirmation, the declaration content,
   date + time in consumer time (Europe/Berlin), **no advertising**.
2. **Merchant notification** (e-mail) — **always sent** to the configured operator
   address; carries the full case data **and the `spam` status** for triage.
3. **ntfy push** — sent **only when `NTFY_ENABLED`**; **data-minimal: no PII**
   (no name/e-mail/order). A bare "new withdrawal received" notice (+ optional
   spam-suspected marker, + a link to the backend). Verify the ntfy publish API
   live at build time.

The `spam` flag is a **triage signal only** — it changes what the operator sees,
never whether the consumer acknowledgment is sent.

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

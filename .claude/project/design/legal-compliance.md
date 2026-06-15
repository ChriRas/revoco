# Legal Compliance — § 356a BGB (Electronic Withdrawal Function)

> Cross-cutting compliance model. Referenced by the form (Phase 2), submit
> (Phase 3), acknowledgment e-mail (Phase 4), and operator triage (Phase 5).
> Source: § 356a BGB (transposing EU Directive 2023/2673), promulgated
> 2026-02-05, in force **2026-06-19**, no transition period.

## Scope

Applies to B2C distance contracts concluded via an online interface where a
statutory right of withdrawal exists. The withdrawal function **complements**
existing channels (e-mail, letter) — it does not replace them.

## Mandatory data — exhaustive (§ 356a Abs. 2)

The consumer must be able to provide **or confirm** exactly these, and no more:

1. **Name** of the consumer.
2. **Contract identification** — information identifying the contract (or the
   part) to be withdrawn (e.g. order number, or a free-text description of the
   goods/service).
3. **Electronic communication means** for the receipt acknowledgment (e-mail).

Any further field (e.g. reason for withdrawal) is **optional, never mandatory** —
legal counsel advises against extra fields entirely.

### Field mapping (this app)

| Form field | Legal basis | Required |
|---|---|---|
| `name` | Abs. 2 Nr. 1 | yes |
| `email` | Abs. 2 Nr. 3 | yes |
| `subject` (goods/service free-text) | Abs. 2 Nr. 2 (contract id via description) | yes |
| `order_number` | Abs. 2 Nr. 2 (optional helper) | no |

FormRequest validation on the three required fields is **lawful** — it collects
the legally mandated information. This is distinct from unlawful obstruction
(see below).

## Never obstruct the submit

The withdrawal must be **as easy as concluding the contract**. Prohibited as a
pre-submit gate:

- Manual **captcha** (counts as obstruction).
- **Contract-existence / order-number validation** as a send blocker. A consumer
  with no match must still be able to submit (free-text fallback). Any
  contract check happens **internally, after receipt only** — never as a gate.
- **Throttling that locks out real customers.** Background throttling is allowed;
  silent spam filters are allowed.

**Consequence for this app:** honeypot and rate-limit are **signals only** — they
set a `spam` flag (+ reason), they never reject. Every submission is stored so
the receipt can be proven. The `spam` flag governs downstream e-mail gating
(Phase 4), not acceptance.

## Two-stage flow (§ 356a Abs. 1 & 3)

1. **Entry button** labelled "Vertrag widerrufen" (or equivalent) — constantly
   available, prominently placed, easily accessible on every subpage, never
   behind a login/footer link. *In this app:* the merchant integrates this
   button to link to the form URL; providing the button across the shop is
   **operator integration**, out of app scope.
2. **Input page** (the form) → **confirmation button** labelled
   **"Widerruf bestätigen"** (or equivalent unambiguous wording), good and
   legible. *This is the submit button label — must be honored in Phase 2 markup.*

## Receipt acknowledgment (§ 356a Abs. 4)

On activating the confirmation function, the trader must **without undue delay**
send a receipt acknowledgment on a **durable medium** (→ e-mail, Phase 4)
containing at least: the content of the declaration + **date and time in the
consumer's local time**. It confirms **receipt of the declaration only**, not the
withdrawal itself. **No advertising** in the acknowledgment.

- The Phase 3 **success page is the on-screen confirmation only**; the durable
  proof is the Phase 4 e-mail.
- Timestamps are stored/displayed in **Europe/Berlin** (single-merchant, German
  consumers ⇒ consumer's local time).

## Descoped (intent.md Non-Goals)

- **Partial withdrawal** — provided for by law (item-level selection), but
  descoped: the free-text `subject` covers "which goods", merchant handles
  manually.
- **Language switcher** — multilingual provision is expected in each
  contract-conclusion language; i18n is prepared, switcher is post-launch.

## Data protection

The operator is the **controller**. Persist the minimum: the four form fields +
`locale` + a boolean `spam` flag (+ reason). **Do not persist the IP** —
rate-limit transiently via cache.

### Retention & erasure (OPEN — deferred)

Two obligations pull in opposite directions and are **not yet resolved**:

- The withdrawal is the operator's **legal proof** that the receipt was given
  (§ 356a) — it argues for retention.
- The data subject may have an **erasure / access right** (GDPR) — it argues for
  deletion on request.

Decision deferred (surfaced during slice-005 planning). The operator backend is
therefore **read-only + a "handled" status** — no in-panel edit/delete, so the
record's integrity is protected by default. Erasure, if needed, is currently a
manual DB operation; a proper retention policy (retention period + a controlled
erasure path) is a candidate for a later slice. Revisit before public release.

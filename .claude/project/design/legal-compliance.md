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

## Imprint & privacy policy (footer legal pages)

> Decided in a planning dialogue on 2026-07-01. Governs the footer legal links
> (currently `imprint_url` / `privacy_url` in `config/revoco.php`, today external-
> only) and the new internal legal pages. The footer **visual** redesign (Design 16
> hover-expand mark) is a separate, first slice; the legal-content mechanism below
> is its own follow-on slice set. Pairs with the AGPL §13 source link (see below).

**Core principle — Revoco never authors substantive legal text.** No Lorem Ipsum,
no AI output published as-is, no "ready-made" imprint/privacy text that could read
as valid. The operator is the **controller** and bears the liability. The app
ships the **mechanism + structure**; the operator supplies the **content**; a hard
warning enforces it. (Consistent with the project rule "don't invent legal
requirements".)

### Provision model (decided — storage revised 2026-07-01)

Storage moved from `.env` to **DB / Filament settings** — the operator edits content
in the panel, not in files (see [`configuration.md`](./configuration.md)). The
*provision* principles are unchanged:

- **Imprint → operator content in the panel, rendered internally + multilingual.**
  Structured fields vs. free rich text is decided at the imprint slice, **after the
  mandatory field set is verified against current law** (basis moved TMG → DDG; do
  **not** derive fields from training memory). Labels/headings are i18n keys; the
  operator data (name, address, …) is locale-independent.
- **Privacy policy → operator rich-text content per language, rendered internally.**
  No mounted file, no placeholder text, no AI output published as-is.
- **Per language + fallback chain.** Content exists per enabled locale; if a locale
  has no text, the system falls back along the operator-defined chain (e.g. fr → en).
- **Per-page external-link override** as a fallback for operators who host their
  texts elsewhere (e.g. the shop). When set, the link replaces the internal page.
  Not the default — internal-first.

### Imprint mandatory fields — § 5 DDG (verified 2026-07-01, primary source)

Basis: **§ 5 DDG** (Digitale-Dienste-Gesetz; replaced § 5 TMG on 2024-05-14, editorial
change only). Must be *leicht erkennbar, unmittelbar erreichbar, ständig verfügbar*
(two-click rule → a footer link satisfies it). Violation = Ordnungswidrigkeit, up to
**€50,000** (§ 33 Abs. 2 Nr. 1 DDG). Source: gesetze-im-internet.de/ddg/__5.html.

§ 5 Abs. 1 fields:
1. **Name + address** (Anschrift, no P.O. box); for legal entities also **legal form**,
   **authorized representative**, and capital details if capital figures are stated.
2. **Fast electronic contact incl. e-mail.** Post-EuGH (C-298/07): e-mail is required
   plus **a second fast channel** (phone, or a contact form answered promptly).
3. **Supervisory authority** — only for activities requiring official authorization.
4. **Commercial/other register + register number** — where entered.
5. **Regulated-profession details** — chamber, statutory job title + state, professional
   rules + how to access them.
6. **VAT-ID (§ 27a UStG) or Wirtschafts-IdNr. (§ 139c AO)** — if held.
7. Companies **in liquidation/winding-up** — the corresponding statement.

Not applicable to the withdrawal form: § 18 MStV (journalistic-editorial content).
Shop-level duties (VSBG dispute-resolution notice; the EU ODR platform was shut down in
2025) belong on the merchant's main site — if counsel wants them here, they go in the
free-form addendum below.

**Design consequence — imprint = hybrid.** Structured settings for the core (Nr. 1, 2,
4, 6) with **i18n labels + locale-independent operator data**; optional structured
(Nr. 3, 5, 7); plus a **per-language free-form addendum** for anything else. The S4
completeness check verifies the *mandatory* structured fields are non-empty. The
**privacy policy** carries no structured fields — it is **per-language rich text**.

### Operator warning & setup gate (decided)

- **Filament panel:** a **red warning** when a page has **neither** internal content
  **nor** an override link — the operator is nagged to fill it in.
- **Public form (while legal content is missing):** a **non-blocking** "setup pending
  — operator, please log in" banner is prepended to the form; the form stays fully
  functional and submittable. § 356a is absolute — a live-but-misconfigured shop must
  never have its withdrawal blocked, and we cannot distinguish a fresh install from a
  misconfigured live site (no login-history flag), so a blocking setup screen is ruled
  out. (Decided 2026-07-01.)
- Both **disappear automatically** once required content exists. Gate on **config
  completeness**, not on "operator ever logged in" (open decision — see
  [`configuration.md`](./configuration.md)). A configured consumer page never shows
  operator nags.

### Withdrawal scope — what can be withdrawn (operator-configurable)

The operator declares which contract/goods types they actually offer (e.g. goods,
services, digital content, contracts) so the form copy is specific instead of one
generic catch-all sentence. **The exact taxonomy must be verified against the BGB
withdrawal categories before implementation — do not invent it.**

**Hard guardrail — display only.** Scope toggles shape **wording/help text only**;
they **never gate the submit** and **never remove the free-text fallback**. A consumer
whose case matches none of the operator's categories must still be able to submit
(see *Never obstruct the submit* above). Restricting categories as a send blocker
would be unlawful obstruction.

### Guardrails

- **No third-party assets on consumer pages.** The footer redesign and legal pages
  must not pull external resources (e.g. Google Fonts CDN) — that leaks the
  visitor's IP to a third party (GDPR). Self-hosted / system font stack + inline
  SVG only. (The form layout already inlines its CSS for the same posture.)
- **AGPL-3.0 §13.** The footer keeps a reachable source-code link (`source_url`).
  The Design-16 mark satisfies the offer as long as it is reachable — the
  `aria-label`/link is exposed to assistive tech even before the hover-expand.

### AI skills (separate track, deploy-time only)

The envisioned skills (website/design adoption; privacy-policy scrape + translate)
are **authoring aids run at deploy time**, not runtime: they produce a **draft** the
operator must **review and release** — never auto-published. They do not block the
footer or legal-page slices.

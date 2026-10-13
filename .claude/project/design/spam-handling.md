# Spam Handling — Draft (planning, 2026-10-13)

> Status: **draft for a planning dialogue** — options and constraints, not yet decisions.
> Trigger: a real bot campaign against a production deployment (details in the operator's
> private infra repo). Legal frame: [`legal-compliance.md`](./legal-compliance.md); delivery
> model: [`delivery.md`](./delivery.md).

## Observed attack pattern (generic)

- Bots load the form, fill **every** field incl. the honeypot with random strings, and use
  **real third-party e-mail addresses** (victims). IPs rotate per submit across datacenter
  ranges; user agents are ancient browsers.
- The honeypot caught 100 %. The harm is **downstream**: each spam row triggers the merchant
  mail, the push, and the § 356a acknowledgment **to the victim** — the app becomes the
  attacker's mail cannon (sender-reputation + GDPR risk for the operator).

## Hard constraints

- **Never block the submit** (§ 356a; manual captcha = obstruction). Every submit is stored.
  "Silent spam filters are allowed" — filtering governs *what happens after* receipt.
- **No geo-blocking of access.** Geo-Blocking Regulation (EU) 2018/302 Art. 3 forbids blocking or
  restricting access to the online interface by nationality/residence/location; a consumer
  abroad or on a VPN must still be able to withdraw. Geo/ASN may only be a **signal**.
- **Blacklists never block** — an abused victim address may later belong to a real withdrawal.
- GET links in mails are **prefetched by scanners** (e.g. Microsoft Defender Safe Links): a
  one-click GET must never mutate state — link → confirmation page → POST.

## Building blocks (candidates)

| # | Block | Effect | Notes |
|---|---|---|---|
| A | **Notification gating by confidence** | high-confidence spam → no merchant mail / no push (or digest) | biggest operator relief; pure app change |
| B | **Acknowledgment gating** | high-confidence spam → no ack to the (victim) address, or delayed ack | stops the mail cannon; legal trade-off vs. "always ack" posture (honeypot false positive = real consumer without ack) |
| C | Spam/Not-spam **link in the merchant mail** | random token, confirm page + POST, single-use, expiring | needs an allowed mutation of `spam` (today only `handled_at` may change) |
| D | Panel: **mark spam / not spam + bulk**, spam hidden by default | default table filter excludes spam; spam view separate | same immutability amendment |
| E | **Retention for confirmed spam** | auto-purge after N days | spam rows hold victims' addresses; ties into the open retention question |
| F | **More signals** (score instead of bool) | content heuristics (random-string fields, no space in name), min fill time, datacenter ASN, country outside shipping list, IP/e-mail seen in confirmed spam, ancient UA | local GeoIP/ASN DB (e.g. DB-IP Lite, CC BY 4.0, attribution) — no third-party lookup at runtime |
| G | **Blocklist feeds** | Spamhaus DROP/ASN-DROP (JSON, daily), Tor exit list, datacenter ranges | signal only; refreshed by the scheduler |
| H | **Edge rate-limit** (reverse proxy) | throttle POST per IP/range | infra-side, operator repo; must not lock out real customers |

## Decisions (planning dialogue 2026-10-13, round 1)

- **Hotfix first (B):** spam-suspected rows no longer get the acknowledgment sent to the
  submitted address — the app must not act as a spam sender. Merchant mail + push unchanged in
  the hotfix. Released as a patch, then deployed staging → live.
- **Ack gating (long-term):** suppress for high-confidence spam (no delay/veto model).
- **Operator info for spam:** no per-row mail/push — a **daily digest mail** ("N spam entries",
  link to the backend).
- **IP-derived data:** resolve **country + ASN locally** and persist them on the row; the raw IP
  is still never persisted.

## Open questions (for the planning dialogue)

- Which confidence level suppresses which channel (merchant mail / push / ack)?
- Ack for honeypot rows: suppress, delay with operator veto, or keep sending?
- Persist IP-derived data (country, ASN) on the row, or evaluate transiently only?
- Is a blocklist (IP, ASN, e-mail) operator-curated, feed-driven, or both?
- Retention period for confirmed spam; does "handled spam" disappear or move to a trash view?

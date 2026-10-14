# Epic 003 — Spam Handling

> Completed: 2026-10-15 (started 2026-10-13)
> Slices: 5/5 landed · autopilot run on epic-003-spam-handling, plus two follow-up slices (030, 032) on the same branch
> Merge: 44da19f (Merge pull request #87 from ChriRas/epic-003-spam-handling)

## Vision

Bot campaigns abuse the withdrawal form with real victims' e-mail addresses: the operator drowns
in notifications, and the app must never act as a spam sender (the 2026-10-13 hotfix `1.0.3`
already withholds the acknowledgment for spam-flagged rows). End state: every submit is still
stored and never blocked (§ 356a), but a non-blocking spam score sorts it green / yellow / red;
red rows get no acknowledgment and no per-row mail or push — only a daily digest. The operator
confirms or corrects with one click (mail link or panel action, single or bulk); confirmed spam
vanishes from the default view and is purged after 7 days, and a "not spam" correction re-sends
the withheld acknowledgment. Out of scope: any blocking, captcha or geo-blocking of access, edge
rate-limits (operator infra), and third-party lookups at request time.

After the human's local hands-on test and an independent review, the epic also sorts out the
campaign's unambiguous pattern automatically (honeypot plus a second bot signal), lists suspected
rows in the digest, and guarantees that neither a failing signal nor a skipped digest run loses a
declaration.

## Slices (5/5)

- [slice-025 — spam-status-model](./slice-025-spam-status-model.md) — three levels plus the operator's verdict, Inbox / Spam tabs, single + bulk actions, 7-day purge of confirmed spam.
- [slice-026 — spam-delivery-routing](./slice-026-spam-delivery-routing.md) — channels gated by level, signed single-use mail links, daily digest, the withheld acknowledgment re-sent once on "not spam".
- [slice-027 — spam-score-signals](./slice-027-spam-score-signals.md) — the non-blocking score engine with content and behaviour signals and yellow / red thresholds.
- [slice-028 — network-geo-signals](./slice-028-network-geo-signals.md) — local DB-IP country / ASN, shipping countries, Spamhaus DROP / ASN-DROP, Tor exits, learned networks; the IP is never stored.
- [slice-029 — abused-address-memory](./slice-029-abused-address-memory.md) — HMAC-hashed addresses of confirmed spam for one year, a hit alone yellow only.

Follow-up slices on the same branch (not decomposition entries):

- [slice-030 — spam-test-followups](./slice-030-spam-test-followups.md) — auto-sorting (honeypot + bot signal), icon row actions, yellow digest section, link success page, German framework messages, Filament asset CI check, dev workers.
- [slice-032 — review-fixes](./slice-032-review-fixes.md) — scorer isolates failing signals, digest since the last digest (max 7 days) with an auto-sorted count line, `claimed_at`, hint for AI agents, docs.

Related, own PR #88: [slice-031 — live-preview](./slice-031-live-preview.md) — the change request CR3 from the same hands-on test.

## Epic Decisions

- **Design record** (2026-10-13) — all planning decisions (rounds 1–4: levels, channel gating, digest, retention, signals, legal constraints) live in `design/spam-handling.md`, extended by rounds 5 (slice-030) and 6 (slice-032).
- **Immutability amended** (2026-10-13) — `rules.md` Tabus: spam status / score mutable, confirmed spam purged after 7 days, declaration content stays immutable; extended in slice-030 to auto-sorted definite spam.
- **Abused-address memory** (2026-10-13) — HMAC-SHA256 with an app-held key, normalised address, sliding 1-year expiry; a hit alone = yellow only, never red.
- **Never block** (2026-10-13) — geo / ASN / blocklists / hash hits are score signals only (§ 356a, Geo-Blocking Reg. (EU) 2018/302 Art. 3).
- **Review only at epic end** (2026-10-13) — the operator's choice for this autopilot run; the a5 sign-off was answered `[N]` and the branch landed through PR #87 instead (`main` is PR-only).
- **Network signals kept** (2026-10-14) — questioned by the human, kept after the live-campaign replay: no effect while the honeypot catches the campaign, but they lift half of it to red if the bot stops filling the honeypot (confirmed again on 2026-10-15).

## Open follow-ups

- Docs sync (README and the GitHub page, bilingual) before 1.0.0.
- Watch the auto-sorted rows: if genuine (e.g. AI-agent-filed) declarations show up, sharpen the auto-sorting rule (`design/spam-handling.md` round 6).
- Production: set `TRUSTED_PROXIES` in the private infra repo (per-IP throttle and network signals need the real client IP; slice-026 R1-4).
- slice-031's follow-up: the "Bearbeitet" filter and the detail page's status field do not yet treat confirmed spam as needing no handling.

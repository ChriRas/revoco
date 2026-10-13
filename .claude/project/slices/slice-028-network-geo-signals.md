# Slice 028 — Network and geo signals

> Completed: 2026-10-13
> Commits: df7ebb1..27f84fa (branch epic-003-spam-handling, epic-003 autopilot run)
> Epic: [epic-003 — Spam Handling](./epic-003-spam-handling.md)

## What

Every submit now resolves the client IP locally (DB-IP Lite MMDB) to country + ASN + ASN organisation and
persists those three values on the `withdrawals` row; the IP itself is never stored. Five network signals join slice-027's
tagged list and only ever add to the score: `drop_network` 30, `asn_drop` 20, `learned_network` 10, `tor_exit` 5,
`country_outside_shipping` 4 (sum 69, below red 70, guarded by a config-invariant test). A daily scheduler job
(`revoco:network-data:refresh`, 04:15) downloads DB-IP country + ASN, DROP v4/v6, ASN-DROP and the Tor exit list into
`storage/app/private/network/` with temp-file + atomic rename; a failing source keeps its old file. The panel gets a
"Network signals" settings page (shipping countries, per-source status, "Refresh now") and the withdrawal view shows
country + `AS<n> <org>` with the DB-IP attribution link.

## Why

Spam submits come from identifiable networks, and a geo/ASN/feed signal is a cheap, local, non-blocking
classifier (§ 356a: the receipt is never prevented). Country, Tor and learned ASNs hit legitimate consumers, so the
weights cap at yellow. Learning is ASN-only (the raw IP is never persisted) and needs 3 confirmed rows within 90 days.
`TRUSTED_PROXIES` is in scope because behind a reverse proxy `$request->ip()` is the proxy and every lookup would
otherwise resolve a private address.

### Walk-through

(1) `bootstrap/app.php` + `.env.example` + `docker-compose.yml` + `deployment.md`: env-driven trusted
proxies, default empty. (2) `maxmind-db/reader` ^1.14 and the MaxMind test MMDBs under `tests/fixtures/network/`.
(3) `config/revoco.php` `network` block and the five weights. (4) `App\Network\{NetworkInfo, NetworkLookup,
MmdbNetworkLookup, NetworkData}`: the lookup never throws, unknown data resolves to nothing. (5) Migrations: three
columns on `withdrawals`, `spam_network_hits`, the `network_signals` settings group. (6) Five signals on
`AbstractNetworkSignal` (catches `Throwable`, reports, answers false), `SubmissionContext::$network`, controller and
`StoreWithdrawal` pass the `NetworkInfo`. (7) `NetworkDataRefresher` + `RefreshNetworkDataCommand` + `RefreshNetworkData`
job; schedule + daily `model:prune` of `SpamNetworkHit`. (8) Learning listeners on `WithdrawalConfirmedAsSpam` /
`WithdrawalMarkedNotSpam`. (9) `ManageNetworkSignals` page, `CountryNames` (ICU), withdrawal view/table additions, lang
keys en + de. (10) Docs: README, `legal-compliance.md`, `spam-handling.md`. (11) Tests: `NetworkSignalsTest`,
`NetworkSignalLabelsTest`, `NetworkDataRefreshTest`, `SpamNetworkLearningTest`, `NetworkSignalSettingsPageTest`, helpers
in `tests/Pest.php`. Phase 5 verified by command (5/5 checks, round 1). Not covered by a test: the `bootstrap/app.php`
env parsing itself (the proxy tests set `TrustProxies::at('*')` directly).

## Decisions

- **Build deviations (slice-028 Phase 4):**
  - Fixture IPs: the vendored MaxMind test DBs hold no GB address that also has an ASN. Tests use the published
    `89.160.20.128` (country SE, AS29518 "Bredband2 AB"), so the shipping list is `['SE']` for the clean baseline and
    `['DE']` for the "outside shipping" case, instead of the plan's GB. Intent is unchanged.
  - Existing migration-step tests (`AcknowledgmentResendTest`, `SpamStatusModelTest`) roll back a fixed `--step` count
    that this slice's three new migrations (columns, hits table, settings) push up (3 -> 6, 4 -> 7); `PurgeConfirmedSpamTest`
    matched a cron column width that the new `15 4 * * *` entry widens. Adjusted minimally.
  - The settings migration carries a `down()` (`delete`), as slice-026's does, since rollback tests re-run it.
  - `AbstractNetworkSignal` is the base of the five signals: `fires()` catches any `Throwable`, reports it and answers
    false, so a failing lookup/setting/table never reaches the submit (slice-027 R1-3: the scorer still does not
    isolate signals itself; left as that slice's open follow-up). `CountryOutsideShipping` resolves its settings in
    `fires()`, not in a constructor, so a settings failure cannot break the container resolution.
  - `Select` of countries uses ext-intl ICU data (`App\Support\CountryNames`); `symfony/intl` is not installed.
  - Verified live 2026-10-13 against the real sources: `revoco:network-data:refresh` ran green (DB-IP 2026-10, 1771 CIDRs,
    436 ASNs, 1207 Tor exits). The data lands in the gitignored `storage/app/private/network/`.
  - `composer require` triggers Filament's post-update asset publish, which rewrote tracked `public/` assets; those were
    restored, they are not part of this slice.
- **DB-IP attribution placement (human decision, plan gate 2026-10-13):** DB-IP's licence page (verified 2026-10-13)
  requires a link back to DB-IP.com "on pages that display or use results from the database". The human decided:
  the "IP Geolocation by DB-IP" link goes **only** on the panel pages (withdrawal view + settings) and in the README,
  **not** on the public consumer form / success page. Sub-tasks and tests match: the link is on both panel pages and
  in the README, and a test asserts that the public form and success page do not carry it.
- **Revision round 2 (human, plan gate `[R]`, 2026-10-13):** note "df37446 wird auf den Epic-Branch cherry-gepickt;
  Gewichte/Schwellen wie vorgeschlagen; DB-IP-Link nur Panel + README; 028 nicht splitten". Applied:
  - The DB-IP question is answered (decision line above). Its open question is removed.
  - P1-6: the human decided to keep slice-028 as **one slice**. It is not split.
  - slice-027's weights and thresholds were accepted unchanged, so this plan's weight table stays as it is.
  - P1-7: `df37446` was cherry-picked onto the epic branch (source-gap line below).
- **Revision round 1 (plan-architect, 2026-10-13):**
  - P1-3 applied: the five keys get `en` + `de` labels under `panel.spam_signal.<key>`, as lang keys only, through
    slice-027's single rendering point (infolist + merchant mail, raw-key fallback). The wording is neutral: it states
    what was observed, never the source's name or a verdict on the consumer. The labels are also seen by the merchant
    in the mail, and a real consumer may sit behind any of these networks. A label sub-task and a label test were added.
  - P1-4 applied: every submit assertion now runs through slice-027's shared clean-submit helper, so
    `render_token_invalid`, `fill_time_too_fast` and the UA signal stay silent. The "all five together" case is now
    pinned exactly (score 69, yellow, not red) and no longer only "not red". Each submit test asserts a zero-score
    baseline first. Should the helper lack a hook for `REMOTE_ADDR` / `X-Forwarded-For`, this slice extends it with
    an optional parameter (judgment call: one helper, not two).
  - P1-5: no change, as the finding says. The weight table (`drop_network` 30, `asn_drop` 20, `learned_network` 10,
    `tor_exit` 5, `country_outside_shipping` 4) stays as planned, because slice-029 pins its own weight against it.
  - P1-6 (note, not applied by design): the plan is not split here. Splitting is the human's call at the gate. The
    natural cut is (a) trusted proxies + `NetworkLookup` / DB-IP geo/ASN + the three columns + shipping countries +
    `country_outside_shipping`, then (b) the feed refresh (DROP, ASN-DROP, Tor) + its three signals + learned networks.
  - P1-7 (note, context only): see the source-gap line below. The human decides at the gate how `df37446` reaches
    the epic branch.
- **Source gap closed (finding P1-7):** the design record's rounds 2–4 and the `rules.md` immutability amendment,
  which the epic plan cites, were only in commit `df37446` on branch `fix/suppress-ack-for-spam`. They were
  cherry-picked onto `epic-003-spam-handling` as commit `16e8ac5` (verified in the checkout 2026-10-13: it is the
  branch head, and `git diff df37446 16e8ac5` on both files is empty). This plan was written against that content, so
  nothing in it changes.
- **Dependencies:** slice-027 (score engine: `SpamSignal` contract, tagged signal list, `SubmissionContext`,
  `revoco.spam.weights`, thresholds yellow ≥ 30 / red ≥ 70 per its proposed defaults, fired keys in `spam_reason`,
  the `panel.spam_signal.<key>` rendering point and the shared clean-submit Pest helper) and
  slice-025 (`MarkWithdrawalAsSpam` / `MarkWithdrawalAsNotSpam` and their events `WithdrawalConfirmedAsSpam` /
  `WithdrawalMarkedNotSpam`, which it leaves listener-free as the sibling hook). Both names were read from the
  sibling plans of this round (2026-10-13). There is no dependency on slice-026 or slice-029. The human accepted
  slice-027's thresholds unchanged at the gate (2026-10-13), so no rescaling is needed.
- **Trusted proxies are in scope (judgment call):** nothing configures them today (`bootstrap/app.php`, nginx conf),
  so behind a reverse proxy `$request->ip()` is the proxy address, and every lookup would resolve to a private IP.
  Without this the entry's effect cannot happen in production. Default empty keeps today's behaviour. The operator
  sets `TRUSTED_PROXIES` in the private infra repo (a deploy action for the human, not a code question). Side effect:
  the existing per-IP throttle signal then keys per client instead of per proxy.
- **Network signals alone never reach red (judgment call):** country, Tor and learned-ASN hit legitimate consumers
  (abroad, VPN, privacy users, shared ISPs). Withholding the § 356a acknowledgment on location alone also sits badly
  with Geo-Blocking Reg. (EU) 2018/302. The guarantee comes from the weights, not from a scorer change (slice-027's
  scorer is a plain sum, and its signal list is meant to be extended without touching it). The five weights sum to 69
  against red 70, guarded by a config-invariant test. This mirrors round 3's "abused-address hit alone = suspicion
  only".
  - The weights are `drop_network` 30 (alone yellow: the DROP terms describe hijacked / criminal blocks),
    `asn_drop` 20, `learned_network` 10, `tor_exit` 5 and `country_outside_shipping` 4 (near-neutral; it only tips
    combinations).
  - Examples on slice-027's scale: DROP + `content_gibberish` 45 = 75 red; country + `user_agent_ancient` 25 = 29
    green; Tor + country + UA = 34 yellow.
- **Unknown data never penalises:** a missing DB, a private IP or an unresolved country/ASN → no signal. An empty
  shipping list disables the country signal (design round 3).
- **Learning = ASN only, one hit per confirmed row (judgment call):** the raw IP is never persisted (round 1), so
  "networks" are ASNs, taken from the row's `asn`. ASNs are organisation numbers, not personal data. Hits live in their
  own table keyed by `withdrawal_id` (SQLite `AUTOINCREMENT` ids are never reused), because confirmed rows are purged
  after 7 days. Per-row hits make "not spam" exact (delete that row's hit) without needing the prior verdict. The
  signal fires at **≥ 3 hits within 90 days**, so one confirmed row from a large consumer ISP cannot taint all its
  customers. This mirrors slice-029's "removed on not spam" and expiry.
- **Feeds = exactly the entry's list:** DROP (v4 + v6), ASN-DROP and the Tor bulk exit list. A datacenter-range list
  and an operator-curated blocklist in the panel (both in the design record's open questions) are **not** in this
  entry and are left out.
- **Feed facts verified live 2026-10-13:** `https://www.spamhaus.org/drop/drop_v4.json`, `drop_v6.json` and
  `asndrop.json` are NDJSON with a trailing metadata line (about 1,678 v4 records, 435 ASNs).
  `https://check.torproject.org/torbulkexitlist` is plain IPv4, one per line (about 1,200 lines, no IPv6). DB-IP
  Lite country/ASN MMDB is monthly at `download.db-ip.com/free/dbip-{country,asn}-lite-YYYY-MM.mmdb.gz` (about 4–5 MB
  gz, 8–10 MB unpacked); next month's URL 404s until it is published, hence the previous-month fallback. The image
  has zlib and bcmath (`php -m`).
- **Spamhaus terms (judgment call):** DROP is free of charge, but terms §3.2 forbid using the "Spamhaus" name in
  marketing, promotional "or any other commercial materials". Panel labels are therefore neutral and name neither the
  source nor a judgment ("IP address in a listed network", "Listed network operator"; no "criminal", no "DROP"), the
  `docs/` landing page does not name it, and the README names the
  source factually in its technical configuration section only. Terms §4.2 (keep the list current) → daily refresh.
- **Storage as files, not DB tables (judgment call):** the MMDBs are files anyway. Feeds as normalised files under
  `storage/app/private/network/` (the persistent `app_storage` volume) with temp-write + atomic rename give
  "keep the old file on failure" for free and are trivially inspectable. Lists are small (about 3k entries), so
  in-memory CIDR matching per submit is cheap.
- **First run:** there is no data until the first refresh, and the app works normally without it (no signals). The
  README tells the operator to run the refresh once; the panel's "Refresh now" dispatches it via the queue. The DB-IP
  data is not baked into the image, because images are built only on release tags and would go stale.
- **`asn_org` persisted (judgment call):** the round-1 decision says "country + ASN". The org name is the ASN's label,
  stored so the panel stays readable after the monthly DB swap. It adds no personal data.
- **Settings home:** a dedicated `network_signals` settings group + page, so it does not collide with settings that
  slice-026 (digest time) may add in parallel. If a shared "spam" settings page lands first, the field may move there
  at build time.
- **Durable capture:** no new cross-cutting design file. The verified feed/licence facts above should be promoted into
  `design/spam-handling.md` in Phase 9.
- **Refactor candidate (autopilot, not applied):** split `NetworkDataRefresher` into one small class per source (geo, drop, asn-drop, tor) behind a shared fetch/swap helper — the single class holds four download-validate-write pipelines and grows with every added feed.
- **Refactor candidate (autopilot, not applied):** let `MmdbNetworkLookup` keep its two `Reader`s open per instance instead of opening the file once per record lookup — saves repeated metadata parsing of the 8–10 MB databases on each submit.

## Commits

- `df7ebb1` — feat(network): honour trusted proxies from TRUSTED_PROXIES
- `15a6376` — feat(network): resolve country and ASN locally on submit
- `18b3f66` — feat(spam): add network signals and learn networks from confirmed spam
- `4413c86` — feat(network): refresh geo and blocklist data daily
- `d0527bd` — feat(panel): add network signals settings and network details
- `27f84fa` — docs: document network signals, trusted proxies and DB-IP attribution

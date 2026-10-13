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

## Decisions (round 2)

- **Mail links:** every non-spam merchant notification carries "mark as spam"; the daily digest
  carries "not spam" per entry. Both: random single-use token → confirmation page → POST
  (scanner prefetch must not mutate).
- **False-positive correction** ("not spam") **automatically sends the withheld acknowledgment**,
  stating the original receipt date/time.
- **Retention:** confirmed spam is hidden from the default view and **purged after 7 days**.
- **Signals (all four groups, never blocking):** content (random strings, no space in name, equal
  lengths) · network + country (datacenter ASN, country outside an operator-maintained shipping
  list, Spamhaus DROP/ASN-DROP, Tor exits) · learning from confirmed spam (networks, e-mail) ·
  behaviour (signed render timestamp → fill time, ancient UA).
- **Abused-address memory (operator idea):** keep a keyed hash of e-mail addresses seen in
  confirmed spam + timestamp; a hit raises the score; "not spam" removes the entry; entries
  expire after ~1 year. Recommended shape: HMAC-SHA256 with an app-held key (a plain hash of an
  e-mail is reversible by dictionary), normalized address (trim + lowercase), sliding expiry on
  `last_seen_at`. Still pseudonymous personal data (GDPR) → legitimate interest (abuse
  prevention), storage limitation via the expiry; the operator's privacy policy must mention it
  (Revoco never authors that text).

## Decisions (round 3)

- **Abused-address hit alone = suspicion only** — it raises the score; spam needs at least one
  further signal (a real victim who later withdraws for real still gets the acknowledgment).
- **Three levels:**
  - *green* — normal delivery.
  - *yellow (suspicious)* — acknowledgment **sent**; merchant mail flagged "spam suspected" + a
    "mark as spam" link; push as today.
  - *red (spam)* — no acknowledgment, no per-row merchant mail, no push; daily digest only, with
    a "not spam" link per entry.
- **Shipping countries:** maintained in the panel (multi-select), default `DE`; empty list
  disables the country signal.
- **Digest:** mornings (time configurable in the panel, default 07:00), sent **only when** there
  was spam in the last 24 h.

> **Built by slice-026** (spam-delivery-routing): round 2's signed mail links, the confirmation page,
> the re-sent acknowledgment on "not spam" (insert-only `withdrawal_acknowledgments` log), and round 3's
> level-based channel gating and morning digest (time configurable in the panel, default 07:00).
> Links are signed URLs made single-use by a `state` parameter, not stored tokens. See
> [`delivery.md`](./delivery.md).

## Decisions (round 4)

- **Immutability amended** (`rules.md` Tabus): besides `handled_at`, the spam status and score
  may change; rows **confirmed** as spam by the operator are purged after 7 days. The declaration
  content (name, e-mail, order, subject, receipt time) stays immutable; unconfirmed red rows are
  never purged.
- **Default panel view = green + yellow.** Red and confirmed spam live behind a "Spam" tab/filter.
  Yellow is highlighted; "spam" / "not spam" actions single + bulk.
- **Delivery as an epic** with slices: (1) status model + panel actions + 7-day purge,
  (2) mail links + digest + re-sent acknowledgment, (3) score + content/behaviour signals,
  (4) GeoIP/ASN + shipping countries + feeds, (5) abused-address hash memory.

## Open questions (for epic/slice planning)

- Blocklist sources: ~~which feeds exactly~~ settled by slice-028 (DROP v4/v6, ASN-DROP, Tor exit list). Still
  open: an operator-curated list in the panel and a datacenter-range list (not in slice-028).
- ~~Exact score weights and the yellow/red thresholds.~~ Settled by slice-027 (see "Score engine" below).
- ~~DB-IP Lite attribution: where the link back is shown~~ decided at the slice-028 plan gate: panel pages
  (withdrawal view + network settings) and the README, not the public form or success page.
- Edge rate-limit at the reverse proxy — operator infra, out of this repo.

## Score engine (slice-027)

Every submit is scored by `App\Spam\SpamScorer` and the result is stored; the score only classifies,
the submit is always stored and always redirected (§ 356a).

- **Stored on the row:** `spam_score` (nullable, legacy rows stay unscored), `spam_level`
  (`App\Enums\SpamLevel`, from slice-025) and `spam_reason`: the fired signal keys, snake_case,
  comma-separated, heaviest first, null when none fired. The level is computed once at submit time.
- **Signals** (`app/Spam/Signals`, weights in `config('revoco.spam.weights')`, code defaults, no env/panel):
  `honeypot` 100, `content_gibberish` 45, `render_token_invalid` 40, `fill_time_too_fast` 40,
  `throttle` 30, `field_lengths_equal` 25, `user_agent_ancient` 25, `name_without_space` 15.
  Thresholds: yellow >= 30, red >= 70. Red needs the honeypot or at least two strong signals; no other
  single signal reaches red. `throttle` alone is yellow (it used to be red/withheld: bots rotate IPs,
  NAT consumers may trip it).
- **Render token:** `App\Spam\FormRenderToken` is the render time, `Crypt`-encrypted (APP_KEY-bound,
  authenticated), in the hidden `rendered` field; `old('rendered', ...)` keeps it across a
  validation-error re-render. Missing, tampered or future tokens are `render_token_invalid`; there
  is no "too slow" signal.
- **User agent:** evaluated in memory only, never persisted. Floors in `user_agent_floors`: Chrome
  (also Edge/Opera, which carry `Chrome/`) 100, Firefox 100, Safari `Version/` 14 (with `Safari/`, without `Chrome/`);
  missing/empty UA and `MSIE `/`Trident/` fire; unknown families never do. Formats verified live on
  2026-10-13 (Chrome UA-reduced `Chrome/<major>.0.0.0`, Firefox `Firefox/<major>.0`, Safari
  `Version/<major>.x Safari/605.1.15`).
- **Extension point:** the signals are the container tag `spam.signals` (`AppServiceProvider::register()`).
  A new signal implements `SpamSignal` (`key()`, `fires(SubmissionContext)`), is appended to the tag, gets a
  weight under its key in `revoco.spam.weights`, and a label under `panel.spam_signal.<key>` in en + de.
  `SubmissionContext` is transient (fields, honeypot flag, render token, UA, IP, time); a sibling slice
  that needs more input adds a constructor field there.
- **Labels:** `App\Spam\SpamReasonLabels::render()` is the one rendering point for `spam_reason`
  (panel infolist, merchant mail, spam digest). Siblings add only lang keys under `panel.spam_signal`,
  never another rendering path; an unlabelled key is shown raw.
- **Test contract:** `submitCleanWithdrawal(array $payload, array $server = [])` in `tests/Pest.php` GETs
  the form, takes the `rendered` token, travels the clock forward by `min_fill_seconds + 1` and POSTs
  with a pinned current-browser UA (`CLEAN_BROWSER_USER_AGENT`). With nothing else firing it scores 0 /
  green. `$server` overrides apply to that request only (e.g. `REMOTE_ADDR`). The clock stays moved:
  six submits move it 24 s, inside the 60 s throttle window. Slice-028/029 assert their own signals on
  top of this.

## Network and geo signals (slice-028)

Five signals read the client IP **transiently** and persist only the derived `country`, `asn`, `asn_org`
(never the IP). They never block, and their weights sum to 69, below red (70): `drop_network` 30,
`asn_drop` 20, `learned_network` 10, `tor_exit` 5, `country_outside_shipping` 4. A test guards the invariant.

- **Lookup.** `App\Network\NetworkLookup` (bound to `MmdbNetworkLookup`) resolves an IP against the DB-IP Lite
  country + ASN MMDBs (`maxmind-db/reader`; DB-IP ASN is `compat=GeoLite2-ASN`). Private and reserved IPs, a
  missing or corrupt database and unknown addresses resolve to `NetworkInfo::unknown()`; the lookup never throws.
  `SubmissionContext::$network` carries the result to the signals.
- **Data files.** `revoco:network-data:refresh [--only=geo,drop,asn-drop,tor]` (daily 04:15, `withoutOverlapping`)
  writes `dbip-country-lite.mmdb`, `dbip-asn-lite.mmdb`, `drop.json`, `asn-drop.json`, `tor-exits.txt` and
  `meta.json` into `revoco.network.path` (`storage/app/private/network`), each through temp file + atomic rename.
  DB-IP: current month, previous month on 404, skipped when the stored month is current. A failing source keeps its
  file, is logged, and makes the exit code non-zero. `App\Network\NetworkData` reads the files lazily, once per
  request; a missing or unparsable file reads as empty.
- **Feed formats (verified live 2026-10-13).** Spamhaus `drop_v4.json`, `drop_v6.json` and `asndrop.json` are
  NDJSON (`{"cidr":…}` / `{"asn":…}` lines plus a trailing `{"type":"metadata","timestamp":…}`); the Tor
  bulk exit list is plain IPv4, one per line. DB-IP URL: `download.db-ip.com/free/dbip-{country,asn}-lite-YYYY-MM.mmdb.gz`.
- **Signals never throw.** Every network signal extends `AbstractNetworkSignal`, whose `fires()` catches any
  `Throwable`, reports it and answers false (the scorer itself does not isolate signals). The country signal reads
  `NetworkSignalSettings` inside `fires()`, so a settings failure cannot break the container.
- **Learning.** `spam_network_hits(withdrawal_id PK, asn, confirmed_at)`: `RecordNetworkHitOnSpam` upserts on
  `WithdrawalConfirmedAsSpam`, `ForgetNetworkHitOnNotSpam` deletes on `WithdrawalMarkedNotSpam`. `learned_network`
  fires at 3 or more hits within 90 days (`revoco.network.learning`); older hits are pruned daily (`model:prune`). The
  7-day purge of confirmed rows leaves hits alone (no foreign key).
- **Trusted proxies.** `TRUSTED_PROXIES` (comma list or `*`, default empty) feeds `trustProxies(at: …)` in
  `bootstrap/app.php`; without it `$request->ip()` is the reverse proxy and the signals see a private IP.
- **Licence and labels.** The panel labels are neutral and name neither the source nor a verdict. The README names
  Spamhaus factually in the technical section only; the `docs/` landing page does not. The DB-IP link ("IP
  Geolocation by DB-IP", https://db-ip.com) is on the panel pages and in the README.
- **Test contract.** MaxMind's public test MMDBs under `tests/fixtures/network/` stand in for DB-IP;
  `useNetworkDirectory()`, `writeNetworkFeeds()` and `shippingCountries()` in `tests/Pest.php` set a per-test
  data directory, feed files and the shipping list. `NETWORK_FIXTURE_IP_SE` resolves to SE / AS29518.

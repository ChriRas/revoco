<p align="center">
  <img src="docs/revoco-logo.svg" alt="Revoco" width="280">
</p>

<p align="center">
  A self-hosted, single-merchant electronic withdrawal form implementing the<br>
  electronic withdrawal function under § 356a BGB (mandatory from 2026-06-19).<br>
  Neutral by default, configurable per <code>.env</code>, open-source (AGPL-3.0).
</p>

---

## Legal notice

Revoco is software, not legal advice. It provides a technical implementation of the
electronic withdrawal function, but it does **not** guarantee that using it meets any
legal requirement. Whether your shop complies depends on how you integrate, configure
and operate it (button placement, withdrawal policy, imprint, privacy policy, mail
delivery, …). The operator is responsible for that — clarify it with your legal counsel.

---

## Requirements

- Docker (Engine 25+) and Docker Compose (v2.24+)
- [Task](https://taskfile.dev) (go-task) — optional, but used for all
  convenience commands

---

## Development

```bash
# First run — build image, install deps, build assets, generate key, migrate
task init

# Start the dev stack (http://localhost:8580)
task up

# Run the quality gate (composer audit + Pint + PHPStan + Pest)
task check

# Start the Vite dev server (HMR) — separate terminal
task dev
```

The dev stack uses a source bind-mount so every local edit is reflected immediately.
`task up` auto-merges `docker-compose.override.yml` (the dev overlay) with the base
`docker-compose.yml`.

---

## Production deployment

### 1. Generate an application key (once, before first boot)

```bash
docker run --rm ghcr.io/chriras/revoco:latest \
    php artisan key:generate --show
```

`ghcr.io/chriras/revoco` is the official published image. If you fork and build
your own, substitute your (lowercase) GHCR owner.

Copy the output (`base64:...`) — this becomes `APP_KEY` in your environment.
**Never** let the container generate its own key on boot; see the entrypoint.

### 2. Prepare your environment file

```bash
cp .env.example .env
# Edit .env: set APP_KEY, APP_URL, MAIL_*, OPERATOR_EMAIL, OPERATOR_PASSWORD, …
```

### 3. Boot the prod stack

```bash
docker compose -f docker-compose.yml up -d
```

The `app` container:
1. Fails fast if `APP_KEY` is missing (exits non-zero — never auto-generates).
2. Creates storage directories and the SQLite file on the persistent volume.
3. Runs `migrate --force`.
4. Warms config/route/view caches.
5. Starts `php-fpm`.

nginx (`web`) serves static files from the `app_public` shared volume and
proxies PHP requests to `app:9000`.

### 4. Provision the operator account (once)

```bash
docker compose -f docker-compose.yml exec app \
    php artisan app:operator --email=admin@example.com --password=changeme
```

Or set `OPERATOR_EMAIL` / `OPERATOR_PASSWORD` in `.env` and run `task operator`.

### 5. Optional: ntfy push notifications

```bash
# Start with the bundled ntfy service
docker compose -f docker-compose.yml --profile ntfy up -d
```

Configure `NTFY_ENABLED=true`, `NTFY_SERVER`, `NTFY_TOPIC` (and optionally
`NTFY_TOKEN`) in `.env`.

---

## Smoke test

Build the prod image and run a local end-to-end smoke test (HTTP 200 on `/`):

```bash
task smoke
```

Runs locally and in the release CI workflow.

---

## Configuration reference

Deployment configuration is via environment variables. See [`.env.example`](.env.example)
for the full list with inline documentation. Content-level settings — the legal pages
(imprint & privacy), consumer languages, withdrawal scope, the withdrawal-notification
recipient, the spam-digest time and the shipping countries — are managed by the operator in the Filament panel and persist in the database,
so changing them needs no redeploy. Key environment variables:

| Variable | Required | Default | Notes |
|---|---|---|---|
| `APP_KEY` | Yes | — | Generate with `key:generate --show`. Fail-fast if missing. |
| `APP_URL` | Yes | `http://localhost` | Full public URL incl. scheme. Also builds the links in mails, so the queue and scheduler need it too. |
| `APP_TIMEZONE` | No | `Europe/Berlin` | Time zone for withdrawal timestamps (receipt date and time). |
| `APP_THEME` | No | `neutral` | Visual theme token set (`data-theme`). |
| `DB_DATABASE` | No | `/var/www/html/storage/database/database.sqlite` | SQLite path inside container. |
| `QUEUE_CONNECTION` | No | `database` | Use `database` for the bundled SQLite queue. |
| `MAIL_MAILER` | No | `log` (safe) | Set to `smtp` in prod and configure `MAIL_*`. |
| `MERCHANT_NOTIFICATION_EMAIL` | No | — | Fallback recipient for the operator notification. The in-panel setting takes precedence; if neither is set, the imprint e-mail is used. |
| `NTFY_ENABLED` | No | `false` | Opt-in ntfy push (no PII sent). |
| `TRUSTED_PROXIES` | No | — (trust nothing) | Comma-separated proxy IPs/CIDRs, or `*`. Set it behind a reverse proxy so the client IP (not the proxy) feeds the spam signals; the proxy must send `X-Forwarded-For`. |

---

## Spam handling

Every submit is stored and the form never blocks one — no captcha, no geo-blocking. A background score sorts each
declaration into one of three levels, and the level decides what is sent:

| Level | Acknowledgment to the consumer | Operator e-mail and push | Panel tab |
|---|---|---|---|
| Clean | sent | sent | Inbox |
| Suspicious | sent | sent, flagged in the subject, with a "mark as spam" link | Inbox |
| Spam | **withheld** | none — listed in the morning digest instead, with a "not spam" link | Spam |

- **Signals.** Content patterns (random strings, a name without a space), a signed render token and the fill time,
  a hidden honeypot field, an ancient browser, the per-IP submit rate, the network signals below, and addresses from
  spam you confirmed earlier. All are evaluated locally; none blocks a submit.
- **Digest.** Daily at the time set on the panel's notifications page (default 07:00, `APP_TIMEZONE`). It covers
  everything since the previous digest, at most 7 days back: unjudged spam, unjudged suspicious declarations and a count
  of auto-sorted ones. Nothing to report, no digest.
- **Mail links.** "Mark as spam" and "not spam" are signed, single-use links that open a confirmation page first, so a
  mail scanner that opens a link changes nothing. They are built from `APP_URL`, so the queue and scheduler containers
  need it too (the bundled `docker-compose.yml` passes it on).
- **Panel.** Inbox and Spam tabs, single and bulk "spam" / "not spam" actions, and a live preview of the newest
  declaration in the current tab (it follows the row under the pointer or keyboard focus).
- **Correcting a mistake.** "Not spam" moves a declaration to the Inbox and sends the withheld acknowledgment once,
  with the original date and time of receipt.
- **Auto-sorting.** A submit that fills the honeypot *and* shows a second bot signal (ancient browser, too fast,
  missing or invalid render token, random-string content) is stored as confirmed spam right away. The digest counts these rows.
- **Retention.** Confirmed spam (by you or by auto-sorting) is deleted 7 days after the verdict; until then "not spam"
  restores it. Unconfirmed spam is never deleted automatically. The declaration content itself is never edited.

Behind a reverse proxy, set `TRUSTED_PROXIES` — otherwise every submit seems to come from the proxy, and the per-IP
rate and the network signals judge the proxy instead of the client.

---

## Network signals (country and network)

Every submit is classified with a few network signals. They only add to the spam score and never block
a submission: the country is outside the shipping countries (panel, default `DE`), the IP lies in a listed
network, the network operator is listed, the IP is a Tor exit node, and a network from which spam was
repeatedly confirmed. The lookup runs **locally**; the app does not call a third party on a submit.

- **Data sources.** Country and network (ASN) come from the free
  [DB-IP Lite](https://db-ip.com/db/lite.php) databases. The listed networks come from the Spamhaus
  DROP / ASN-DROP lists, the Tor exit nodes from the Tor Project's bulk exit list. A daily scheduler job
  (`revoco:network-data:refresh`) refreshes them into `storage/app/private/network/`; a failing source keeps
  its previous file. The operator panel (Settings, Network signals) shows the status and has a "Refresh now" action.
- **First run.** There is no data until the first refresh; the signals stay silent without it. Fill it once
  after deploying:

  ```bash
  task artisan -- revoco:network-data:refresh
  ```

  (In a prod stack: `docker compose -f docker-compose.yml exec -u www-data app php artisan revoco:network-data:refresh`; run as `www-data`, so the scheduler and the
  queue worker can later write the same files.)
- **Privacy.** The IP address is used in memory only and is never stored. The **country, the ASN and the
  network operator's name derived from it are stored** with the withdrawal. The operator's privacy policy must
  say so.
- **Attribution.** [IP Geolocation by DB-IP](https://db-ip.com). The link is also shown on the panel pages that
  display the data.

---

## Branding the form

The form is neutral by default. Its whole appearance is driven by one `--wf-*` CSS
token contract, switched via `data-theme` (`APP_THEME`). Concrete brand overlays —
real colours, fonts and logos — never ship in this public repo; they live in your
private infra repo / deployment mount (see
[`.claude/project/design/theming.md`](.claude/project/design/theming.md)).

### The `design-adoption` skill

To match a shop's look without hand-writing CSS, this repo ships a **deploy-time
Claude-Code skill**, `design-adoption` (in
[`.claude/skills/design-adoption/`](.claude/skills/design-adoption/SKILL.md)) — an
authoring tool, not an app-runtime feature. Run it inside a Claude Code session:
give it the shop's URL and it scans the site, extracts the corporate identity
(colours, fonts, logo), and produces a ready-to-place brand overlay plus a
placement report.

Under the hood it calls a deterministic Artisan command you can also run directly:

```bash
task artisan -- revoco:make-theme \
  --slug=myshop \
  --accent='#4f46e5' --fg='#1f2937' \
  --font='"Inter", system-ui, sans-serif' \
  --logo-url='https://myshop.example/logo.svg' \
  --brand-name='My Shop' \
  --output=./myshop-theme.css
```

The command validates every value against the `--wf-*` contract, rejects malformed
input, and never overrides accessibility-critical tokens (the focus ring stays
intact). The result is **operator-reviewed and operator-placed**: move the overlay
into your deployment, set `APP_THEME=<slug>`, wire the logo via `REVOCO_LOGO_URL`,
and review the rendered form before going live. Nothing is auto-published, and
brand assets are never committed to this repo.

---

## Legal pages (Impressum & privacy)

Revoco serves an Impressum (§ 5 DDG) and a privacy policy at `/impressum` and
`/datenschutz`, backed by operator-editable settings in the Filament **Legal** panel
(neutral "not configured yet" placeholders until filled). The operator is the data
controller and authors the substantive text — Revoco ships only the mechanism.

**Privacy policy and spam handling.** When you confirm a withdrawal as spam, Revoco remembers a one-way hash
(HMAC-SHA256, keyed from `APP_KEY`) of its e-mail address for one year, so a later submit from the same address is
flagged as suspicious (never blocked). Your privacy policy should cover this pseudonymous processing (legitimate
interest, abuse prevention); Revoco never writes that text for you. Rotating `APP_KEY` resets the memory.

### The `legal-extraction` skill

If the operator already publishes an Impressum and privacy policy elsewhere (their shop,
a CMS), this repo ships a **deploy-time Claude-Code skill**, `legal-extraction` (in
[`.claude/skills/legal-extraction/`](.claude/skills/legal-extraction/SKILL.md)), to
import them instead of copying field by field. Give it the two URLs and the locale; it
scrapes the pages, extracts the § 5 DDG fields and the privacy content, and hands a JSON
payload to a deterministic Artisan command:

```bash
task artisan -- revoco:import-legal --locale=de --input=./legal-payload.json
```

The command validates the payload against the settings schema (unknown key or malformed
e-mail → nothing written), sanitises scraped HTML, scopes per-locale fields to `--locale`,
and **refuses to overwrite already-populated fields** unless `--overwrite` is given — your
reviewed legal text is never silently replaced. Imported content is **live immediately**
(there is no draft state): open Filament → Legal, correct every field, and check the
rendered pages before you link to the form. Scraped or AI-extracted text can be incomplete
or wrong, and Revoco makes no legal-correctness guarantee.

---

## CI / Release

GitHub Actions are configured in [`.github/workflows/`](.github/workflows/):

- **`ci.yml`** — runs `composer audit` (security) + `Pint --test` + PHPStan (max)
  + Pest on every PR and push to `main`.
- **`release.yml`** — on a `v*` SemVer tag: builds the prod image, runs the
  smoke test, and pushes to `ghcr.io/chriras/revoco` (SemVer + `latest`). A fork
  publishes under its own lowercased owner.
- **`security-audit.yml`** — scheduled `composer audit` (~every 2 days) that opens
  an issue if a locked dependency has a known advisory. Dependabot
  (`.github/dependabot.yml`) complements it with weekly update PRs.

---

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).

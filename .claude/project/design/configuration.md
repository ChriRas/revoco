# Configuration — env vs. database (operator settings)

> Cross-cutting configuration model. Expanded in a planning dialogue on 2026-07-01.
> Supersedes the "everything via `.env`" posture for operator-editable **content**
> and **behaviour**. Pairs with [`legal-compliance.md`](./legal-compliance.md)
> (legal pages) and [`theming.md`](./theming.md) (visual tokens).
> **STATUS: proposed** — the env→DB shift is an intent-level change and needs
> `/craft:intent-update` before the epic starts; open decisions are marked below.

## The split — by nature of the value, not by topic

**Tier 1 — `.env` / deploy-time. Infrastructure, secrets, bootstrap.**
Needed before or independent of the panel, or a credential:

- `APP_KEY`, `APP_ENV`, `APP_URL`, `APP_DEBUG`; DB / queue / cache / session drivers.
- `MAIL_*` (SMTP) — see "SMTP" below.
- `OPERATOR_EMAIL` / `OPERATOR_PASSWORD` (initial admin provisioning), `BACKEND_LOCALE`
  (panel UI language).
- `APP_THEME` — selects a code/asset-level brand **overlay** (a `--wf-*` token set),
  which lives in the private infra repo. The overlay *is* code, so its selection
  stays deploy-level.
- **Content branding** — `REVOCO_BRAND_NAME`, `REVOCO_LOGO_URL`. Kept in `.env`
  (decided 2026-07-01): brand name + logo are part of the deployment's **visual
  identity**, which already lives on the deploy tier alongside the theme overlay.
  Splitting them into the DB would fragment the visual identity across two tiers;
  the existing `config/revoco.php` construction stays as-is.

**Tier 2 — database, Filament-editable. Operator content & runtime behaviour.**
What a non-technical operator changes without touching files:

- **Offered locales** — which languages the form shows, default, and fallback chain.
  (Replaces `APP_AVAILABLE_LOCALES`. Operators can only enable locales that have
  shipped UI translations **and** legal content.)
- **Legal content** — Impressum + Datenschutz, per language, + per-page external-link
  override + per-page fallback locale. Details in `legal-compliance.md`.
- **Withdrawal scope** — which contract/goods types the merchant offers. Shapes form
  copy **only**; never gates the submit (see `legal-compliance.md` obstruction rule).

Content branding (brand name, logo) is **not** here — it stays Tier 1 with the theme
overlay (visual identity travels with the deploy tier; see Tier 1 above).

Non-secret operational values (merchant notification e-mail, ntfy server/topic) **may**
move to Tier 2 for convenience; the ntfy **token** is secret-ish → keep Tier 1.

## Mechanism (verified 2026-07-01)

- Filament is **v5** (released 2026-01-16; the project is on `^5.0`).
- Use **`spatie/laravel-settings`** + the official **`filament/spatie-laravel-settings-plugin`**
  (`^5.0`). Strongly-typed settings classes in `app/Settings` (e.g. `LocaleSettings`,
  `LegalSettings`, `WithdrawalScopeSettings`, `BrandingSettings`), each with a
  migration; one Filament `SettingsPage` per group. DB-backed, typed, admin-editable.
- **Consequence:** operator content now lives in the **SQLite volume** — not the repo
  and not the private infra repo's `.env`. This is *more* consistent with the OSS
  "no operator specifics in repo" goal, **but the DB volume must now be backed up**
  (it holds the legal texts). Trade-off vs. env: env is declarative/reproducible
  (GitOps-friendly); DB is friendlier for long rich text and non-devs. For a
  single-merchant, self-hosted app aimed at non-devs, **DB+panel wins for content;
  secrets stay in env.**

## SMTP — DECIDED: stays in `.env` (Tier 1) + read-only panel status

Decided 2026-07-01. `MAIL_*` stays in env.

- It is a **credential**; SQLite is a bind-mounted file — a DB leak would expose it.
- Mail must work **from first boot** for the § 356a receipt duty, independent of panel
  state; DB-SMTP adds a failure/bootstrap surface (a misconfig leaves **no out-of-band
  channel** to tell the operator).

The panel gets a **read-only** "mail configured? last send OK?" indicator (no editing).
DB-editable SMTP is deferred — revisit only on operator demand; if ever added, use
encrypted casts (APP_KEY) + a "send test mail" action.

## Setup gate (fresh install)

Until the app is **minimally configured** (mandatory legal content present):

- The **public form** shows a **non-blocking** "setup pending — operator, please log in"
  banner (prepended; the form stays fully functional and submittable — § 356a is absolute,
  a live-but-misconfigured shop's withdrawal must never be blocked).
- The **Filament panel** shows the red warning nag (operator-facing).

Both disappear **automatically** once required content exists. **DECIDED 2026-07-01:**
gate on **config completeness**, not on "operator ever logged in" — completeness is
what is legally required and it self-heals. (No login-history flag is tracked.)

## Intent implications (needs `/craft:intent-update`)

`intent.md` currently states single-merchant config "via `.env`" and branding "via
`APP_THEME` + `.env`". The revised model is **env for infra/secrets + DB/Filament for
operator content & behaviour**. Single-merchant is unchanged; the *mechanism* changes.
Revise the two affected Architectural-Decision bullets before the config epic starts.

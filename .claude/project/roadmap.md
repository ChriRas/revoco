# Roadmap

> Long-term phases for Revoco. Detail in `intent.md`, `rules.md`, `design/`.

## Phases

### Phase 1 — Scaffold & quality gates
Laravel scaffold (latest stable), SQLite, Pint/Pest/PHPStan (level max), README (EN), Docker dev setup, `.env.example` (incl. branding/theme/delivery/ntfy variables). Git already exists.

Status: planned

### Phase 2 — Withdrawal form (neutral-first, i18n-ready)
Theme mechanism from the prototype (`data-theme`, `--wf-*`): neutral default + optional brand overlay, selected via `APP_THEME`. Static Blade form, strings as translation keys (`lang/de`), a11y (ARIA, focus, reduced-motion), honeypot. Branding (logo/copy/links) via `.env`.

Status: planned

### Phase 3 — Submit, persistence & success
FormRequest validation (the 3 mandatory fields only), store withdrawal in SQLite (Europe/Berlin timestamp, locale), success page, soft rate-limit + spam flag (signal only).

Status: planned

### Phase 4 — E-mails & push
Two mailables (consumer acknowledgment § 356a (4) + merchant notification) via direct SMTP; ntfy push client (toggleable, data-minimal). DB queue + worker.

Status: planned

### Phase 5 — Operator backend
Filament panel with a `Withdrawal` resource (list, search, detail, "handled") behind login.

Status: planned

### Phase 6 — Containerization & CI
Dockerfile + `docker-compose.yml` (generic, env-driven: web/app/queue/scheduler, SQLite volume), optional `ntfy` compose profile, CI (tests + image build to a registry). Operator-specific deployment lives in the private infra repo.

Status: planned

### Phase 7 — i18n expansion (post-launch)
More languages (en/…) as lang files + a language switcher in the form.

Status: done — English consumer language + in-form flag switcher (slice-009),
per-locale e-mail date format (slice-010).

### Phase 8 — Public release
Finalize license (AGPL-3.0), neutral default docs, public repo.

Status: planned (optional)

## Releases (optional)

| Version | Date | Highlights |
|---|---|---|
| 0.1.0 | tbd | Legal minimum: form + acknowledgment e-mail (Phases 1–4) |
| 0.2.0 | tbd | Containerization + operator backend (Phases 5–6) |

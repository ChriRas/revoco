# Intent

> What we want and why. Keep under ~80 lines — loads on every `/craft:prime`. Details in `rules.md`, the roadmap, and `design/`.

## Product Vision

Revoco is a self-hosted, **neutral-by-default** withdrawal form per § 356a BGB (mandatory from 2026-06-19), **configured per `.env` for a single merchant** — branding, copy, delivery. A consumer submits a withdrawal declaration; the system stores it and sends the legally required acknowledgment e-mail to the consumer plus a notification to the merchant. The operator is the **data controller** (not a processor). The neutral standalone design makes it **open-source** (AGPL-3.0); operator- and brand-specific configuration stays private.

## Active Goals

- Legal minimum per § 356a BGB: form → submit → success page + acknowledgment e-mail.
- Free-text mode: the consumer states the order number + affected goods themselves; the merchant reviews manually. No shop lookup.
- Neutral-first + optional brand overlay via `.env`; German at launch, but i18n-ready.
- Ships as Docker containers, deployable behind any reverse proxy; concrete deployment is operator-specific and lives in a separate private repo.

## Architectural Decisions

- **Configurable single-merchant, not multi-tenant** — one deployment = one merchant configured via `.env`; multiple merchants = multiple deployments. *Why not multi-tenant:* keeps the simplicity (no tenant_id/routing/token engine) while enabling standalone + open source.
- **Public app repo + private infra repo** — this repo is the neutral OSS app (incl. generic containers); operator deployment, real domains/secrets, and brand assets live in a separate private repository. *Why:* the public repo must never carry operator/infra specifics; clean git history from commit #1.
- **Neutral-first theming** — neutral default design, optional brand theme via `APP_THEME` + logo/copy/links via `.env`. *Why:* branding is configuration, not a code fork.
- **Open-source split (AGPL-3.0)** — code incl. generic infra templates public (placeholders only); real URLs/secrets/brand assets only in the private repo + the server `.env`. *Why:* others can use it, no secrets in the repo; AGPL guards against closed-source reuse.
- **i18n-ready, German at launch** — all user-facing strings as Laravel translation keys from day one; more languages = new `lang/` files. *Why:* retrofitting i18n is expensive, preparing it is cheap. Language switcher comes post-launch.
- **Operator is controller, withdrawals in SQLite** — simple persistence + a Filament panel. *Why not zero-retention:* unnecessary for a self-operated single merchant; simpler and yields the proof copy directly.
- **Blade, not Livewire** — a static form needs no reactivity. *Why not Livewire:* only pays off with live lookup / partial withdrawal (descoped).
- **Async e-mail + push via DB queue** — submit returns the success page immediately; mails + ntfy push run in the worker. *Why:* the submit must never fail on SMTP/push (§ 356a fallback duty).
- **Optional operator push (ntfy)** — ntfy *client* in-app, toggleable via `.env`; the ntfy *server* is operator infrastructure (optional compose profile ships for standalone users). *Why:* reusable notification without coupling the app to a server.

## Non-Goals

- No multi-tenancy (no runtime multi-brand) — branding is per-deployment config.
- No shop adapter / lookup / partial withdrawal / classification (free-text for now).
- No language switcher at launch (i18n only prepared).
- No zero-retention buffer, no captcha, no mandatory fields beyond the three legal ones.
- No operator/infra specifics in this repo (they live in the private infra repo).

## Open Questions

- Pin down the brand-overlay mechanism (private overlay vs. deploy-time mount).
- Default language(s) for the shipped neutral build vs. per-operator override.

# Slice 005 — Operator backend (Filament Withdrawal panel)

> Completed: 2026-06-16
> Commits: eca5782..7143f5c (branch slice-005-operator-backend, built in the main
> checkout without a worktree per operator request; 11 atomic commits) merged into
> main via GitHub PR #1 (merge commit 5ea2c5c, --no-ff).

## What

An authenticated, read-only operator backend:

- **Filament v5.6.7 panel** at `/admin` (major version verified live at build).
- **Operator auth** — `User implements FilamentUser`; `canAccessPanel()` returns
  true because every `User` is an operator (single-role: public registration
  closed, `app:operator` is the only creation path). Idempotent `app:operator`
  command upserts from `OPERATOR_EMAIL` / `OPERATOR_PASSWORD` (hashed);
  `.env.example` placeholders.
- **`handled_at`** nullable timestamp migration on `withdrawals` (the status field
  deferred in slice-003) + a `datetime` cast and `isHandled()` accessor.
- **Read-only `WithdrawalResource`** — table (Received in Europe/Berlin · Name ·
  Order # · "No Spam" · Handled), full-text search, handled / spam / date-range
  filters, newest-first; read-only view page exposing all fields incl.
  `spam_reason`. No create / edit / delete. "Handled" toggle (table action + view
  header) sets / clears `handled_at`.
- **Access control** — guest → login redirect (no data leak).
- **Panel UX** — Dashboard removed (login lands directly on the Withdrawals list);
  "No Spam" column with inverted icon (check = clean record); Email / Subject
  trimmed from the table (kept on the view page).
- 39 Pest/Livewire tests.

## Why

§ 356a BGB mandates a durable, tamper-resistant withdrawal record. The operator
must triage (spam signal) and close (handled) individual declarations without the
ability to alter their content. Filament delivers a production-ready admin UI with
minimal custom code; the read-only resource pattern enforces the integrity
requirement at the framework level, with the single `handled_at` mutation as the
only intentional exception.

## Decisions

- **Single-role model** — every `User` is an operator; no role flag.
  `canAccessPanel()` returns true (registration closed; `app:operator` is the only
  user-creation path). The `is_operator` flag was built then removed as redundant
  during PR review. Revisit only if a second role is ever introduced (Non-Goal).
- **Withdrawal record is read-only** — only `handled_at` is mutable; protects the
  § 356a legal record. → **promoted to `rules.md` Tabus** as a durable invariant.
- **Operator via `app:operator` (env-driven, idempotent)** — mirrors the SMTP/push
  secrets-in-env pattern; safe to re-run on every deploy; password env var can be
  removed after first run. Interactive mode deferred to slice-008.
- **`handled_at`, no `handled_by`** — single-operator context; tracking which
  operator set the flag would add complexity with near-zero value now.
- **GDPR retention/erasure deferred** — open policy in
  [`design/legal-compliance.md`](../design/legal-compliance.md): legal proof argues
  retention, the data-subject erasure right argues deletion; manual DB op for now.
- **Dashboard removed; `/.craft/` excluded from VCS** (ephemeral plugin state).

## Commits

- `eca5782` — feat(filament): install Filament v5.6.7 and register admin panel at /admin
- `4ce5813` — feat(operator): FilamentUser auth, app:operator command (+ initial is_operator migration, later removed)
- `ee10eba` — feat(withdrawal): handled_at column and isHandled() accessor
- `3150dfd` — feat(filament): read-only WithdrawalResource (list, view, search/filters, handled toggle)
- `d473fd6` — feat(operator): WithdrawalFactory, operator factory state, Pest tests
- `f85a378` — chore(slice-005): Phase 4 complete — Phase 5 handoff
- `78b7658` — fix(operator): Phase-5 UX — drop dashboard, "No Spam" column, trim table columns
- `0d6f7ef` — chore: stop tracking ephemeral .craft working dir
- `cf2c59b` — refactor(operator): final classes + strict_types on the panel provider
- `7583ca8` — fix(operator): Phase-8 review fixes (redundant confirmation, missing test)
- `7143f5c` — refactor(operator): drop redundant is_operator flag — every user is an operator
- `5ea2c5c` — Merge pull request #1 → main

Gate at close: Pint (55 files) · PHPStan max (no errors) · Pest (39 passed / 125 assertions).

## Follow-ups

> Light / awareness findings carried over from Phase 8 Review + cross-slice.

- **Operator command rework → slice-008:** `app:operator updateOrCreate` resets
  `name` to 'Operator' on every run; `handled_at` sits in `$fillable` (not
  reachable — no edit form, but worth tightening). Add `--email` / `--password`
  options + interactive prompt when both absent + a Taskfile wrapper reading `.env`.
- **Backend i18n → slice-007:** Filament chrome is English-only; add `.env`-driven
  panel locale plus a German variant (translation keys for Dashboard/Search/
  per-page/"Showing X–Y of Z", etc.).
- **`updated_at` semantics (awareness):** the `handled_at` toggle bumps
  `updated_at`, so the view page's "Last updated" reflects triage activity, not a
  content change — could mislead an auditor. Consider not surfacing `updated_at`
  or labelling it precisely. Low priority.
- **ntfy backend link (from slice-004):** now that the panel exists, the ntfy push
  could carry a `Click` / `Actions` deep-link to `/admin/withdrawals/{id}` (still
  PII-free). Candidate for a small delivery follow-up.
- **Model-layer authorization (obsoleted):** the Phase-8 note about testing that a
  "non-operator cannot toggle handled" is moot under the single-role model — no
  non-operator `User` can exist. Re-open only if roles are introduced.

## How (Diagram)

```
Guest → GET /admin ──► [Authenticate middleware] ──► /admin/login
                                                         │ credentials
Operator ─────────────────────────────────────────────► │
                              ┌──────────────────────────┘
                              ▼
                     AdminPanelProvider
                       homeUrl → /admin/withdrawals
                              │
                     ┌────────▼──────────┐
                     │  ListWithdrawals  │◄── search / filter (name, spam, handled, date)
                     │  (Filament table) │
                     └────────┬──────────┘
                              │ row click / ViewAction
                     ┌────────▼──────────┐
                     │  ViewWithdrawal   │◄── header: toggle_handled action
                     │  (infolist, RO)   │
                     └────────┬──────────┘
                              │ toggle_handled
                     withdrawals.handled_at ← Carbon::now() | null
```

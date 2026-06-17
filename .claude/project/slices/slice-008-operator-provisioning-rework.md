# Slice 008 — Operator provisioning rework (CLI options + interactive prompt + Task wrapper)

> Completed: 2026-06-17
> Commits: a86475d..4198910 (branch slice-008-operator-provisioning-rework, built in
> the main checkout without a worktree per operator request; 3 commits) merged into
> main via GitHub PR #3 (merge commit 79c85e4, --no-ff).

## What

`app:operator` is now a fully option/prompt-driven Artisan command, and the
environment coupling moved out of the command into a Taskfile wrapper.

- **`app:operator`** accepts `--email` / `--password` / `--name`; prompts
  interactively (Laravel Prompts `text()` / `password()`) for any missing credential
  on a TTY; under `--no-interaction` with a missing credential it **fails cleanly**
  (non-zero, readable error) — no hang.
- **Idempotent upsert preserves an existing operator's `name`** on update (default
  `Operator` applied only on create; `--name` honoured on create only).
- **Readable validation messages** — explicit CLI messages instead of raw lang keys
  (the `validation.email` key bug found during Phase-5).
- **Design-A separation** — the command no longer reads env; the `operator.email` /
  `operator.password` config keys are removed. A new **`task operator`** target
  (`dotenv: ['.env']`) bridges `OPERATOR_EMAIL` / `OPERATOR_PASSWORD` → `--email` /
  `--password`. `.env.example` documents both provisioning paths.
- **Withdrawal hardening** — `handled_at` removed from `$fillable` (set exclusively
  via the operator "handled" action), tightening the immutable-record invariant.

## Why

The previous command read credentials from config (which read env), coupling the
domain command to the environment and making it awkward to test and to run by hand.
Design A keeps the command pure and independently testable (creds supplied or asked)
while the env→params bridge lives only in the `task operator` wrapper for deploy
convenience. Prompting must be gated on interactivity so CI/automated deploys never
hang.

## Decisions

- **Design A — separation of concerns** (chosen over a command env-fallback): the
  command is option/prompt-driven and env-free; bridging lives in the Task wrapper.
  → **promoted to `rules.md` Code Conventions** as a general CLI-command convention.
- **Interactive only when interactive; non-interactive fails cleanly** (no hang in
  CI/deploy). → part of the same promoted convention.
- **Preserve existing operator `name` on update** (slice-005 deferred finding) —
  default name only on create.
- **`handled_at` removed from Withdrawal `$fillable`** — set only via the action;
  reinforces the read-only-record invariant (rules.md Tabu, slice-005).

## Commits

- `a86475d` — feat(operator): rework provisioning to option/prompt-driven command
- `8bd8d27` — refactor(operator): extract resolveName() from inline ternary in handle()
- `4198910` — review(operator): drop redundant min:1 rule and clarify task operator desc
- `79c85e4` — Merge pull request #3 → main

Gate at close: Pint (clean) · PHPStan max (no errors) · Pest (58 passed / 180 assertions).

## Phase 5 (agent-run verification)

Phase-5 verification was run by the agent in the dev container (not a human handoff):
create / idempotent update / **name preservation** (`name=Verify Op`, single row,
hashed password) and the non-interactive guard all confirmed. It surfaced a bug a
unit test had missed — the validation error rendered as the raw key `validation.email`
— which was fixed in-phase (explicit messages + a Pest assertion). The only check not
drivable headlessly was the real interactive TTY prompt (covered by the Pest
`expectsQuestion` test).

## Review (Phase 8)

0 Heavy / 4 Light — 2 fixed in-phase (dropped a redundant `min:1` password rule;
clarified the `task operator` description), 2 accepted (minor error-wording
difference between resolve- and validate-phase; `--name` intentionally not surfaced
in the Taskfile — create-only, user-confirmed).

## Follow-ups

- None outstanding. This slice closed the operator/​i18n deferrals from slice-005 and
  slice-007. Remaining planned work: slice-006 (containerization / CI).

## How (Diagram)

```
Deploy path (A):  .env (OPERATOR_EMAIL/PASSWORD) ──► task operator ──► app:operator --email --password
Interactive (B):  php artisan app:operator ──► (TTY) prompt email + password
                                       │
                                       ▼
                         resolveEmail()/resolvePassword()
                          option? → use · else TTY? → prompt · else → clean FAILURE
                                       │
                                       ▼
                              validate() (readable messages)
                                       │
                                       ▼
                    updateOrCreate(email) — name set on create only (preserved on update)
```

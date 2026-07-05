# Contributing to Revoco

Thanks for your interest. Revoco is a neutral, self-hosted electronic withdrawal form for
the German § 356a BGB statutory right of withdrawal.

## Ground rules

- **License** — contributions are accepted under the project's `AGPL-3.0-or-later` license.
- **Neutrality** — the public repo stays neutral: no operator/brand specifics, real
  domains, hosting details, or secrets (those live in a private infra repo). Ship
  placeholders and `.env.example` only.
- **Language** — code, comments, commit messages, and documentation are English; only
  end-user-facing UI text is localized (German default), authored as translation keys.

## Development setup

Everything runs in containers via [Task](https://taskfile.dev) — no local PHP/Node needed.

```bash
task init     # build image, install deps, generate key, migrate, build assets
task up       # dev stack on http://localhost:8580
task check    # the quality gate: Pint + PHPStan (level max) + Pest
```

## How a contribution gets merged

Revoco is maintained by a single maintainer, and the workflow reflects that:

1. **Fork** the repository (external contributors cannot push branches here directly).
2. Create a topic branch from `main` in your fork.
3. Open a **pull request** against `main`. Direct pushes to `main` are blocked by a
   repository ruleset — everything lands through a PR.
4. The maintainer reviews the PR and is the only one who merges it. There is no
   auto-merge; nothing reaches `main` without an explicit review.
5. Releases (`v*` tags) are cut exclusively by the maintainer after the change is on
   `main` — you do not need to touch version numbers or tags in your PR.

> For a **first-time contribution**, GitHub holds your CI run until the maintainer
> approves it. That is expected — nothing is wrong with your PR.

## Before you open a PR

- **Discuss large or structural changes first** — open an issue describing the problem
  before investing in a big PR, so we can agree on the direction.
- **Keep it focused** — one logical change per PR; small, reviewable diffs get merged
  faster.
- `task check` must be green (Pint, PHPStan level max, Pest).
- `task audit` should be clean (no dependency with a known security advisory) — CI
  enforces this on every PR.
- Add tests for any behavior change.
- Use [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`,
  `docs:`, `refactor:`, `test:`, `chore:`) for both commits and the PR title.
- Describe **what** changes and **why** in the PR body; link the issue it addresses
  (`Closes #123`).

## Reporting issues

Everyone is welcome to open issues — they are the best way to report a problem or
propose a change.

- **Bug reports** — include what you did, what you expected, what happened instead, and
  the exact steps to reproduce (plus relevant `.env` config and versions).
- **Feature requests / legal-scope questions** — describe the use case and, where it
  concerns § 356a BGB behavior, cite the relevant provision so the discussion stays
  grounded.
- **Security issues** — see [SECURITY.md](SECURITY.md); report them privately and do
  **not** file them as public issues.

Please search existing issues first to avoid duplicates.

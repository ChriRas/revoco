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

## Before you open a PR

- `task check` must be green (Pint, PHPStan level max, Pest).
- `task audit` should be clean (no dependency with a known security advisory) — CI
  enforces this on every PR.
- Add tests for any behavior change.
- Use [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`,
  `docs:`, `refactor:`, `test:`, `chore:`).
- Branch from `main` and open a PR — there are no direct pushes to `main`.

## Reporting issues

- **Functional bugs** — open a GitHub issue with reproduction steps.
- **Security issues** — see [SECURITY.md](SECURITY.md); please do **not** file them
  publicly.

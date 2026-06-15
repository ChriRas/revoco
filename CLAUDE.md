# Revoco

A self-hosted, single-merchant electronic withdrawal form implementing the
§ 356a BGB withdrawal function (mandatory from 2026-06-19). Neutral by default,
configurable per `.env`, open-source (AGPL-3.0).

This project uses the `craft` Claude Code plugin (Coding with Rules, Autonomy,
Feedback, Tests) for its development workflow.

## Project Knowledge

- [`.claude/project/intent.md`](./.claude/project/intent.md) — Vision, goals, architectural decisions.
- [`.claude/project/rules.md`](./.claude/project/rules.md) — Stack, conventions, deployment, tabus.
- [`.claude/project/roadmap.md`](./.claude/project/roadmap.md) — Long-term phases.
- [`.claude/project/design/`](./.claude/project/design/) — Cross-cutting design knowledge (on-demand; optional).
- [`.claude/project/slices/`](./.claude/project/slices/) — Archived completed slices (Decision Log).

## Active Work

- [`.claude/plans/`](./.claude/plans/) — Currently active slice plans (ephemeral; deleted on Phase 9 cleanup).

## Common Commands

> Finalized after the Laravel scaffold (Phase 1).

```bash
# Development
php artisan serve
php artisan migrate
php artisan queue:work

# Quality
./vendor/bin/pest
./vendor/bin/pint
./vendor/bin/phpstan analyse
```

## Workflow

Every session starts with `/craft:prime` (auto-triggered by the plugin's SessionStart
hook). To plan new work: `/craft:plan <slice-name>`. To resume open work: `/craft:continue`.

> Operator-specific deployment (reverse proxy, domains, secrets, branding) lives in a
> separate private infrastructure repository, never in this public repo.

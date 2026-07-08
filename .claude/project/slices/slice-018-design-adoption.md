# Slice 018 — Design Adoption (skill + theme-overlay generator)

> Completed: 2026-07-08 (landed on main via PR #46; slice PR #25 into the epic branch 2026-07-02)
> Commits: 0aa120a, 5d72561 (PR #25)
> Epic: epic-002 (authoring-skills), first slice.

## What

A deploy-time Claude-Code skill (`.claude/skills/design-adoption/SKILL.md`) that
turns a shop's website URL into a ready-to-place Revoco brand theme, backed by a
tested artisan command `revoco:make-theme` (`MakeThemeCommand` +
`ThemeOverlayGenerator`). The skill scans the site and extracts its corporate
identity (colours, typography, logo); the command deterministically emits a
`.wf-card[data-theme="<slug>"]` overlay against the existing `--wf-*` token
contract plus a placement report (`APP_THEME=<slug>`, logo wiring via
`REVOCO_LOGO_URL` / `--wf-logo-*`). Output goes to an operator-chosen `--output`
path or stdout — never into this repo's committed theme. No app-runtime change.

## Why

Revoco is self-hosted and often set up by a non-programmer or an agency. Without
this, mirroring the shop's look means hand-writing CSS against the `--wf-*`
contract. The skill closes that gap so the form matches the shop (logo included)
without code.

## Decisions

- **Tested seam = artisan command; skill = AI wrapper.** The deterministic
  generator/validator is the CRAFT-testable core; the non-deterministic AI
  extraction (scan site → token set) lives in the skill and is verified hands-on
  in Phase 5, not in the Pest suite. Promoted to
  [`design/authoring-skills.md`](../design/authoring-skills.md) at epic close.
- **Output is operator-placed, never committed here.** The overlay + logo target
  the operator's private infra overlay / deployment mount; the command writes to
  an external path or stdout and the report says so. *Grounds:* rules.md Tabu (no
  brand overlays/assets in the public OSS repo) + `design/theming.md`
  public/private split.
- **Overlay honours the `--wf-*` contract + a11y invariants.** The generator
  emits only known contract token names and never overrides the focus-ring /
  reduced-motion base styles — both a correctness rule and a test assertion.
- **Skill home = `.claude/skills/`.** First repo-shipped authoring skill;
  establishes where epic-002's deploy-time skills live.
- **CLI conventions (slice-008).** Parameter-driven; prompts only on a TTY; fails
  cleanly under `--no-interaction` with a required option missing.

## Commits

- `0aa120a` — feat(skills): add design-adoption skill + revoco:make-theme generator
- `5d72561` — docs(readme): document the design-adoption authoring skill

Files: `.claude/skills/design-adoption/SKILL.md`,
`app/Console/Commands/MakeThemeCommand.php`,
`app/Services/ThemeOverlayGenerator.php`, `app/Services/ThemeOverlay.php`,
`tests/Feature/MakeThemeCommandTest.php`, `README.md`,
`.claude/project/design/theming.md` (1 line).

## Follow-ups

- **Operator note surfaced during Phase-5 hands-on:** switching a deployment's
  `APP_THEME` requires a container `--force-recreate`, not just an `.env` edit —
  `APP_THEME` is baked as an OS env var at `docker compose up` and shadows `.env`.
  Recorded in [`design/deployment.md`](../design/deployment.md) at epic close.

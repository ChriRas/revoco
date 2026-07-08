# Authoring Skills (deploy-time AI)

> Cross-cutting pattern behind epic-002. Read when planning or changing a
> deploy-time authoring skill (`design-adoption`, `legal-extraction`, or a future
> sibling). Established across slice-018 + slice-019.

## What they are

Authoring skills are **deploy-time Claude-Code skills** that help a non-programmer
operator (or their AI-literate agency) reach Revoco's best use without writing
code — adopting a shop's visual identity, importing existing legal pages, etc.
They run in a Claude-Code session **at setup time**. Nothing ships in the running
container; there is no request-time AI, no live scraping when a consumer submits.

Skill homes live under `.claude/skills/<name>/SKILL.md` (repo-shipped).

## The core pattern: deterministic command core + AI wrapper

Each authoring skill is split into two parts with a hard seam between them:

1. **A deterministic artisan command** — the correctness-critical core. It takes
   explicit, validated input and produces the real state change (a generated
   overlay file, a settings write). This is the **CRAFT-tested seam**: a Pest
   feature test drives it end-to-end.
2. **An AI skill wrapper** — the non-deterministic part (scan a live site, extract
   a token set or legal content, map it to the command's input). It is **verified
   hands-on in Phase 5**, never in the automated suite, because a live-site scrape
   has no stable fixture.

*Why the split:* a pure-prose skill has no automated test and cannot pass the
Phase-3 test gate. Extracting a deterministic seam makes the correctness-critical
part stable and testable, while the inherently fuzzy AI step is owned by the skill
and proven against a real site by a human.

Instances:
- **design-adoption** (slice-018): skill scans a shop URL → `revoco:make-theme`
  generates the validated `--wf-*` overlay + placement report.
- **legal-extraction** (slice-019): skill scrapes Impressum/privacy URLs →
  `revoco:import-legal` validates, sanitizes and persists into `LegalSettings`.

## Invariants every authoring skill upholds

- **Operator-reviewed, never auto-published.** AI output is a draft. The operator
  confirms theme placement / reviews the legal settings in Filament before it goes
  live. The operator remains the data controller; Revoco makes no legal-correctness
  guarantee.
- **Emit existing shapes, invent no new path.** Design emits the established
  `--wf-*` / `APP_THEME` overlay mechanism; legal fills the existing
  `spatie/laravel-settings` store. No new stores, no runtime coupling, no bespoke
  formats.
- **Never write brand/operator specifics into this public repo.** Generated
  overlays and assets target the operator's private infra overlay / deployment
  mount (or stdout). Enforced by the rules.md Tabu.
- **Untrusted input is validated and sanitized.** Scraped third-party HTML is
  sanitized with the same tool the render path uses (`Str::sanitizeHtml`);
  unknown/invalid payload keys are rejected and nothing is written. Existing
  operator-reviewed content is never silently clobbered (explicit `--overwrite`).
- **CLI conventions (slice-008).** Commands are parameter-driven, prompt only on a
  TTY, and fail cleanly under `--no-interaction`. Env→param bridging, if any, lives
  in a `task` wrapper, not in the command.

## Test strategy

Test the command, hands-on the AI. The Pest feature test asserts the deterministic
contract (valid output shape, known-tokens-only / known-keys-only, a11y invariants
preserved, sanitization, overwrite guard, clean `--no-interaction` failure). The AI
extraction step is a Phase-5 hands-on run against a real URL.

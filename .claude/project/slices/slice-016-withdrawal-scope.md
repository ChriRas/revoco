# Slice 016 — Withdrawal Scope (operator-declared contract types)

> Completed: 2026-07-01
> PR: #17 (→ epic-001)

## What

The operator declares which contract/goods types they offer (goods / services / digital
content); the generic form copy is tailored to name them — **without ever touching the
submit path** (§ 356a: never obstruct, never remove the free-text fallback).

## Why

Tailor the form copy to the merchant's actual offering while keeping the withdrawal itself
universal and unblocked.

## Decisions

- Taxonomy grounded, not invented: goods / services / digital content from § 312g in
  conjunction with § 355/§ 356 BGB (verified 2026-07-01). Edge cases (utilities,
  subscriptions) are covered by the free-text `subject` if needed.
- **Display-only — hard § 356a guardrail:** scope toggles shape copy/labels only; they never
  gate the submit and never remove the free-text fallback. A consumer whose case matches no
  enabled category can still submit. See `design/legal-compliance.md`.
- Default = all off → generic copy (behaviour-preserving on upgrade); the operator opts into
  specificity.
- Withdrawal-period / classification logic stays out of scope (free-text, manual); this slice
  is copy tailoring only.

## Gates

Pint · PHPStan larastan level max · Pest green. Merged via PR #17.

# Slice 015 — Missing-Content Warning (completeness gate)

> Completed: 2026-07-02
> PR: #19 (→ epic-001) · commits 2162cd5, 05928c0, 87f6dfc, fcd09fb

## What

When the imprint and/or privacy policy is unconfigured, the operator is warned — a **red
banner** in the Filament panel and a **non-blocking red setup notice** on the public form —
both keyed off one `App\Support\LegalContent` completeness helper and self-healing once both
are configured. The panel banner clears immediately after save.

## Why

A fresh/misconfigured install must nudge the operator to supply the mandatory legal pages
(§ 5 DDG imprint + privacy) without ever blocking the withdrawal (§ 356a). No login-history
flag — keyed purely on config completeness, so it self-heals.

## Decisions

- Public notice is **non-blocking** (banner only); the form stays fully submittable — § 356a
  is absolute, and a fresh install can't be distinguished from a misconfigured live site.
- Two surfaces, one helper: the panel banner + the public notice both key off
  `LegalContent::isComplete()`.
- Completeness per page = structured content OR external override link; imprint core =
  name + address + email, privacy = any content; whitespace rejected via `trim()`.
- `MissingSettings` → treated incomplete (warnings shown), never 500s.
- Public notice made **loud red** (user request) with a warning icon — unmissable — matching
  the panel banner; `role="status"` (polite, non-alarming for assistive tech).
- Banner clears right after save: `ManageLegal::getRedirectUrl()` redirects to self so the
  top-bar render hook re-evaluates without a manual reload (chosen over a reactive
  Livewire-component banner for simplicity/robustness).
- Required markers on the § 5 core fields are visual only (`markAsRequired`) — the red
  warning does the soft enforcement; the external-link + partial-save paths stay open.

## Gates

Pint · PHPStan larastan level max · Pest 167 passed (545 assertions). Merged via PR #19.

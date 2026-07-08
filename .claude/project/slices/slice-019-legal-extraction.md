# Slice 019 — Legal Extraction (skill + Impressum/Datenschutz importer)

> Completed: 2026-07-08 (landed on main via PR #46; slice PR #26 into the epic branch 2026-07-02)
> Commits: 1dfb746, 2190492, 02e9b97 (PR #26)
> Epic: epic-002 (authoring-skills), second slice. Depends-On: slice-018.

## What

A deploy-time Claude-Code skill (`.claude/skills/legal-extraction/SKILL.md`) that
scrapes the operator's existing Impressum and privacy-policy pages from URLs and
loads their content into the Phase-10 `LegalSettings`, backed by a tested artisan
command `revoco:import-legal` (`ImportLegalCommand` + `LegalContentImporter`). The
command reads a structured JSON payload (`--input`), validates it against the
`LegalSettings` schema (known keys only), **sanitizes** incoming privacy/addendum
HTML, and persists the § 5 DDG imprint fields (global) plus per-locale
`privacy_content` / `imprint_address` / `imprint_addendum` under `--locale`. The
operator then reviews the result in Filament `ManageLegal` before it goes live. No
app-runtime change.

## Why

Operators typically already have legal pages elsewhere (their shop, a CMS).
Hand-copying that content into the panel is tedious and error-prone; the skill
populates the settings automatically, and the Filament panel remains the review
gate so the operator stays the data controller.

## Decisions

- **Same seam pattern as slice-018** — deterministic command core + AI wrapper;
  the command is Pest-tested, the scrape/extraction is Phase-5 hands-on. The
  second instance of the pattern; promoted to
  [`design/authoring-skills.md`](../design/authoring-skills.md) at epic close.
- **Payload = validated JSON (`--input`), not ~18 flags.** The § 5 DDG field count
  plus the nested per-locale structure make JSON the natural CLI interface; the
  command writes nothing on a validation error. Still slice-008-compliant.
- **Fill-empty by default; `--overwrite` to replace.** Refuses to clobber
  already-populated legal fields unless `--overwrite` is passed (clean fail listing
  the conflicts). *Grounds:* legal text is operator-reviewed and liability-bearing
  — an AI scrape must never silently destroy it.
- **Scraped HTML is sanitized before storing** (`Str::sanitizeHtml`, allow-list) —
  `privacy_content` / `imprint_addendum` render on the public legal pages, so
  imported HTML from arbitrary sites is untrusted (stored-XSS). Reuses the exact
  tool the render path uses.
- **Scope = internal content fields, per `--locale`.** Imports content into the
  internal fields, not the external `*_link` overrides; `fallback_order` is config
  and left untouched. Natural-language fields (address, prose) are per-locale;
  structured imprint fields are global.
- **Filament `ManageLegal` is the review gate.** The command prints a mandatory
  controller-review reminder; it never auto-publishes.

## Commits

- `1dfb746` — feat(skills): add legal-extraction skill + revoco:import-legal importer
- `2190492` — docs(readme): document the legal-extraction authoring skill
- `02e9b97` — docs: genericize brand-specific examples

Files: `.claude/skills/legal-extraction/SKILL.md`,
`app/Console/Commands/ImportLegalCommand.php`,
`app/Services/LegalContentImporter.php`, `app/Services/LegalImportResult.php`,
`tests/Feature/ImportLegalCommandTest.php`, `README.md`.

## Follow-ups

- **Phase-5 hands-on (Shopware)** confirmed the design: the overwrite guard fired
  live (dev had legal content → refused, then `--overwrite` wrote 14 fields),
  `Str::sanitizeHtml` kept structure, the Impressum rendered fully structured.
  Extraction quality on flat CMS markup is best-effort by design — which is why
  the Filament review gate exists and the skill (not the tested command) owns
  extraction quality.

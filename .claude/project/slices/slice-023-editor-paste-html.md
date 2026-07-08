# Slice 023 — "Paste HTML" button in the legal rich editors

> Completed: 2026-07-06
> Commits: f24ef03 (PR #35)
> Origin: staging finding **C** (0.6.0) — comfort feature.

## What

The privacy-policy and imprint-addendum RichEditors in `ManageLegal` gained a
**"HTML einfügen"** toolbar button. It opens a modal with a plain `Textarea`; on
save the pasted HTML is **sanitized** and inserted into the editor as formatted,
editable content. Implemented as a Filament v5 `App\Filament\RichContent\
PasteHtmlPlugin` (`getEditorTools()` → the toolbar button; `getEditorActions()` →
the modal `Action`), registered on the legal editors via `->plugins([...])` with
`pasteHtml` added to their `toolbarButtons()`.

## Why

Operators receive law-firm privacy policies as HTML (or plain text). Pasting
directly into a rich editor is fiddly and lossy; a paste-as-HTML modal makes
importing a real legal text painless.

## Decisions

- **Filament v5 `RichContentPlugin` is the supported path** — v5 exposes
  `getEditorTools()` + `getEditorActions()` + `runCommands()`/`EditorCommand`,
  purpose-built for a toolbar button that opens a modal and runs a TipTap command,
  so no hacking around the editor.
- **Sanitize on insert, reusing `Str::sanitizeHtml()`** — pasted third-party HTML
  is untrusted and ends up on the public legal pages; sanitizing with the exact
  tool the render path uses avoids a sanitize mismatch and stored-XSS. The action
  passes the sanitized (not raw) content to the insert command.
- **Insert at cursor, not replace-all** — non-destructive; fills an empty editor
  naturally. A "replace" affordance was left for later if hands-on shows a need.

## Commits

- `f24ef03` — feat(legal): add a "paste HTML" button to the legal rich editors

Files: `app/Filament/RichContent/PasteHtmlPlugin.php`,
`app/Filament/Pages/ManageLegal.php`, `lang/{de,en}/panel.php`,
`tests/Feature/PasteHtmlPluginTest.php`.

## Follow-ups

- Archived retroactively during the 2026-07-08 plan/state reconciliation (shipped
  via PR #35 without a formal Phase 6–9 pass).

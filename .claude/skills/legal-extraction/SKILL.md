---
name: legal-extraction
description: >-
  Import an operator's existing Impressum and privacy-policy pages into Revoco's
  legal settings at deploy time. Use when setting up a Revoco deployment and the
  operator already has legal pages elsewhere (their shop, a CMS) that should
  populate the withdrawal form's Impressum/Datenschutz. Scrapes the given URLs,
  extracts the content, and loads it via `revoco:import-legal` for review in
  Filament. Deploy-time authoring only — not app runtime.
---

# Legal Extraction — populate Revoco's legal pages from existing URLs

This skill helps an operator fill Revoco's Impressum (§ 5 DDG) and privacy policy
from pages they already publish, without hand-copying field by field. You scrape
the pages, map the content to a JSON payload, and hand it to the deterministic
`revoco:import-legal` command, which validates, sanitises and persists it into the
Filament-managed legal settings.

**You do not decide what is legally correct.** Your job is faithful extraction +
mapping; the command validates the shape and sanitises the HTML. The operator
reviews and owns the result.

## Guardrails (read first)

- **Operator-reviewed, never auto-published.** The command fills the settings; the
  operator reviews and edits them in **Filament → Legal** before they go live. The
  panel is the review gate — never present the import as final or legally correct.
- **The operator is the data controller.** Revoco makes no legal-correctness
  guarantee. Extract only what is actually on the pages; **do not invent, complete
  or "improve" legal fields.** Leave anything you cannot find out of the payload.
- **Never clobber reviewed text silently.** If the command refuses because a field
  is already populated, stop and ask the operator before re-running with
  `--overwrite`.
- **Scope to the stated locale.** Per-locale fields (address, addendum, privacy
  content) are written under the `--locale` you were given. Confirm which language
  the scraped pages are in.

## Procedure

### 1. Ask for the URLs and the locale

Ask the operator for:
- the **Impressum** URL,
- the **privacy-policy** URL,
- the **locale** those pages are written in (e.g. `de`).

Either URL may be omitted if that page does not exist yet.

### 2. Scrape and extract

Load each page (Claude-in-Chrome tools or a fetch) and extract:

- **Impressum → § 5 DDG fields:** entity name, legal form, represented-by, full
  postal address, e-mail, phone, register court + number, VAT ID, business ID, and
  (only if present) supervisory authority, chamber, statutory job title,
  professional rules, liquidation note. Any remaining free-form text becomes the
  `addendum`.
- **Privacy → the policy body** as HTML (keep the meaningful structure — headings,
  paragraphs, lists; drop the site chrome/navigation).

Take values verbatim from the page. When a field is ambiguous or absent, leave it out.

### 3. Build the JSON payload

Write a payload file matching this shape (include only the keys you actually found):

```json
{
  "imprint": {
    "name": "Muster GmbH",
    "legal_form": "GmbH",
    "represented_by": "Erika Musterfrau",
    "address": "Musterstraße 1\n12345 Musterstadt",
    "email": "kontakt@example.com",
    "phone": "+49 123 456789",
    "register_court": "Amtsgericht Musterstadt",
    "register_number": "HRB 12345",
    "vat_id": "DE123456789",
    "addendum": "<p>optional free-form additions</p>"
  },
  "privacy": {
    "content": "<h2>Datenschutzerklärung</h2><p>…</p>"
  }
}
```

Structured imprint fields are global; `address`, `addendum` and `privacy.content`
are stored per the given locale. `addendum` and `privacy.content` are HTML (they are
sanitised on import); everything else is plain text.

### 4. Run the importer

```
task artisan -- revoco:import-legal --locale=de --input=./legal-payload.json
```

(Outside the Docker/`task` setup, call `php artisan revoco:import-legal …` directly.)
It writes nothing on a validation error (unknown key, malformed e-mail), and it
**refuses to overwrite already-populated fields** — if it does, report which fields
conflict and ask the operator before re-running with `--overwrite`.

### 5. Hand the result to the operator

Show the imported-fields summary, then direct the operator to **Filament → Legal**
to review and correct every field, and to check the rendered `/impressum` and
`/datenschutz` pages. Remind them that nothing is auto-published, the content is
theirs to verify, and legal correctness is their responsibility as the data
controller.

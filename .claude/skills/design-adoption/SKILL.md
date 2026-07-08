---
name: design-adoption
description: >-
  Adopt a shop's visual identity into the Revoco withdrawal form at deploy time.
  Use when setting up a Revoco deployment and you want the form to match a
  specific shop's website (colours, fonts, logo). Scans the shop URL, extracts
  its corporate identity, and generates a ready-to-place `--wf-*` brand theme
  overlay plus a placement report. Deploy-time authoring only — not app runtime.
---

# Design Adoption — brand a Revoco deployment from a shop URL

This skill helps an operator (or their agency) make the Revoco withdrawal form
look like a specific shop's website, without hand-writing CSS. You scan the shop,
extract its corporate identity, and hand it to the deterministic generator command
`revoco:make-theme`, which produces a validated theme overlay and tells the
operator exactly where to put it.

**You do not write the CSS yourself.** Your job is extraction + mapping; the
command renders and validates the overlay against Revoco's `--wf-*` token contract
(see `.claude/project/design/theming.md`). This keeps the output correct and a11y-safe.

## Guardrails (read first)

- **Operator-reviewed, never auto-published.** You produce a draft overlay + a
  placement report. The operator places the file and reviews the rendered form
  before it goes live. Never claim the result is final.
- **Never commit brand output to this public repo.** The overlay and any logo are
  operator/brand assets — they belong in the operator's *private* infra repo or
  deployment mount, selected per deployment via `APP_THEME`. Committing them here
  violates the project's public/private split (`rules.md` → Tabus).
- **Do not fabricate brand values.** If you cannot confidently extract a colour or
  font, ask the operator to confirm it rather than guessing.

## Procedure

### 1. Ask for the shop URL

Ask the operator for the website whose look the form should adopt (e.g. the shop's
home page). If they also have a specific brand/style guide page, take that too.

### 2. Scan and extract the corporate identity

Load the page and extract the brand signals. Use whatever browser/fetch tooling is
available (the Claude-in-Chrome tools give you *computed* styles, which are more
reliable than raw HTML; a plain fetch of the CSS is a fallback). Extract:

- **Colours** — the primary accent (buttons/links), its hover state, body text,
  heading text, muted text, the card/surface background, and border colour.
- **Fonts** — the body font stack and the heading/display font stack.
- **Logo** — the URL of the shop's logo image.
- **Brand name** — the shop/company name.

Prefer values taken from computed styles of prominent elements (primary button,
headings, body). When a value is ambiguous, show the operator your candidate and
confirm before using it.

### 3. Map to `revoco:make-theme` options

Each extracted value maps to one command option (which maps to one `--wf-*` token):

| Extracted signal | Option |
|---|---|
| accent / primary | `--accent` (+ `--accent-hover`) |
| body text | `--fg` |
| heading text | `--heading-fg` |
| muted text | `--muted` |
| card / surface bg | `--card-bg` |
| border | `--card-border` |
| button bg / hover / text | `--btn-bg` / `--btn-hover` / `--btn-fg` |
| body font stack | `--font` |
| heading font stack | `--font-display` |
| logo URL | `--logo-url` (guidance only) |
| brand name | `--brand-name` (guidance only) |

Only pass the options you actually extracted — unset tokens correctly fall through
to the neutral default. Colours may be hex, `rgb()/rgba()`, `hsl()/hsla()` or a CSS
named colour; the command rejects anything malformed.

### 4. Run the generator

Choose a short lowercase `--slug` for the deployment (e.g. the brand name) and
write the overlay to a file with `--output`:

```
task artisan -- revoco:make-theme \
  --slug=<brand> \
  --accent='#c1121f' --accent-hover='#a00e19' \
  --fg='#1a1a1a' --heading-fg='#111111' \
  --card-bg='#ffffff' --card-border='#e5e5e5' \
  --font='"Inter", system-ui, sans-serif' \
  --font-display='"Poppins", sans-serif' \
  --logo-url='https://shop.example/logo.svg' \
  --brand-name='Example Shop' \
  --output=./<brand>-theme.css
```

(Outside the Docker/`task` setup, call `php artisan revoco:make-theme …` directly.)
If the command reports an invalid value, fix that one input and re-run — it writes
nothing on a validation error.

### 5. Hand the result to the operator

Show the operator the generated `<brand>-theme.css` and the command's **placement
report** verbatim, then walk them through it:

1. Move the overlay into their private infra repo / deployment mount (never this repo).
2. Set `APP_THEME=<slug>` in the deployment `.env`.
3. Wire the logo via `REVOCO_LOGO_URL` (and `REVOCO_BRAND_NAME`) in `.env`.
4. **Review** the rendered form against the shop, and check focus-ring/text contrast
   against the new accent before going live.

End by reminding them the result is a starting draft they own and must review — the
form's final appearance and any brand-asset licensing are their responsibility.

# Theming — the `--wf-*` token contract

> Cross-cutting design knowledge promoted from slice-002. Defines how the
> withdrawal form's appearance is themed. Branding is **configuration, not a code
> fork** (see `intent.md` → "Neutral-first theming").

## Mechanism

1. **Selection.** `APP_THEME` (env) → `config('revoco.theme')` (default
   `neutral`) → rendered as `data-theme="{theme}"` on the `.wf-card` form element.
2. **Token swap.** Every visible property of the form reads from a single CSS
   variable contract (`--wf-*`). A theme is just an override block keyed on
   `.wf-card[data-theme="<name>"]`. The markup never changes — pure CSS swap.
3. **Neutral default.** `resources/css/withdrawal.css` defines the neutral token
   set on `.wf-card` (and explicitly on `.wf-card[data-theme="neutral"]`), then
   the base form/card styles that consume the tokens.

```
APP_THEME ─► config(revoco.theme) ─► <form class="wf-card" data-theme="…"> ─► --wf-* values ─► styles
```

## The token contract (neutral values)

Fonts: `--wf-font`, `--wf-font-display`.
Card: `--wf-card-bg` `#ffffff`, `--wf-card-border` `#e1e6ec`, `--wf-card-radius`
`18px`, `--wf-rail` (accent gradient), `--wf-shadow`.
Text: `--wf-fg`, `--wf-heading-fg`, `--wf-muted`, `--wf-border`.
Inputs: `--wf-input-bg`, `--wf-input-border`, `--wf-input-fg`,
`--wf-input-radius`, `--wf-placeholder`.
Accent / focus: `--wf-accent` `#33576f`, `--wf-accent-hover`, `--wf-focus-ring`.
Button: `--wf-btn-bg`, `--wf-btn-hover`, `--wf-btn-fg`, `--wf-btn-radius`.
Labels: `--wf-label-fg`, `--wf-label-transform`, `--wf-label-spacing`,
`--wf-label-weight`.
Badges: `--wf-badge-bg`, `--wf-badge-fg`. Error: `--wf-danger`.
Footer: `--wf-page-foot-fg`.

> The authoritative, current values live in `resources/css/withdrawal.css` — this
> doc names the contract, not a frozen copy. A theme overrides any subset; unset
> tokens fall through to the neutral defaults.

## Adding a brand overlay (extension hook)

A commented `[data-theme="example"]` block in `withdrawal.css` shows the shape.
To add a real overlay:

1. Define `.wf-card[data-theme="<brand>"] { --wf-…: …; }` overriding the needed
   tokens (and optionally a `--wf-logo-*` for the logo slot).
2. Set `APP_THEME=<brand>` for that deployment.

**Public/private split (hard rule).** This public OSS repo ships the neutral
theme + the mechanism + one commented example hook **only**. Concrete brand
themes (real colours, fonts, logos) and their assets live in the **private infra
repo** and are mounted/selected per deployment. Never commit brand overlays or
real logos here (see `rules.md` → Tabus). The prototype's base64 brand logos
were deliberately not ported for this reason.

## Logo slot

`.wf-logo` is a reserved, empty slot in the neutral theme. A deployment provides a
logo via `REVOCO_LOGO_URL` (`.env`) — rendered as an `<img>` — or, for a brand
overlay, via CSS (`--wf-logo-img`/`-w`/`-h`). Neutral ships no logo.

## Accessibility invariants (theme-independent)

Themes must preserve: a visible focus ring (`--wf-focus-ring` + `:focus`/
`:focus-visible`), sufficient contrast, and `prefers-reduced-motion: reduce`
honoured (animations/transitions disabled). These live in the base styles, not in
the per-theme overrides — an overlay that only sets colour tokens keeps them.

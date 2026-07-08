# Landing-page assets

## Screenshot derivatives (`img/*.webp`)

Generated from the retina originals in [`../screenshots/`](../screenshots/)
(see its `manifest.md` for what each shot shows). Regenerate after a new
screenshot session:

```bash
cd docs/screenshots
for f in *.png; do
  case "$f" in
    *-mobile.png) target=640 ;;   # mobile shots (1170px wide @3x) → 640px
    *)            target=1440 ;;  # desktop shots (2880px wide @2x) → 1440px
  esac
  cwebp -quiet -q 82 -resize "$target" 0 "$f" -o "../assets/img/${f%.png}.webp"
done
```

The pages reference these derivatives with explicit `width`/`height`
attributes — update those in `docs/index.html` and `docs/de/index.html` if a
regenerated image changes its aspect ratio (`webpinfo` prints the dimensions).

## Social preview (`og-image.png`)

1200 × 630 PNG (WebP is not reliably supported by link-preview scrapers),
cropped from the branded form screenshot:

```bash
sips --resampleWidth 1200 docs/screenshots/form-branded.png -o /tmp/og.png
sips -c 630 1200 /tmp/og.png -o docs/assets/og-image.png
```

## Logos

- `../revoco-logo.svg` — light-mode wordmark (dark ink), also used by the README.
- `revoco-logo-dark.svg` — dark-mode variant (light ink), swapped in via
  `<picture media="(prefers-color-scheme: dark)">`.
- `favicon.svg` — the square brand mark alone; works on light and dark tabs.

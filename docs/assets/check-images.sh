#!/usr/bin/env bash
# Local consistency check of the landing-page screenshots (exit 0 = consistent).
#
# Checks: every WebP the pages reference exists and its width/height attributes equal the real
# dimensions; every WebP has a PNG original; every PNG is listed in the manifest and every listed
# PNG exists; desktop originals are 2880 px wide, mobile originals 1170 px wide.
# Needs `webpinfo` (libwebp) and `file`. Run from anywhere: bash docs/assets/check-images.sh
set -u

cd "$(dirname "$0")/.." || exit 2   # docs/

fail=0
err() { echo "FAIL: $*" >&2; fail=1; }

dim() { # webp file -> "WxH"
  webpinfo "$1" | awk '/^ *Width:/{w=$2} /^ *Height:/{h=$2} END{print w"x"h}'
}

# 1. Page references: file exists, width/height attributes match.
for page in index.html de/index.html; do
  prefix=""; [ "$page" = "de/index.html" ] && prefix="../"
  while IFS= read -r tag; do
    src=$(printf '%s' "$tag" | sed -n 's/.*src="\([^"]*\)".*/\1/p')
    w=$(printf '%s' "$tag" | sed -n 's/.* width="\([0-9]*\)".*/\1/p')
    h=$(printf '%s' "$tag" | sed -n 's/.* height="\([0-9]*\)".*/\1/p')
    case "$src" in "${prefix}assets/img/"*.webp) ;; *) continue ;; esac
    file="${src#"$prefix"}"
    if [ ! -f "$file" ]; then err "$page references missing $file"; continue; fi
    [ "$(dim "$file")" = "${w}x${h}" ] || err "$page: $file is $(dim "$file"), attributes say ${w}x${h}"
  done < <(tr '\n' ' ' < "$page" | grep -o '<img [^>]*>')
done

# 2. Every WebP has a PNG original.
for webp in assets/img/*.webp; do
  name=$(basename "$webp" .webp)
  [ -f "screenshots/$name.png" ] || err "$webp has no PNG original"
done

# 3. PNGs and the manifest agree.
for png in screenshots/*.png; do
  name=$(basename "$png")
  grep -q "\`$name\`" screenshots/manifest.md || err "$name is not listed in manifest.md"
done
while IFS= read -r name; do
  [ -f "screenshots/$name" ] || err "manifest.md lists missing $name"
done < <(grep -o '`[A-Za-z0-9_-]*\.png`' screenshots/manifest.md | tr -d '`' | sort -u)

# 4. Original widths: 2880 px desktop, 1170 px mobile.
for png in screenshots/*.png; do
  case "$png" in *-mobile.png) want=1170 ;; *) want=2880 ;; esac
  got=$(file "$png" | sed -n 's/.*PNG image data, \([0-9]*\) x.*/\1/p')
  [ "$got" = "$want" ] || err "$png is ${got:-?} px wide, expected $want"
done

[ "$fail" -eq 0 ] && echo "images consistent"
exit "$fail"

{{-- Shared consumer-page footer (form + success). Two centered rows:
       Row 1 — the legal links "Impressum · Datenschutzerklärung", normal weight.
       Row 2 — the Design-16 double-mark: a blue ring-tile + the GitHub Octocat.
     On hover or keyboard focus the accessible name "Revoco App on GitHub" expands
     to the side, on the mark's own row, so the legal links never shift (no layout
     jump). prefers-reduced-motion disables the expand animation (CSS).

     The mark anchor carries config('revoco.source_url') plus the fixed English
     accessible name, so the AGPL-3.0 § 13 corresponding-source offer is reachable
     for assistive tech even before the hover-expand. The "Revoco App on GitHub"
     slogan is English-only by design — a brand slogan, not a translation key. --}}
<footer class="wf-foot">
    <div class="wf-foot-legal">
        <a href="{{ \App\Support\LegalPages::imprintUrl() }}">{{ __('wf.footer.imprint') }}</a>
        <span class="wf-foot-sep" aria-hidden="true">·</span>
        <a href="{{ \App\Support\LegalPages::privacyUrl() }}">{{ __('wf.footer.privacy') }}</a>
    </div>

    <div class="wf-foot-mark-row">
        <a class="wf-gh" href="{{ config('revoco.source_url') }}" target="_blank" rel="noopener noreferrer" aria-label="Revoco App on GitHub">
            <span class="wf-gh-mark">
                {{-- Revoco ring-tile: white ring + arrowhead on a rounded blue tile. --}}
                <svg class="wf-gh-tile" viewBox="0 0 64 64" width="20" height="20" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="2" width="60" height="60" rx="16" fill="#1f63e6"/>
                    <path d="M25.36 16.35 A17 17 0 1 0 38.64 16.35" stroke="#ffffff" stroke-width="6.2" stroke-linecap="round"/>
                    <path d="M29.44 12.45 L41.4 10.3 L36.0 22.2 Z" fill="#ffffff"/>
                </svg>
                {{-- Official GitHub Octocat mark (dark ink for the light footer). --}}
                <svg class="wf-gh-cat" viewBox="0 0 16 16" width="18" height="18" fill="#16233a" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
                </svg>
            </span>
            {{-- Visible slogan mirrors the anchor's accessible name (label-in-name);
                 hidden by CSS until hover/focus. --}}
            <span class="wf-gh-label">Revoco App on GitHub</span>
        </a>
    </div>
</footer>

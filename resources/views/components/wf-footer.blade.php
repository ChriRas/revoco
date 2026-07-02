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
                <x-icons.revoco-mark class="wf-gh-tile" />
                <x-icons.github-mark class="wf-gh-cat" />
            </span>
            {{-- Visible slogan mirrors the anchor's accessible name (label-in-name);
                 hidden by CSS until hover/focus. --}}
            <span class="wf-gh-label">Revoco App on GitHub</span>
        </a>
    </div>
</footer>

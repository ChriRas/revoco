{{--
    Version line at the sidebar foot (slice-035).

    Rendered via PanelsRenderHook::SIDEBAR_FOOTER on every authenticated panel page.
    Reads config and the cache only (ReleaseInfo) — never makes an HTTP request. The
    badge appears when the daily release check cached a newer stable release.
--}}
@php
    $releaseInfo = app(\App\Services\ReleaseInfo::class);
    $newer = $releaseInfo->newerRelease();
@endphp

<div class="fi-revoco-version" style="padding:.75rem 1.5rem;display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;font-size:.75rem">
    <span style="color:var(--gray-500)">{{ __('panel.version.running', ['version' => $releaseInfo->runningVersion()]) }}</span>

    @if ($newer !== null)
        <x-filament::badge
            color="info"
            size="sm"
            tag="a"
            :href="$newer['url']"
            target="_blank"
            rel="noopener noreferrer"
            :title="__('panel.version.release_notes', ['version' => $newer['version']])"
        >{{ __('panel.version.available', ['version' => $newer['version']]) }}</x-filament::badge>
    @endif
</div>

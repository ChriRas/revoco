{{--
    Legal-content completeness banner (slice-015).

    Rendered via PanelsRenderHook::TOPBAR_AFTER on every authenticated panel page.
    Fires in the backend locale (SetBackendLocale middleware runs before the hook).
    Shows only when App\Support\LegalContent::isComplete() returns false; absent
    once the operator has configured both the imprint and the privacy policy.

    Links to ManageLegal::getUrl() — the Filament "Legal" settings page.
--}}
@php
    $missingPages = \App\Support\LegalContent::missing();
@endphp

@if (count($missingPages) > 0)
    @php
        $pageLabels = array_map(
            fn (string $page): string => __("panel.setup.page_{$page}"),
            $missingPages,
        );
    @endphp
    <div
        role="alert"
        style="background:#b91c1c;color:#fff;padding:.6rem 1.25rem;font-size:.875rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap"
    >
        <span>{{ __('panel.setup.warning', ['pages' => implode(', ', $pageLabels)]) }}</span>
        <a
            href="{{ \App\Filament\Pages\ManageLegal::getUrl() }}"
            style="color:#fff;font-weight:600;text-decoration:underline;white-space:nowrap"
        >{{ __('panel.setup.link') }}</a>
    </div>
@endif

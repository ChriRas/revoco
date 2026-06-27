{{-- Consumer language switcher. Each available locale renders as a flag icon
     linking to the locale.set route (which writes the cookie SetConsumerLocale
     reads). The active locale is a non-link, marked aria-current and highlighted.
     The flag is decorative — the accessible language name rides on aria-label /
     title — and a locale shipping no flag partial falls back to its autonym text.
     Hidden entirely when only one locale is configured. --}}
@php
    $locales = \App\Support\ConsumerLocales::available();
    $current = app()->getLocale();
@endphp

@if (count($locales) > 1)
    <nav class="wf-langs" aria-label="{{ __('wf.language.label') }}">
        @foreach ($locales as $locale)
            @php
                $autonym = __('wf.language.names.'.$locale);
                $hreflang = str_replace('_', '-', $locale);
                $hasFlag = view()->exists('components.flags.'.$locale);
                $class = $hasFlag ? 'wf-flag' : 'wf-flag wf-flag--text';
            @endphp
            @if ($locale === $current)
                <span class="{{ $class }} is-current" lang="{{ $hreflang }}" aria-current="true" aria-label="{{ $autonym }}" title="{{ $autonym }}">
                    @if ($hasFlag)<x-dynamic-component :component="'flags.'.$locale" />@else{{ $autonym }}@endif
                </span>
            @else
                <a class="{{ $class }}" href="{{ route('locale.set', $locale) }}" hreflang="{{ $hreflang }}" lang="{{ $hreflang }}" rel="nofollow" aria-label="{{ $autonym }}" title="{{ $autonym }}">
                    @if ($hasFlag)<x-dynamic-component :component="'flags.'.$locale" />@else{{ $autonym }}@endif
                </a>
            @endif
        @endforeach
    </nav>
@endif

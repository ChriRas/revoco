@extends('layouts.app')

{{-- Generic legal page (privacy now, imprint later): title + safe-rendered rich
     content, or a neutral "not configured yet" placeholder when the operator has
     supplied neither content nor an override link. $content is a pre-rendered,
     sanitized HtmlString (Filament RichContentRenderer) or null. --}}
@section('content')
<main class="wf-shell">
    <div class="wf-card" data-theme="{{ config('revoco.theme') }}">
        <div class="wf-utility">
            @if (config('revoco.logo_url'))
                <img class="wf-logo" src="{{ config('revoco.logo_url') }}" alt="{{ config('revoco.brand_name') ?? __('wf.title') }}">
            @else
                <div class="wf-logo" aria-hidden="true"></div>
            @endif

            <x-language-switcher />
        </div>

        <section class="wf-panel">
            <header class="wf-head">
                <h1 class="wf-title">{{ $title }}</h1>
            </header>

            @if ($content !== null)
                <div class="wf-prose">{!! $content !!}</div>
            @else
                <p class="wf-legal-empty">{{ __('wf.legal.placeholder') }}</p>
            @endif
        </section>
    </div>
</main>

<footer class="wf-page-foot">
    <a href="{{ config('revoco.imprint_url') }}">{{ __('wf.footer.imprint') }}</a>
    <a href="{{ \App\Support\LegalPages::privacyUrl() }}">{{ __('wf.footer.privacy') }}</a>
    <a href="{{ config('revoco.source_url') }}" target="_blank" rel="noopener noreferrer">{{ __('wf.footer.source') }}</a>
</footer>
@endsection

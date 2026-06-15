@extends('layouts.app')

@section('content')
<div class="wf-shell">
    {{-- data-theme drives the --wf-* token swap; resolved from APP_THEME (neutral default). --}}
    <form class="wf-card" data-theme="{{ config('revoco.theme') }}" method="POST" action="{{ route('withdrawal.store') }}">
        @csrf

        <div class="wf-utility">
            @if (config('revoco.logo_url'))
                <img class="wf-logo" src="{{ config('revoco.logo_url') }}" alt="{{ config('revoco.brand_name') ?? __('wf.title') }}">
            @else
                {{-- Neutral: reserved, empty logo slot — brand logos are mounted per deployment. --}}
                <div class="wf-logo" aria-hidden="true"></div>
            @endif
        </div>

        <section class="wf-panel wf-panel--form">
            <header class="wf-head">
                <h1 class="wf-title">{{ __('wf.title') }}</h1>
                <p class="wf-sub">{{ __('wf.subtitle') }}</p>
            </header>

            <div class="wf-form">
                <div class="wf-grid">
                    {{-- Name (required) --}}
                    <div class="wf-field" data-field="name">
                        <label class="wf-label" for="wf-name">
                            {{ __('wf.field.name.label') }}
                            <span class="wf-req">{{ __('wf.badge.required') }}</span>
                        </label>
                        <input class="wf-input" type="text" id="wf-name" name="name"
                               autocomplete="name" required aria-required="true" aria-describedby="err-name">
                        <p class="wf-error" id="err-name">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>{{ __('wf.field.name.error') }}</span>
                        </p>
                    </div>

                    {{-- E-mail (required) --}}
                    <div class="wf-field" data-field="email">
                        <label class="wf-label" for="wf-email">
                            {{ __('wf.field.email.label') }}
                            <span class="wf-req">{{ __('wf.badge.required') }}</span>
                        </label>
                        <input class="wf-input" type="email" id="wf-email" name="email"
                               autocomplete="email" required aria-required="true" aria-describedby="err-email">
                        <p class="wf-error" id="err-email">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>{{ __('wf.field.email.error') }}</span>
                        </p>
                    </div>
                </div>

                {{-- Order / contract number (optional) --}}
                <div class="wf-field wf-field--full" data-field="order">
                    <label class="wf-label" for="wf-order">
                        {{ __('wf.field.order.label') }}
                        <span class="wf-optional">{{ __('wf.badge.optional') }}</span>
                    </label>
                    <input class="wf-input" type="text" id="wf-order" name="orderNumber" autocomplete="off">
                </div>

                {{-- Subject — affected goods / digital content / service (required) --}}
                <div class="wf-field wf-field--full" data-field="subject">
                    <label class="wf-label" for="wf-subject">
                        {{ __('wf.field.subject.label') }}
                        <span class="wf-req">{{ __('wf.badge.required') }}</span>
                    </label>
                    <input class="wf-input" type="text" id="wf-subject" name="subject"
                           autocomplete="off" required aria-required="true" aria-describedby="err-subject">
                    <p class="wf-error" id="err-subject">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>{{ __('wf.field.subject.error') }}</span>
                    </p>
                </div>

                {{-- Honeypot: anti-spam decoy, hidden from users + assistive tech.
                     No scoring/blocking here — signal handling is slice-003. --}}
                <div class="wf-hp" aria-hidden="true">
                    <label for="wf-website">{{ __('wf.honeypot.label') }}</label>
                    <input type="text" id="wf-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="wf-actions">
                    <button class="wf-btn" type="submit">
                        {{ __('wf.submit') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                    <p class="wf-hint">{{ __('wf.hint') }}</p>
                </div>
            </div>
        </section>
    </form>
</div>

<footer class="wf-page-foot">
    <a href="{{ config('revoco.imprint_url') }}">{{ __('wf.footer.imprint') }}</a>
    <a href="{{ config('revoco.privacy_url') }}">{{ __('wf.footer.privacy') }}</a>
</footer>
@endsection

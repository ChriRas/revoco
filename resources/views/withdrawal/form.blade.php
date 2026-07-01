@extends('layouts.app')

@section('content')
{{-- Non-blocking setup notice (slice-015): shown when legal content is not yet
     configured. Consumer-locale text. The form stays fully functional and
     submittable regardless — § 356a is absolute (withdrawal is never blocked). --}}
@if (! \App\Support\LegalContent::isComplete())
    <div class="wf-setup-notice" role="status">
        {{ __('wf.setup.pending') }}
    </div>
@endif
<main class="wf-shell">
    {{-- data-theme drives the --wf-* token swap; resolved from APP_THEME (neutral default).
         novalidate: suppress the browser's native validation bubbles so the submit reaches
         the server and our styled, translated inline @error messages are shown instead.
         `required`/`aria-required` stay for assistive-tech semantics. --}}
    <form class="wf-card" data-theme="{{ config('revoco.theme') }}" method="POST" action="{{ route('withdrawal.store') }}" novalidate>
        @csrf

        <div class="wf-utility">
            @if (config('revoco.logo_url'))
                <img class="wf-logo" src="{{ config('revoco.logo_url') }}" alt="{{ config('revoco.brand_name') ?? __('wf.title') }}">
            @else
                {{-- Neutral: reserved, empty logo slot — brand logos are mounted per deployment. --}}
                <div class="wf-logo" aria-hidden="true"></div>
            @endif

            <x-language-switcher />
        </div>

        <section class="wf-panel wf-panel--form">
            <header class="wf-head">
                <h1 class="wf-title">{{ __('wf.title') }}</h1>
                {{-- Scope intro: names the operator-declared contract categories, or a
                     generic fallback when none are enabled. Display only (§ 356a) —
                     see App\Support\WithdrawalScope; never gates the submit. --}}
                <p class="wf-sub">{{ \App\Support\WithdrawalScope::intro() }}</p>
                <p class="wf-sub">{{ __('wf.subtitle') }}</p>
            </header>

            <div class="wf-form">
                <div class="wf-grid">
                    {{-- Name (required) --}}
                    <div class="wf-field @error('name') is-invalid @enderror" data-field="name">
                        <label class="wf-label" for="wf-name">
                            {{ __('wf.field.name.label') }}
                            <span class="wf-req">{{ __('wf.badge.required') }}</span>
                        </label>
                        <input class="wf-input" type="text" id="wf-name" name="name" value="{{ old('name') }}"
                               autocomplete="name" required aria-required="true"
                               @error('name') aria-invalid="true" aria-describedby="err-name" @enderror>
                        @error('name')
                            <p class="wf-error" id="err-name" role="alert">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- E-mail (required) --}}
                    <div class="wf-field @error('email') is-invalid @enderror" data-field="email">
                        <label class="wf-label" for="wf-email">
                            {{ __('wf.field.email.label') }}
                            <span class="wf-req">{{ __('wf.badge.required') }}</span>
                        </label>
                        <input class="wf-input" type="email" id="wf-email" name="email" value="{{ old('email') }}"
                               autocomplete="email" required aria-required="true"
                               @error('email') aria-invalid="true" aria-describedby="err-email" @enderror>
                        @error('email')
                            <p class="wf-error" id="err-email" role="alert">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>
                </div>

                {{-- Order / contract number (optional) --}}
                <div class="wf-field wf-field--full" data-field="order">
                    <label class="wf-label" for="wf-order">
                        {{ __('wf.field.order.label') }}
                        <span class="wf-optional">{{ __('wf.badge.optional') }}</span>
                    </label>
                    <input class="wf-input" type="text" id="wf-order" name="orderNumber" value="{{ old('orderNumber') }}" autocomplete="off">
                </div>

                {{-- Subject — affected goods / digital content / service (required) --}}
                <div class="wf-field wf-field--full @error('subject') is-invalid @enderror" data-field="subject">
                    {{-- Label names the enabled categories (or the generic three-way
                         label when none). Display only — the field name/validation/
                         required state are unchanged (§ 356a). --}}
                    <label class="wf-label" for="wf-subject">
                        {{ \App\Support\WithdrawalScope::subjectLabel() }}
                        <span class="wf-req">{{ __('wf.badge.required') }}</span>
                    </label>
                    <input class="wf-input" type="text" id="wf-subject" name="subject" value="{{ old('subject') }}"
                           autocomplete="off" required aria-required="true"
                           @error('subject') aria-invalid="true" aria-describedby="err-subject" @enderror>
                    @error('subject')
                        <p class="wf-error" id="err-subject" role="alert">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                {{-- Honeypot: anti-spam decoy, hidden from users + assistive tech.
                     Filled → stored with spam=true; never blocks (handled in the controller). --}}
                <div class="wf-hp" aria-hidden="true">
                    <label for="wf-website">{{ __('wf.honeypot.label') }}</label>
                    <input type="text" id="wf-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="wf-actions">
                    <button class="wf-btn" type="submit">
                        {{ __('wf.submit') }}
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                    <p class="wf-hint">{{ __('wf.hint') }}</p>
                </div>
            </div>
        </section>
    </form>
</main>

<footer class="wf-page-foot">
    <a href="{{ \App\Support\LegalPages::imprintUrl() }}">{{ __('wf.footer.imprint') }}</a>
    <a href="{{ \App\Support\LegalPages::privacyUrl() }}">{{ __('wf.footer.privacy') }}</a>
    <a href="{{ config('revoco.source_url') }}" target="_blank" rel="noopener noreferrer">{{ __('wf.footer.source') }}</a>
</footer>
@endsection

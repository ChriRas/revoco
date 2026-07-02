@extends('layouts.app')

{{-- Imprint page (§ 5 DDG): structured operator fields with i18n labels, locale-
     independent operator data, and an optional per-language addendum. Empty optional
     fields are omitted (controller builds only non-blank rows). $fields is a list of
     groups (heading key + rows), $addendum is a sanitized HtmlString or null,
     $isEmpty is true when neither fields nor addendum are configured. --}}
@section('content')
<main class="wf-shell wf-shell--legal">
    {{-- Sticky back-link above the card so it is not clipped by overflow:hidden. --}}
    <a class="wf-back" href="{{ route('withdrawal.form') }}" data-theme="{{ config('revoco.theme') }}">
        <x-icons.arrow-left />
        <span>{{ __('wf.legal.back') }}</span>
    </a>

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

            @if ($isEmpty)
                <p class="wf-legal-empty">{{ __('wf.legal.placeholder') }}</p>
            @else
                <div class="wf-prose wf-imprint">
                    @foreach ($fields as $group)
                        <h2 class="wf-imprint-heading">{{ __($group['heading']) }}</h2>
                        <dl class="wf-imprint-dl">
                            @foreach ($group['rows'] as $row)
                                <div class="wf-imprint-row">
                                    <dt class="wf-imprint-dt">{{ $row['label'] }}</dt>
                                    <dd class="wf-imprint-dd">{{ $row['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endforeach

                    @if ($addendum !== null)
                        <h2 class="wf-imprint-heading">{{ __('wf.legal.imprint.heading.addendum') }}</h2>
                        <div class="wf-imprint-addendum">{!! $addendum !!}</div>
                    @endif
                </div>
            @endif
        </section>
    </div>
</main>

<x-wf-footer />
@endsection

@extends('layouts.app')

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
            <div class="wf-success">
                <div class="wf-check" aria-hidden="true">
                    <x-icons.check />
                </div>
                <h1 class="wf-success-title">{{ __('wf.success.title') }}</h1>
                <p class="wf-success-text">{{ __('wf.success.body') }}</p>
                <p class="wf-success-text">{{ __('wf.success.note') }}</p>
            </div>
        </section>
    </div>
</main>

<x-wf-footer />
@endsection

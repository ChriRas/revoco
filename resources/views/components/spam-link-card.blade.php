{{--
    Frame of the signed spam-link pages (confirm / invalid / gone). Operator-facing and
    PII-free; never indexed (robots meta) and no referrer is sent.
--}}
@push('head')
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
@endpush

<main class="wf-shell">
    <div class="wf-card" data-theme="{{ config('revoco.theme') }}">
        <section class="wf-panel">
            {{ $slot }}
        </section>
    </div>
</main>

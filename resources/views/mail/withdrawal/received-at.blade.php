{{--
    Receipt timestamp line, shared by the acknowledgment and the operator
    notification. Renders, per locale: the date (`mail.datetime_format`), the German
    "Uhr" suffix (empty in other locales) and a DST-aware timezone abbreviation —
    PHP's `T` token yields the neutral CET/CEST for the actual instant, `mail.tz`
    maps it to the locale label (de: MEZ/MESZ) and `mail.timezone_format` adds the
    surrounding punctuation (de: parentheses, en: none).

    @param \Illuminate\Support\Carbon|null $at  receipt timestamp (Europe/Berlin)
--}}
@php
    // Guard the dynamic `mail.tz` key: a null timestamp or an unmapped zone must
    // not leak a raw translation key into the § 356a receipt — fall back to the
    // neutral CET/CEST abbreviation (empty when there is no timestamp at all).
    $abbr = $at?->format('T');
    $tz = $abbr ? (Lang::has('mail.tz.'.$abbr) ? __('mail.tz.'.$abbr) : $abbr) : '';
@endphp
{{ $at?->format(__('mail.datetime_format')) }} {{ __('mail.uhr') }} {{ __('mail.timezone_format', ['tz' => $tz]) }}

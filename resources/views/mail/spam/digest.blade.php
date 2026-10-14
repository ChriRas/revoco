@use('App\Enums\SpamLinkAction')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.digest.heading') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1b1f26;line-height:1.5;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e1e6ec;border-radius:12px;padding:28px 32px;">
        <h1 style="margin:0 0 16px;font-size:1.3rem;color:#161b22;">{{ __('mail.digest.heading') }}</h1>
        @if ($total > 0)
            <h2 style="margin:0 0 8px;font-size:1.05rem;color:#161b22;">{{ __('mail.digest.spam_heading') }}</h2>
            <p style="margin:0 0 18px;">{{ trans_choice('mail.digest.intro', $total, ['count' => $total]) }}</p>

            @foreach ($entries as $entry)
                @include('mail.spam.digest-entry', ['entry' => $entry, 'action' => SpamLinkAction::NotSpam, 'linkLabel' => __('mail.digest.not_spam_link')])
            @endforeach

            @if ($more > 0)
                <p style="margin:18px 0 0;font-weight:600;">{{ __('mail.digest.more', ['count' => $more]) }}</p>
            @endif
        @endif

        @if ($suspectedEntries->isNotEmpty())
            <h2 style="margin:{{ $total > 0 ? '28px' : '0' }} 0 8px;font-size:1.05rem;color:#161b22;">{{ __('mail.digest.suspected_heading') }}</h2>
            <p style="margin:0 0 18px;">{{ trans_choice('mail.digest.suspected_intro', $suspectedTotal, ['count' => $suspectedTotal]) }}</p>

            @foreach ($suspectedEntries as $entry)
                @include('mail.spam.digest-entry', ['entry' => $entry, 'action' => SpamLinkAction::Spam, 'linkLabel' => __('mail.digest.spam_link')])
            @endforeach

            @if ($suspectedMore > 0)
                <p style="margin:18px 0 0;font-weight:600;">{{ __('mail.digest.more', ['count' => $suspectedMore]) }}</p>
            @endif
        @endif

        <p style="margin:22px 0 0;"><a href="{{ $panelUrl }}" style="color:#33576f;font-weight:600;">{{ __('mail.digest.panel_link') }}</a></p>
    </div>
</body>
</html>

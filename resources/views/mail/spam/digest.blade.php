@use('App\Enums\SpamLinkAction')
@use('App\Spam\SpamLinks')
@use('App\Spam\SpamReasonLabels')
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
        <p style="margin:0 0 18px;">{{ trans_choice('mail.digest.intro', $total, ['count' => $total]) }}</p>

        @foreach ($entries as $entry)
            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border-top:1px solid #e4e8ee;margin-top:12px;">
                <tr><td style="padding:6px 0;color:#5d6776;width:34%;">{{ __('mail.field.datetime') }}</td><td style="padding:6px 0;">@include('mail.withdrawal.received-at', ['at' => $entry->created_at])</td></tr>
                <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.name') }}</td><td style="padding:6px 0;">{{ $entry->name }}</td></tr>
                <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.email') }}</td><td style="padding:6px 0;">{{ $entry->email }}</td></tr>
                <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.digest.reason') }}</td><td style="padding:6px 0;">{{ SpamReasonLabels::render($entry->spam_reason) ?? '—' }}</td></tr>
                <tr><td colspan="2" style="padding:6px 0;"><a href="{{ SpamLinks::url($entry, SpamLinkAction::NotSpam) }}" style="color:#33576f;font-weight:600;">{{ __('mail.digest.not_spam_link') }}</a></td></tr>
            </table>
        @endforeach

        @if ($more > 0)
            <p style="margin:18px 0 0;font-weight:600;">{{ __('mail.digest.more', ['count' => $more]) }}</p>
        @endif

        <p style="margin:22px 0 0;"><a href="{{ $panelUrl }}" style="color:#33576f;font-weight:600;">{{ __('mail.digest.panel_link') }}</a></p>
    </div>
</body>
</html>

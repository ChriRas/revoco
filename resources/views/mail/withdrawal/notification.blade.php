<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.notification.subject') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1b1f26;line-height:1.5;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e1e6ec;border-radius:12px;padding:28px 32px;">
        <h1 style="margin:0 0 16px;font-size:1.3rem;color:#161b22;">{{ __('mail.notification.heading') }}</h1>

        @if ($withdrawal->spam)
            <p style="margin:0 0 18px;padding:10px 14px;background:#fdeceb;border:1px solid #f3c0bb;border-radius:8px;color:#a32a1e;">
                <strong>{{ __('mail.notification.spam_warning', ['reason' => $withdrawal->spam_reason ?? '—']) }}</strong>
            </p>
        @endif

        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:6px 0;color:#5d6776;width:42%;">{{ __('mail.field.name') }}</td><td style="padding:6px 0;">{{ $withdrawal->name }}</td></tr>
            <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.email') }}</td><td style="padding:6px 0;">{{ $withdrawal->email }}</td></tr>
            <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.order') }}</td><td style="padding:6px 0;">{{ $withdrawal->order_number ?? '—' }}</td></tr>
            <tr><td style="padding:6px 0;color:#5d6776;vertical-align:top;">{{ __('mail.field.subject') }}</td><td style="padding:6px 0;">{{ $withdrawal->subject }}</td></tr>
            <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.datetime') }}</td><td style="padding:6px 0;">{{ $withdrawal->created_at?->format('d.m.Y, H:i') }} {{ __('mail.uhr') }}</td></tr>
            <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.notification.spam_status') }}</td><td style="padding:6px 0;">{{ $withdrawal->spam ? __('mail.notification.spam_yes') : __('mail.notification.spam_no') }}</td></tr>
        </table>
    </div>
</body>
</html>

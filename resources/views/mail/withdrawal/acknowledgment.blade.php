<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.ack.subject') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1b1f26;line-height:1.5;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e1e6ec;border-radius:12px;padding:28px 32px;">
        <h1 style="margin:0 0 16px;font-size:1.4rem;color:#161b22;">{{ __('mail.ack.heading') }}</h1>

        <p style="margin:0 0 12px;">{{ __('mail.ack.greeting', ['name' => $withdrawal->name]) }}</p>
        <p style="margin:0 0 20px;">{{ __('mail.ack.intro') }}</p>

        <h2 style="margin:0 0 10px;font-size:1.05rem;color:#161b22;">{{ __('mail.ack.declaration_heading') }}</h2>
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:6px 0;color:#5d6776;width:42%;">{{ __('mail.field.name') }}</td><td style="padding:6px 0;">{{ $withdrawal->name }}</td></tr>
            <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.email') }}</td><td style="padding:6px 0;">{{ $withdrawal->email }}</td></tr>
            @if (filled($withdrawal->order_number))
                <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.order') }}</td><td style="padding:6px 0;">{{ $withdrawal->order_number }}</td></tr>
            @endif
            <tr><td style="padding:6px 0;color:#5d6776;vertical-align:top;">{{ __('mail.field.subject') }}</td><td style="padding:6px 0;">{{ $withdrawal->subject }}</td></tr>
            <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.datetime') }}</td><td style="padding:6px 0;">@include('mail.withdrawal.received-at', ['at' => $withdrawal->created_at])</td></tr>
        </table>

        <p style="margin:20px 0 0;color:#5d6776;font-size:.95rem;">{{ __('mail.ack.outro') }}</p>
    </div>
</body>
</html>

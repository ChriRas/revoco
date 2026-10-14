@use('App\Spam\SpamLinks')
@use('App\Spam\SpamReasonLabels')
{{-- One digest entry; $action is the SpamLinkAction its signed link applies, $linkLabel its text. --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border-top:1px solid #e4e8ee;margin-top:12px;">
    <tr><td style="padding:6px 0;color:#5d6776;width:34%;">{{ __('mail.field.datetime') }}</td><td style="padding:6px 0;">@include('mail.withdrawal.received-at', ['at' => $entry->created_at])</td></tr>
    <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.name') }}</td><td style="padding:6px 0;">{{ $entry->name }}</td></tr>
    <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.field.email') }}</td><td style="padding:6px 0;">{{ $entry->email }}</td></tr>
    <tr><td style="padding:6px 0;color:#5d6776;">{{ __('mail.digest.reason') }}</td><td style="padding:6px 0;">{{ SpamReasonLabels::render($entry->spam_reason) ?? '—' }}</td></tr>
    <tr><td colspan="2" style="padding:6px 0;"><a href="{{ SpamLinks::url($entry, $action) }}" style="color:#33576f;font-weight:600;">{{ $linkLabel }}</a></td></tr>
</table>

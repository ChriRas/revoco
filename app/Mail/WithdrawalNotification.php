<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\SpamLevel;
use App\Enums\SpamLinkAction;
use App\Models\Withdrawal;
use App\Spam\SpamLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Merchant notification — sent to the configured operator address for green and
 * yellow rows (red rows only appear in the spam digest). Carries the full case data,
 * the spam status for triage and a signed "mark as spam" link. Operator-facing
 * (rendered in the app default locale).
 */
final class WithdrawalNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Withdrawal $withdrawal) {}

    public function envelope(): Envelope
    {
        $subject = __('mail.notification.subject');

        // Spam verdict up front, not appended — the operator must see it at a
        // glance in an inbox list without opening or widening the subject.
        if ($this->withdrawal->spam_level !== SpamLevel::Green) {
            $subject = __('mail.notification.spam_prefix').' '.$subject;
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.withdrawal.notification',
            with: [
                // An unsaved row (the settings page's test mail) has no entry to link to.
                'markSpamUrl' => $this->withdrawal->exists
                    ? SpamLinks::url($this->withdrawal, SpamLinkAction::Spam)
                    : null,
            ],
        );
    }
}

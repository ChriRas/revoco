<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Merchant notification — always sent to the configured operator address.
 * Carries the full case data AND the spam status for triage. Operator-facing
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
        if ($this->withdrawal->spam) {
            $subject = __('mail.notification.spam_prefix').' '.$subject;
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.withdrawal.notification');
    }
}

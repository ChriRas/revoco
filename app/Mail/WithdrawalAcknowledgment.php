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
 * Consumer receipt acknowledgment (§ 356a Abs. 4) — always sent, even for
 * spam-flagged rows. Confirms RECEIPT of the declaration only; contains the
 * declaration content + date/time in the consumer's local time (Europe/Berlin)
 * and NO advertising. Rendered in the consumer's locale (set by the listener).
 */
final class WithdrawalAcknowledgment extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Withdrawal $withdrawal) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.ack.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.withdrawal.acknowledgment');
    }
}

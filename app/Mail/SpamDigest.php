<?php

declare(strict_types=1);

namespace App\Mail;

use App\Filament\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Morning digest of red, unresolved withdrawals: the only operator signal for them.
 * Lists up to {@see self::MAX_ENTRIES} entries with a "not spam" link each and a count
 * of the rest. Operator-facing (rendered in the app default locale).
 */
final class SpamDigest extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Entries listed before the mail falls back to "… and N more". */
    public const int MAX_ENTRIES = 50;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  Collection<int, Withdrawal>  $entries  at most MAX_ENTRIES rows
     * @param  int  $total  all rows the digest covers
     */
    public function __construct(
        public readonly Collection $entries,
        public readonly int $total,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans_choice('mail.digest.subject', $this->total, ['count' => $this->total]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.spam.digest',
            with: [
                'more' => max(0, $this->total - $this->entries->count()),
                'panelUrl' => WithdrawalResource::getUrl('index', ['tab' => 'spam'], panel: 'admin'),
            ],
        );
    }
}

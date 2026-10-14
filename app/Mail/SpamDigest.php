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
 * Morning digest of unjudged withdrawals in two sections: red rows (the only operator signal
 * for them) with a "not spam" link each, and yellow rows (suspected, but acknowledged and
 * reported) with a "mark as spam" link each. Each section lists up to
 * {@see self::MAX_ENTRIES} entries and counts the rest. Operator-facing (rendered in the app
 * default locale).
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
     * @param  Collection<int, Withdrawal>  $entries  red rows, at most MAX_ENTRIES
     * @param  int  $total  all red rows the digest covers
     * @param  Collection<int, Withdrawal>|null  $suspected  yellow rows, at most MAX_ENTRIES
     * @param  int  $suspectedTotal  all yellow rows the digest covers
     */
    public function __construct(
        public readonly Collection $entries,
        public readonly int $total,
        public readonly ?Collection $suspected = null,
        public readonly int $suspectedTotal = 0,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->total + $this->suspectedTotal;

        return new Envelope(subject: trans_choice('mail.digest.subject', $count, ['count' => $count]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.spam.digest',
            with: [
                'more' => max(0, $this->total - $this->entries->count()),
                'suspectedEntries' => $this->suspected ?? new Collection,
                'suspectedMore' => max(0, $this->suspectedTotal - ($this->suspected?->count() ?? 0)),
                // The spam tab when there are red rows, else the inbox the yellow rows live in.
                'panelUrl' => WithdrawalResource::getUrl('index', $this->total > 0 ? ['tab' => 'spam'] : [], panel: 'admin'),
            ],
        );
    }
}

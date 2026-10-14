<?php

declare(strict_types=1);

namespace App\Mail;

use App\Filament\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use App\Spam\SpamDigestWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Morning digest since the last one: unjudged red rows (the only operator signal for them)
 * with a "not spam" link each, unjudged yellow rows (suspected, but acknowledged and
 * reported) with a "mark as spam" link each — each section up to {@see self::MAX_ENTRIES}
 * entries plus a count of the rest — and a count line for the rows sorted out automatically,
 * with the date their purge starts. Operator-facing (rendered in the app default locale).
 */
final class SpamDigest extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Entries listed per section before the mail falls back to "… and N more". */
    public const int MAX_ENTRIES = SpamDigestWindow::MAX_ENTRIES;

    public int $tries = 3;

    public int $backoff = 30;

    /** @var Collection<int, Withdrawal> red rows, at most MAX_ENTRIES */
    public readonly Collection $entries;

    /** All red rows the digest covers. */
    public readonly int $total;

    /** @var Collection<int, Withdrawal> yellow rows, at most MAX_ENTRIES */
    public readonly Collection $suspected;

    /** All yellow rows the digest covers. */
    public readonly int $suspectedTotal;

    /** Rows sorted out automatically in the window (count only). */
    public readonly int $autoSortedTotal;

    /** When the first of them is purged. */
    public readonly ?Carbon $autoPurgeFrom;

    /** Flattened into top-level properties, so SerializesModels queues the rows as identifiers. */
    public function __construct(SpamDigestWindow $window)
    {
        $this->entries = $window->spam;
        $this->total = $window->spamTotal;
        $this->suspected = $window->suspected;
        $this->suspectedTotal = $window->suspectedTotal;
        $this->autoSortedTotal = $window->autoSortedTotal;
        $this->autoPurgeFrom = $window->autoPurgeFrom;
    }

    public function envelope(): Envelope
    {
        $count = $this->total + $this->suspectedTotal + $this->autoSortedTotal;

        return new Envelope(subject: trans_choice('mail.digest.subject', $count, ['count' => $count]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.spam.digest',
            with: [
                'more' => max(0, $this->total - $this->entries->count()),
                'suspectedEntries' => $this->suspected,
                'suspectedMore' => max(0, $this->suspectedTotal - $this->suspected->count()),
                'autoPurgeDate' => $this->autoPurgeFrom?->timezone('Europe/Berlin')->format('d.m.Y'),
                'spamTabUrl' => WithdrawalResource::getUrl('index', ['tab' => 'spam'], panel: 'admin'),
                // The spam tab when there are red rows, else the inbox the yellow rows live in.
                'panelUrl' => WithdrawalResource::getUrl('index', $this->total > 0 ? ['tab' => 'spam'] : [], panel: 'admin'),
            ],
        );
    }
}

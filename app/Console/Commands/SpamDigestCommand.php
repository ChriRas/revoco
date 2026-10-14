<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SpamLevel;
use App\Mail\SpamDigest;
use App\Models\Withdrawal;
use App\Support\NotificationRecipient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the morning digest of the red and the yellow withdrawals the operator has not yet
 * judged and that arrived in the last 24 hours, in two sections. Auto-sorted rows carry a
 * verdict and are left out. Scheduled daily at the time set on the notifications page; can
 * also be run by hand. Takes no input, so it is safe under --no-interaction. Sends nothing,
 * and says why, when there is nothing to report.
 */
final class SpamDigestCommand extends Command
{
    protected $signature = 'revoco:spam-digest';

    protected $description = 'Queue the digest mail of red and yellow, unjudged withdrawals from the last 24 hours.';

    public function handle(): int
    {
        $recipient = NotificationRecipient::resolve();

        if ($recipient === null) {
            $this->line('No digest sent: no notification recipient is configured.');

            return self::SUCCESS;
        }

        $spam = $this->unjudged(SpamLevel::Red);
        $suspected = $this->unjudged(SpamLevel::Yellow);

        $total = $spam->count();
        $suspectedTotal = $suspected->count();

        if ($total === 0 && $suspectedTotal === 0) {
            $this->line('No digest sent: no unresolved spam or suspected withdrawals in the last 24 hours.');

            return self::SUCCESS;
        }

        Mail::to($recipient)->send(
            (new SpamDigest($this->firstEntries($spam), $total, $this->firstEntries($suspected), $suspectedTotal))
                ->locale(Config::string('app.default_locale')),
        );

        $this->line("Digest queued: {$total} spam, {$suspectedTotal} suspected.");

        return self::SUCCESS;
    }

    /**
     * Rows of the given level without a verdict from the last 24 hours.
     *
     * @return Builder<Withdrawal>
     */
    private function unjudged(SpamLevel $level): Builder
    {
        return Withdrawal::query()
            ->where('spam_level', $level)
            ->whereNull('spam_verdict')
            ->where('created_at', '>=', now()->subDay());
    }

    /**
     * @param  Builder<Withdrawal>  $query
     * @return Collection<int, Withdrawal>
     */
    private function firstEntries(Builder $query): Collection
    {
        return $query->orderBy('created_at')->orderBy('id')->limit(SpamDigest::MAX_ENTRIES)->get();
    }
}

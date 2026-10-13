<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SpamLevel;
use App\Mail\SpamDigest;
use App\Models\Withdrawal;
use App\Support\NotificationRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the morning digest of red withdrawals the operator has not yet resolved and
 * that arrived in the last 24 hours. Scheduled daily at the time set on the
 * notifications page; can also be run by hand. Takes no input, so it is safe under
 * --no-interaction. Sends nothing, and says why, when there is nothing to report.
 */
final class SpamDigestCommand extends Command
{
    protected $signature = 'revoco:spam-digest';

    protected $description = 'Queue the digest mail of red, unresolved withdrawals from the last 24 hours.';

    public function handle(): int
    {
        $recipient = NotificationRecipient::resolve();

        if ($recipient === null) {
            $this->line('No digest sent: no notification recipient is configured.');

            return self::SUCCESS;
        }

        $query = Withdrawal::query()
            ->where('spam_level', SpamLevel::Red)
            ->whereNull('spam_verdict')
            ->where('created_at', '>=', now()->subDay());

        $total = $query->count();

        if ($total === 0) {
            $this->line('No digest sent: no unresolved spam in the last 24 hours.');

            return self::SUCCESS;
        }

        $entries = $query->orderBy('created_at')->orderBy('id')->limit(SpamDigest::MAX_ENTRIES)->get();

        Mail::to($recipient)->send(
            (new SpamDigest($entries, $total))->locale(Config::string('app.default_locale')),
        );

        $this->line("Digest queued: {$total} entries.");

        return self::SUCCESS;
    }
}

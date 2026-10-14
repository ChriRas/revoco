<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SpamDigest;
use App\Models\Withdrawal;
use App\Settings\NotificationSettings;
use App\Spam\SpamDigestWindow;
use App\Support\NotificationRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the morning digest of everything received since the last digest was queued (at most
 * {@see Withdrawal::SPAM_RETENTION_DAYS} days back, also on the first run): the red and the yellow
 * withdrawals the operator has not yet judged, in two sections, and a count of the rows sorted out
 * automatically before they are purged. A skipped or empty run moves nothing forward, so no row
 * misses the digest. Scheduled daily at the time set on the notifications page; can also be run by
 * hand. Takes no input, so it is safe under --no-interaction. Sends nothing, and says why, when
 * there is nothing to report.
 */
final class SpamDigestCommand extends Command
{
    protected $signature = 'revoco:spam-digest';

    protected $description = 'Queue the digest mail of unjudged red and yellow withdrawals and auto-sorted spam since the last digest.';

    public function handle(NotificationSettings $settings): int
    {
        $recipient = NotificationRecipient::resolve();

        if ($recipient === null) {
            $this->line('No digest sent: no notification recipient is configured.');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();
        $since = $this->since($settings, $now);

        $window = SpamDigestWindow::since($since);

        if ($window->isEmpty()) {
            $this->line('No digest sent: nothing to report since the last digest.');

            return self::SUCCESS;
        }

        Mail::to($recipient)->send((new SpamDigest($window))->locale(Config::string('app.default_locale')));

        $settings->spam_digest_last_sent_at = $now->toIso8601String();
        $settings->save();

        $this->line("Digest queued: {$window->spamTotal} spam, {$window->suspectedTotal} suspected, {$window->autoSortedTotal} auto-sorted.");

        return self::SUCCESS;
    }

    /** The window start: the last queued digest, but never more than the purge horizon back. */
    private function since(NotificationSettings $settings, CarbonImmutable $now): CarbonImmutable
    {
        $horizon = $now->subDays(Withdrawal::SPAM_RETENTION_DAYS);
        $last = $settings->spam_digest_last_sent_at;

        return $last === null ? $horizon : CarbonImmutable::parse($last)->max($horizon);
    }
}

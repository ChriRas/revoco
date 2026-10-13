<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SpamVerdict;
use App\Events\WithdrawalMarkedNotSpam;
use App\Models\Withdrawal;

/**
 * Records the operator's "not spam" verdict; this also cancels a pending purge.
 * Idempotent: the same verdict again changes nothing and fires no event.
 * Returns whether a change was made.
 */
final class MarkWithdrawalAsNotSpam
{
    public function handle(Withdrawal $withdrawal): bool
    {
        if ($withdrawal->spam_verdict === SpamVerdict::NotSpam) {
            return false;
        }

        $withdrawal->spam_verdict = SpamVerdict::NotSpam;
        $withdrawal->spam_verdict_at = now();
        $withdrawal->save();

        WithdrawalMarkedNotSpam::dispatch($withdrawal);

        return true;
    }
}

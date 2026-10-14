<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Events\WithdrawalConfirmedAsSpam;
use App\Models\Withdrawal;

/**
 * Records the operator's "spam" verdict. Idempotent: the same verdict again
 * changes nothing and fires no event. Returns whether a change was made.
 */
final class MarkWithdrawalAsSpam
{
    public function handle(Withdrawal $withdrawal): bool
    {
        if ($withdrawal->spam_verdict === SpamVerdict::Spam) {
            return false;
        }

        $withdrawal->spam_verdict = SpamVerdict::Spam;
        $withdrawal->spam_verdict_at = now();
        $withdrawal->spam_verdict_source = SpamVerdictSource::Operator;
        $withdrawal->save();

        WithdrawalConfirmedAsSpam::dispatch($withdrawal);

        return true;
    }
}

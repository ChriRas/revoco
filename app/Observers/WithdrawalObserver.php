<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Withdrawal;
use App\Statistics\DailyCountRecorder;

/**
 * Feeds the daily counters from the one place every writer of the spam columns passes through:
 * the submit (with auto-sorting), the verdict actions, bulk actions and the signed mail links.
 * Deliberately no `deleted` handler: a purge never takes a count away.
 */
final class WithdrawalObserver
{
    public function __construct(private readonly DailyCountRecorder $recorder) {}

    public function created(Withdrawal $withdrawal): void
    {
        $this->recorder->record($withdrawal);
    }

    public function updated(Withdrawal $withdrawal): void
    {
        if ($withdrawal->wasChanged(['spam_level', 'spam_verdict', 'spam_verdict_source'])) {
            $this->recorder->move($withdrawal);
        }
    }
}

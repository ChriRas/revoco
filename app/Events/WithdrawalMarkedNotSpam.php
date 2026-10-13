<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Withdrawal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when the operator marks a withdrawal as not spam.
 * Re-sends the withheld acknowledgment (SendAcknowledgmentOnNotSpam); later slices attach more.
 */
final class WithdrawalMarkedNotSpam
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Withdrawal $withdrawal) {}
}

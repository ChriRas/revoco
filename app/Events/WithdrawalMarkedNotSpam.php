<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Withdrawal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when the operator marks a withdrawal as not spam.
 * Re-sends the withheld acknowledgment (SendAcknowledgmentOnNotSpam) and takes the row's
 * learned-network hit back (ForgetNetworkHitOnNotSpam); later slices attach more.
 */
final class WithdrawalMarkedNotSpam
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Withdrawal $withdrawal) {}
}

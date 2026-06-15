<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Withdrawal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the StoreWithdrawal action once a declaration is persisted.
 *
 * Queued listeners fan out the delivery channels (consumer acknowledgment,
 * merchant notification, optional ntfy push) — all off the request, so the
 * submit never waits on or fails because of SMTP/push.
 */
final class WithdrawalSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Withdrawal $withdrawal) {}
}

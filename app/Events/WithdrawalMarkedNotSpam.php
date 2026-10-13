<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Withdrawal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when the operator marks a withdrawal as not spam.
 * A hook for later listeners; nothing listens yet.
 */
final class WithdrawalMarkedNotSpam
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Withdrawal $withdrawal) {}
}

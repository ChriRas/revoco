<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\WithdrawalSubmitted;
use App\Models\Withdrawal;

/**
 * Persists a withdrawal declaration (domain logic per project rules).
 *
 * Captures the consumer's active locale (drives the acknowledgment language) and
 * stamps `created_at` in the app timezone (Europe/Berlin). The spam flag is a
 * non-blocking triage signal decided upstream — this action never rejects a
 * submission (§ 356a: the receipt must never be prevented). After persisting it
 * fires `WithdrawalSubmitted`; queued listeners handle delivery off the request.
 */
final class StoreWithdrawal
{
    public function handle(
        string $name,
        string $email,
        ?string $orderNumber,
        string $subject,
        bool $spam = false,
        ?string $spamReason = null,
    ): Withdrawal {
        $withdrawal = Withdrawal::create([
            'name' => $name,
            'email' => $email,
            'order_number' => $orderNumber,
            'subject' => $subject,
            'locale' => app()->getLocale(),
            'spam' => $spam,
            'spam_reason' => $spamReason,
        ]);

        WithdrawalSubmitted::dispatch($withdrawal);

        return $withdrawal;
    }
}

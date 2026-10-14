<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Events\WithdrawalSubmitted;
use App\Models\Withdrawal;
use App\Network\NetworkInfo;
use App\Spam\SpamAssessment;

/**
 * Persists a withdrawal declaration (domain logic per project rules).
 *
 * Captures the consumer's active locale (drives the acknowledgment language) and
 * stamps `created_at` in the app timezone (Europe/Berlin). The spam assessment (score,
 * level, fired signals) is a non-blocking triage signal decided upstream — this action never rejects a
 * submission (§ 356a: the receipt must never be prevented). The network info (country, ASN)
 * was derived from the client IP upstream; the IP itself is never stored. After persisting it
 * fires `WithdrawalSubmitted`; queued listeners handle delivery off the request.
 *
 * A submit the auto-sorting rule judged definite spam is stored with the verdict "spam"
 * (source `auto`) — still stored, never blocked. It deliberately fires no
 * `WithdrawalConfirmedAsSpam`: the memories learn only from the operator's own verdicts.
 */
final class StoreWithdrawal
{
    public function handle(
        string $name,
        string $email,
        ?string $orderNumber,
        string $subject,
        SpamAssessment $assessment,
        ?NetworkInfo $network = null,
    ): Withdrawal {
        $withdrawal = new Withdrawal([
            'name' => $name,
            'email' => $email,
            'order_number' => $orderNumber,
            'subject' => $subject,
            'locale' => app()->getLocale(),
            'spam_score' => $assessment->score,
            'spam_level' => $assessment->level,
            'spam_reason' => $assessment->reason(),
            'country' => $network?->country,
            'asn' => $network?->asn,
            'asn_org' => $network?->asnOrg,
        ]);

        if ($assessment->autoConfirmed) {
            $withdrawal->spam_verdict = SpamVerdict::Spam;
            $withdrawal->spam_verdict_at = now();
            $withdrawal->spam_verdict_source = SpamVerdictSource::Auto;
        }

        $withdrawal->save();

        WithdrawalSubmitted::dispatch($withdrawal);

        return $withdrawal;
    }
}

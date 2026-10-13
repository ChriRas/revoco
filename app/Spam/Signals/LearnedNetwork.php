<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Models\SpamNetworkHit;
use App\Spam\SubmissionContext;
use Illuminate\Support\Facades\Config;

/**
 * The ASN of the IP has repeatedly been confirmed as spam by the operator: at least
 * `revoco.network.learning.min_hits` confirmed rows within `window_days`. Several hits are
 * required so one confirmed row of a large consumer ISP cannot taint all its customers.
 */
final class LearnedNetwork extends AbstractNetworkSignal
{
    public function key(): string
    {
        return 'learned_network';
    }

    protected function matches(SubmissionContext $context): bool
    {
        $asn = $context->network?->asn;

        if ($asn === null) {
            return false;
        }

        return SpamNetworkHit::query()
            ->where('asn', $asn)
            ->where('confirmed_at', '>', now()->subDays(Config::integer('revoco.network.learning.window_days')))
            ->count() >= Config::integer('revoco.network.learning.min_hits');
    }
}

<?php

declare(strict_types=1);

namespace App\Spam;

use App\Enums\SpamLevel;
use Illuminate\Support\Facades\Config;

/**
 * Sums the configured weights of the signals that fired and sorts the score into a level.
 *
 * Weights and thresholds are read from `config('revoco.spam')` on every call. The
 * signals are the container tag `spam.signals`; appending to it is the extension point.
 */
final class SpamScorer
{
    /**
     * @param  iterable<SpamSignal>  $signals
     */
    public function __construct(private readonly iterable $signals) {}

    public function assess(SubmissionContext $context): SpamAssessment
    {
        $fired = [];

        foreach ($this->signals as $signal) {
            if ($signal->fires($context)) {
                $fired[$signal->key()] = $this->weight($signal->key());
            }
        }

        // Heaviest first; equal weights keep the registration order (usort is stable).
        $keys = array_keys($fired);
        usort($keys, fn (string $a, string $b): int => $fired[$b] <=> $fired[$a]);

        $score = array_sum($fired);

        return new SpamAssessment($score, $this->level($score), $keys);
    }

    private function weight(string $key): int
    {
        return Config::integer('revoco.spam.weights.'.$key, 0);
    }

    private function level(int $score): SpamLevel
    {
        return match (true) {
            $score >= Config::integer('revoco.spam.thresholds.red') => SpamLevel::Red,
            $score >= Config::integer('revoco.spam.thresholds.yellow') => SpamLevel::Yellow,
            default => SpamLevel::Green,
        };
    }
}

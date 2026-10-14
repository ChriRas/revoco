<?php

declare(strict_types=1);

namespace App\Spam;

use App\Enums\SpamLevel;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Sums the configured weights of the signals that fired and sorts the score into a level.
 *
 * Weights and thresholds are read from `config('revoco.spam')` on every call. The
 * signals are the container tag `spam.signals`; appending to it is the extension point.
 * A signal that throws is reported and counts as not fired: scoring must never cost the
 * submit (§ 356a — the declaration is always stored).
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
            if ($this->firesSafely($signal, $context)) {
                $fired[$signal->key()] = $this->weight($signal->key());
            }
        }

        // Heaviest first; equal weights keep the registration order (usort is stable).
        $keys = array_keys($fired);
        usort($keys, fn (string $a, string $b): int => $fired[$b] <=> $fired[$a]);

        $score = array_sum($fired);

        return new SpamAssessment($score, $this->level($score), $keys, $this->autoConfirmed($keys));
    }

    /**
     * The auto-sorting rule: the trigger signal plus at least one of the configured bot signals.
     *
     * @param  list<string>  $keys
     */
    private function autoConfirmed(array $keys): bool
    {
        $trigger = Config::string('revoco.spam.auto_confirm.trigger', '');
        /** @var list<string> $withAny */
        $withAny = Config::array('revoco.spam.auto_confirm.with_any', []);

        return $trigger !== ''
            && in_array($trigger, $keys, true)
            && array_intersect($withAny, $keys) !== [];
    }

    private function firesSafely(SpamSignal $signal, SubmissionContext $context): bool
    {
        try {
            return $signal->fires($context);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
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

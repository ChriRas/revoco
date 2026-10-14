<?php

declare(strict_types=1);

namespace App\Spam;

use App\Enums\SpamLevel;

/**
 * The scorer's result for one submit: score, level, the signal keys that fired (heaviest
 * first) and whether the auto-sorting rule judged it definite spam.
 */
final readonly class SpamAssessment
{
    /**
     * @param  list<string>  $signals
     */
    public function __construct(
        public int $score,
        public SpamLevel $level,
        public array $signals,
        public bool $autoConfirmed = false,
    ) {}

    /** The stored `spam_reason` value: comma-separated keys, null when none fired. */
    public function reason(): ?string
    {
        return $this->signals === [] ? null : implode(',', $this->signals);
    }
}

<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Soft rate limit: more than 5 submits per IP within 60 seconds.
 *
 * Evaluating the signal counts the submit (a hit) unless the limit is already
 * exceeded. The IP is used only as a hashed, transient cache key — never persisted.
 */
final class Throttle implements SpamSignal
{
    /** Submits allowed per IP per window before the signal fires. */
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    public function key(): string
    {
        return 'throttle';
    }

    public function fires(SubmissionContext $context): bool
    {
        $key = 'withdrawal-submit:'.sha1((string) $context->ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return true;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return false;
    }
}

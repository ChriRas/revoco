<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/** The hidden decoy field was filled in: a bot, not a consumer. */
final class Honeypot implements SpamSignal
{
    public function key(): string
    {
        return 'honeypot';
    }

    public function fires(SubmissionContext $context): bool
    {
        return $context->honeypotFilled;
    }
}

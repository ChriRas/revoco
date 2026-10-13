<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;
use Throwable;

/**
 * Base of the network signals: they read local data (files, settings, a table) and must
 * never fail the submit. Whatever goes wrong while looking, the signal simply does not
 * fire, and the failure is reported. Unknown data never penalises.
 */
abstract class AbstractNetworkSignal implements SpamSignal
{
    final public function fires(SubmissionContext $context): bool
    {
        try {
            return $this->matches($context);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    abstract protected function matches(SubmissionContext $context): bool;
}

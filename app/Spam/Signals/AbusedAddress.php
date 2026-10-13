<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Services\AbusedAddressMemory;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;
use Throwable;

/**
 * The submitted address is in the memory of addresses from confirmed spam. Its weight keeps a
 * hit alone yellow (suspicion only) and never red. A failing lookup must not fail the submit:
 * the signal then simply does not fire, and the failure is reported.
 */
final class AbusedAddress implements SpamSignal
{
    public function __construct(private readonly AbusedAddressMemory $memory) {}

    public function key(): string
    {
        return 'abused_address';
    }

    public function fires(SubmissionContext $context): bool
    {
        if ($context->email === null) {
            return false;
        }

        try {
            return $this->memory->isKnown($context->email);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}

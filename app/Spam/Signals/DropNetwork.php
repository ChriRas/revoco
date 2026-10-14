<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Network\NetworkData;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/** The IP lies inside a listed IP block (hijacked or abused address space, IPv4 and IPv6). */
final class DropNetwork implements SpamSignal
{
    public function __construct(private readonly NetworkData $data) {}

    public function key(): string
    {
        return 'drop_network';
    }

    public function fires(SubmissionContext $context): bool
    {
        return $context->ip !== null && $this->data->isInDrop($context->ip);
    }
}

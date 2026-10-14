<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Network\NetworkData;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/** The IP is a Tor exit node. Privacy users are legitimate, hence the near-neutral weight. */
final class TorExit implements SpamSignal
{
    public function __construct(private readonly NetworkData $data) {}

    public function key(): string
    {
        return 'tor_exit';
    }

    public function fires(SubmissionContext $context): bool
    {
        return $context->ip !== null && $this->data->isTorExit($context->ip);
    }
}

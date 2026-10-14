<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Network\NetworkData;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/** The IP belongs to a listed network operator (ASN list). */
final class AsnDrop implements SpamSignal
{
    public function __construct(private readonly NetworkData $data) {}

    public function key(): string
    {
        return 'asn_drop';
    }

    public function fires(SubmissionContext $context): bool
    {
        $asn = $context->network?->asn;

        return $asn !== null && $this->data->isAsnListed($asn);
    }
}

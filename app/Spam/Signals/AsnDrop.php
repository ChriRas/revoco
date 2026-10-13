<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Network\NetworkData;
use App\Spam\SubmissionContext;

/** The IP belongs to a listed network operator (ASN list). */
final class AsnDrop extends AbstractNetworkSignal
{
    public function __construct(private readonly NetworkData $data) {}

    public function key(): string
    {
        return 'asn_drop';
    }

    protected function matches(SubmissionContext $context): bool
    {
        $asn = $context->network?->asn;

        return $asn !== null && $this->data->isAsnListed($asn);
    }
}

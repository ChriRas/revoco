<?php

declare(strict_types=1);

namespace App\Network;

/** What the local lookup knows of a client IP: ISO-3166 alpha-2 country and the ASN with its organisation. */
final readonly class NetworkInfo
{
    public function __construct(
        public ?string $country = null,
        public ?int $asn = null,
        public ?string $asnOrg = null,
    ) {}

    /** Nothing resolved (private IP, no data, unreadable database). */
    public static function unknown(): self
    {
        return new self;
    }
}

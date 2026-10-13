<?php

declare(strict_types=1);

namespace App\Network;

/**
 * IP address to country and ASN, resolved locally. An implementation never throws
 * (the submit must not fail on it) and answers {@see NetworkInfo::unknown()} when it cannot tell.
 */
interface NetworkLookup
{
    public function lookup(?string $ip): NetworkInfo;
}

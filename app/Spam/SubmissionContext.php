<?php

declare(strict_types=1);

namespace App\Spam;

use Carbon\CarbonImmutable;

/**
 * What the signals get to see of one submit. Transient: built per request, used in
 * memory only and never persisted (the IP and the user agent are data-minimised).
 */
final readonly class SubmissionContext
{
    public function __construct(
        public string $name,
        public ?string $orderNumber,
        public string $subject,
        public bool $honeypotFilled,
        public ?string $renderToken,
        public ?string $userAgent,
        public ?string $ip,
        public CarbonImmutable $submittedAt,
    ) {}
}

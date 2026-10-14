<?php

declare(strict_types=1);

namespace App\Delivery;

use App\Enums\SpamLevel;

/**
 * Which delivery channels a withdrawal of a given spam level reaches at submit.
 * The row is stored either way; this only decides what is sent.
 */
final readonly class ChannelPolicy
{
    public function __construct(
        public bool $acknowledgment,
        public bool $merchantMail,
        public bool $push,
        /** Whether the merchant mail and the push carry the "spam suspected" marker. */
        public bool $suspected,
    ) {}

    public static function for(SpamLevel $level): self
    {
        return match ($level) {
            SpamLevel::Green => new self(acknowledgment: true, merchantMail: true, push: true, suspected: false),
            SpamLevel::Yellow => new self(acknowledgment: true, merchantMail: true, push: true, suspected: true),
            SpamLevel::Red => new self(acknowledgment: false, merchantMail: false, push: false, suspected: true),
        };
    }
}

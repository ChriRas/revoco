<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AbusedAddress;
use App\Support\AbusedAddressHasher;

/**
 * Remembers which e-mail addresses showed up in confirmed spam, as hashes only.
 * An entry counts for one year after its last confirmation; `isKnown` enforces that
 * itself, so it does not depend on the scheduled prune having run.
 */
class AbusedAddressMemory
{
    public function __construct(private readonly AbusedAddressHasher $hasher) {}

    /** Upserts the address and slides its expiry to now. */
    public function remember(#[\SensitiveParameter] string $email): void
    {
        AbusedAddress::query()->updateOrCreate(
            ['hash' => $this->hasher->hash($email)],
            ['last_seen_at' => now()],
        );
    }

    public function forget(#[\SensitiveParameter] string $email): void
    {
        AbusedAddress::query()->where('hash', $this->hasher->hash($email))->delete();
    }

    public function isKnown(#[\SensitiveParameter] string $email): bool
    {
        return AbusedAddress::query()
            ->where('hash', $this->hasher->hash($email))
            ->where('last_seen_at', '>=', now()->subYears(AbusedAddress::RETENTION_YEARS))
            ->exists();
    }
}

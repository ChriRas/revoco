<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The pseudonymised e-mail address of a withdrawal the operator confirmed as spam: an
 * HMAC hash, never the address. `last_seen_at` is the last confirmation; the entry counts
 * for one year after it.
 *
 * @property int $id
 * @property string $hash
 * @property Carbon $last_seen_at
 */
final class AbusedAddress extends Model
{
    use MassPrunable;

    /** How long an entry counts after its last confirmation. */
    public const int RETENTION_YEARS = 1;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Entries past their expiry; `model:prune` reclaims them. Lookups filter by expiry themselves.
     *
     * @return Builder<AbusedAddress>
     */
    public function prunable(): Builder
    {
        return self::query()->where('last_seen_at', '<', now()->subYears(self::RETENTION_YEARS));
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * The ASN of one withdrawal the operator confirmed as spam. The `learned_network` signal
 * counts the recent hits per ASN. Keyed by the withdrawal id without a foreign key, since
 * confirmed rows are purged after 7 days while their hit stays for the learning window.
 *
 * @property int $withdrawal_id
 * @property int $asn
 * @property Carbon $confirmed_at
 */
final class SpamNetworkHit extends Model
{
    use Prunable;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'withdrawal_id';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'withdrawal_id' => 'integer',
            'asn' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Hits older than the learning window no longer count and are deleted by `model:prune`.
     *
     * @return Builder<SpamNetworkHit>
     */
    public function prunable(): Builder
    {
        return self::query()->where('confirmed_at', '<=', now()->subDays(Config::integer('revoco.network.learning.window_days')));
    }
}

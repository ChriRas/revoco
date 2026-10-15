<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SpamLevel;
use App\Statistics\Outcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One anonymous daily counter: how many withdrawals of one stored state (level x outcome) were
 * received on a day, in total (`signal = ''`) or carrying one fired signal key.
 *
 * The read side of the counters; the recorder writes them through the query builder.
 *
 * @property Carbon $day
 * @property SpamLevel $level
 * @property Outcome $outcome
 * @property string $signal
 * @property int $count
 */
final class WithdrawalDailyCount extends Model
{
    /** The table has no surrogate id (its unique key is the identity), so instances are read-only. */
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'withdrawal_daily_counts';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date:Y-m-d',
            'level' => SpamLevel::class,
            'outcome' => Outcome::class,
            'count' => 'integer',
        ];
    }

    /**
     * The total rows: each declaration counted once.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTotalRows(Builder $query): void
    {
        $query->where('signal', '');
    }

    /**
     * The per-signal rows: a declaration counted once per fired signal.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSignalRows(Builder $query): void
    {
        $query->where('signal', '<>', '');
    }
}

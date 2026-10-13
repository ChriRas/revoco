<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AcknowledgmentTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Insert-only record that a withdrawal's acknowledgment was queued. It keeps the
 * withdrawal row itself untouched and, through the unique `withdrawal_id`, makes sure
 * the acknowledgment is queued at most once per row.
 *
 * @property int $id
 * @property int $withdrawal_id
 * @property Carbon $sent_at
 * @property AcknowledgmentTrigger $trigger
 */
final class AcknowledgmentLog extends Model
{
    protected $table = 'withdrawal_acknowledgments';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['withdrawal_id', 'sent_at', 'trigger'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'trigger' => AcknowledgmentTrigger::class,
        ];
    }

    /**
     * @return BelongsTo<Withdrawal, $this>
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }

    /**
     * Claims the acknowledgment for a withdrawal. Returns true for the one caller that
     * recorded it (that caller sends), false when an entry already existed.
     */
    public static function claim(Withdrawal $withdrawal, AcknowledgmentTrigger $trigger): bool
    {
        return DB::table((new self)->getTable())->insertOrIgnore([
            'withdrawal_id' => $withdrawal->id,
            'sent_at' => now(),
            'trigger' => $trigger->value,
        ]) === 1;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * A stored § 356a withdrawal declaration.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $order_number
 * @property string $subject
 * @property string $locale
 * @property int|null $spam_score
 * @property SpamLevel $spam_level
 * @property SpamVerdict|null $spam_verdict
 * @property Carbon|null $spam_verdict_at
 * @property string|null $spam_reason
 * @property string|null $country
 * @property int|null $asn
 * @property string|null $asn_org
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    use Prunable;

    /** Days a spam confirmation stays reversible before the row is purged. */
    public const int SPAM_RETENTION_DAYS = 7;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'order_number',
        'subject',
        'locale',
        'spam_score',
        'spam_level',
        'spam_reason',
        'country',
        'asn',
        'asn_org',
        // handled_at, spam_verdict and spam_verdict_at are intentionally excluded — set
        // exclusively via the operator actions, never mass-assigned (immutable-record invariant).
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spam_score' => 'integer',
            'asn' => 'integer',
            'spam_level' => SpamLevel::class,
            'spam_verdict' => SpamVerdict::class,
            'spam_verdict_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    /** Whether the operator has marked this withdrawal as handled. */
    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }

    /** Whether "Mark as spam" would change anything: the verdict is not yet "spam". */
    public function canBeMarkedSpam(): bool
    {
        return $this->spam_verdict !== SpamVerdict::Spam;
    }

    /** Whether "Not spam" would change anything: confirmed spam, or an unjudged yellow or red row. */
    public function canBeMarkedNotSpam(): bool
    {
        return $this->spam_verdict === SpamVerdict::Spam
            || ($this->spam_verdict === null && $this->spam_level !== SpamLevel::Green);
    }

    /** Whether the operator confirmed this withdrawal as spam. */
    public function isConfirmedSpam(): bool
    {
        return $this->spam_verdict === SpamVerdict::Spam;
    }

    /**
     * Rows for the default list: corrected to "not spam", or unjudged and green/yellow.
     *
     * @param  Builder<Withdrawal>  $query
     */
    public function scopeInbox(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where('spam_verdict', SpamVerdict::NotSpam)
                ->orWhere(function (Builder $q): void {
                    $q->whereNull('spam_verdict')
                        ->whereIn('spam_level', [SpamLevel::Green, SpamLevel::Yellow]);
                });
        });
    }

    /**
     * Rows for the "Spam" tab: confirmed as spam, or unjudged and red.
     *
     * @param  Builder<Withdrawal>  $query
     */
    public function scopeSpamFolder(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where('spam_verdict', SpamVerdict::Spam)
                ->orWhere(function (Builder $q): void {
                    $q->whereNull('spam_verdict')
                        ->where('spam_level', SpamLevel::Red);
                });
        });
    }

    /**
     * Rows `model:prune` hard-deletes: confirmed as spam at least 7 days ago.
     * Unconfirmed rows are never pruned.
     *
     * @return Builder<Withdrawal>
     */
    public function prunable(): Builder
    {
        return self::query()
            ->where('spam_verdict', SpamVerdict::Spam)
            ->where('spam_verdict_at', '<=', now()->subDays(self::SPAM_RETENTION_DAYS));
    }
}

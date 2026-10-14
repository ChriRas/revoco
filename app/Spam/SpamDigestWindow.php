<?php

declare(strict_types=1);

namespace App\Spam;

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * What one spam digest covers, read once from the rows received since the window start: the
 * unjudged red and yellow rows (each up to {@see self::MAX_ENTRIES}, plus their totals) and the
 * count of rows sorted out automatically, with the date their purge starts.
 */
final readonly class SpamDigestWindow
{
    /** Entries listed per section before the mail falls back to "… and N more". */
    public const int MAX_ENTRIES = 50;

    /**
     * @param  Collection<int, Withdrawal>  $spam  red rows, at most MAX_ENTRIES
     * @param  Collection<int, Withdrawal>  $suspected  yellow rows, at most MAX_ENTRIES
     */
    public function __construct(
        public Collection $spam,
        public int $spamTotal,
        public Collection $suspected,
        public int $suspectedTotal,
        public int $autoSortedTotal,
        public ?Carbon $autoPurgeFrom,
    ) {}

    public static function since(CarbonImmutable $since): self
    {
        $spam = self::unjudged(SpamLevel::Red, $since);
        $suspected = self::unjudged(SpamLevel::Yellow, $since);
        $autoSorted = Withdrawal::query()
            ->where('spam_verdict', SpamVerdict::Spam)
            ->where('spam_verdict_source', SpamVerdictSource::Auto)
            ->where('created_at', '>=', $since);

        /** @var string|null $firstVerdictAt */
        $firstVerdictAt = $autoSorted->min('spam_verdict_at');

        return new self(
            self::firstEntries($spam->clone()),
            $spam->count(),
            self::firstEntries($suspected->clone()),
            $suspected->count(),
            $autoSorted->count(),
            $firstVerdictAt === null ? null : Carbon::parse($firstVerdictAt)->addDays(Withdrawal::SPAM_RETENTION_DAYS),
        );
    }

    /** Whether there is nothing to report. */
    public function isEmpty(): bool
    {
        return $this->spamTotal === 0 && $this->suspectedTotal === 0 && $this->autoSortedTotal === 0;
    }

    /**
     * Rows of the given level without a verdict received since the window start.
     *
     * @return Builder<Withdrawal>
     */
    private static function unjudged(SpamLevel $level, CarbonImmutable $since): Builder
    {
        return Withdrawal::query()
            ->where('spam_level', $level)
            ->whereNull('spam_verdict')
            ->where('created_at', '>=', $since);
    }

    /**
     * @param  Builder<Withdrawal>  $query
     * @return Collection<int, Withdrawal>
     */
    private static function firstEntries(Builder $query): Collection
    {
        return $query->orderBy('created_at')->orderBy('id')->limit(self::MAX_ENTRIES)->get();
    }
}

<?php

declare(strict_types=1);

namespace App\Statistics;

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Models\Withdrawal;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Keeps `withdrawal_daily_counts` in step with the withdrawals: one bucket per
 * (receipt day, level, outcome, signal) with a count, and no personal data.
 *
 * Every state counts once in its total bucket (`signal = ''`) and once per fired signal key.
 * Counts sit on the receipt day: a later verdict moves a row between states on that day. A
 * deleted row never decrements anything. Writes are upserts that add to the stored count, so
 * concurrent submits cannot lose an increment.
 *
 * A counting failure is reported and swallowed: it must never cost the receipt or a verdict
 * (§ 356a; a drift of one is accepted over a failed submit).
 */
final class DailyCountRecorder
{
    private const string TABLE = 'withdrawal_daily_counts';

    /** Counts a newly stored withdrawal in its current state. */
    public function record(Withdrawal $withdrawal): void
    {
        $this->guarded(function () use ($withdrawal): void {
            foreach (self::buckets($withdrawal, current: true) as $bucket) {
                $this->add($bucket, 1);
            }
        });
    }

    /**
     * Moves a withdrawal from its original state (before the pending change) to its current one.
     * Buckets both states share cancel out.
     */
    public function move(Withdrawal $withdrawal): void
    {
        $this->guarded(function () use ($withdrawal): void {
            $deltas = [];

            foreach (self::buckets($withdrawal, current: false) as $key => $bucket) {
                $deltas[$key] = ['bucket' => $bucket, 'delta' => -1];
            }

            foreach (self::buckets($withdrawal, current: true) as $key => $bucket) {
                $deltas[$key] = ['bucket' => $bucket, 'delta' => ($deltas[$key]['delta'] ?? 0) + 1];
            }

            foreach ($deltas as $entry) {
                if ($entry['delta'] !== 0) {
                    $this->add($entry['bucket'], $entry['delta']);
                }
            }
        });
    }

    /**
     * Runs one recording as a single transaction; a failure is reported and swallowed.
     *
     * @param  Closure(): void  $recording
     */
    private function guarded(Closure $recording): void
    {
        try {
            DB::transaction($recording);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array{day: string, level: string, outcome: string, signal: string}  $bucket
     */
    private function add(array $bucket, int $delta): void
    {
        DB::table(self::TABLE)->upsert(
            [$bucket + ['count' => $delta]],
            ['day', 'level', 'outcome', 'signal'],
            ['count' => DB::raw('"count" + "excluded"."count"')],
        );
    }

    /**
     * The buckets one withdrawal occupies in its current or original state, keyed to merge cleanly.
     *
     * @return array<string, array{day: string, level: string, outcome: string, signal: string}>
     */
    private static function buckets(Withdrawal $withdrawal, bool $current): array
    {
        if ($current) {
            // An unset level reads as the column default, as in the backfill.
            $level = $withdrawal->getAttribute('spam_level');
            $level = $level instanceof SpamLevel ? $level : SpamLevel::Green;
            $outcome = Outcome::fromVerdict($withdrawal->spam_verdict, $withdrawal->spam_verdict_source);
            $reason = $withdrawal->spam_reason;
        } else {
            $level = SpamLevel::tryFrom(self::original($withdrawal, 'spam_level') ?? '') ?? SpamLevel::Green;
            $outcome = Outcome::fromVerdict(
                SpamVerdict::tryFrom(self::original($withdrawal, 'spam_verdict') ?? ''),
                SpamVerdictSource::tryFrom(self::original($withdrawal, 'spam_verdict_source') ?? ''),
            );
            $reason = self::original($withdrawal, 'spam_reason');
        }

        $day = $withdrawal->created_at?->toDateString() ?? now()->toDateString();

        $buckets = [];

        foreach (['', ...self::signalKeys($reason)] as $signal) {
            $bucket = [
                'day' => $day,
                'level' => $level->value,
                'outcome' => $outcome->value,
                'signal' => $signal,
            ];
            $buckets[implode('|', $bucket)] = $bucket;
        }

        return $buckets;
    }

    /** A column's raw value before the pending change; null when it was null. */
    private static function original(Withdrawal $withdrawal, string $column): ?string
    {
        $value = $withdrawal->getRawOriginal($column);

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function signalKeys(?string $reason): array
    {
        if ($reason === null) {
            return [];
        }

        $keys = array_map('trim', explode(',', $reason));

        return array_values(array_unique(array_filter($keys, static fn (string $key): bool => $key !== '')));
    }
}

<?php

declare(strict_types=1);

namespace App\Statistics;

use App\Enums\SpamLevel;
use App\Models\WithdrawalDailyCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Reads the anonymous daily counters for the statistics page.
 *
 * The counters store a state (level x outcome); every figure here is that state mapped through
 * `Outcome` (category, auto-sorted, false alarm, missed), so this class holds no category
 * definition of its own. Totals and history read the total rows only (each declaration counts
 * once), the per-signal table reads the per-signal rows only (once per fired signal).
 *
 * @phpstan-type Totals array{genuine: int, suspected: int, spam: int, auto_sorted: int, false_alarms: int, missed: int}
 * @phpstan-type HistoryBar array{key: string, label: string, genuine: int, suspected: int, spam: int}
 * @phpstan-type SignalRow array{signal: string, genuine: int, suspected: int, spam: int, total: int}
 */
final class SpamStatistics
{
    /**
     * @return Totals
     */
    public function totals(StatisticsPeriod $period): array
    {
        $totals = ['genuine' => 0, 'suspected' => 0, 'spam' => 0, 'auto_sorted' => 0, 'false_alarms' => 0, 'missed' => 0];

        foreach ($this->states($this->inPeriod(WithdrawalDailyCount::query()->totalRows(), $period)) as [$level, $outcome, $count]) {
            $totals[$outcome->category($level)->value] += $count;

            if ($outcome->isAutoSorted()) {
                $totals['auto_sorted'] += $count;
            }

            if ($outcome->isFalseAlarm($level)) {
                $totals['false_alarms'] += $count;
            }

            if ($outcome->isMissed($level)) {
                $totals['missed'] += $count;
            }
        }

        return $totals;
    }

    /**
     * One bar per bucket of the period's chart, empty buckets included.
     *
     * @return list<HistoryBar>
     */
    public function history(StatisticsPeriod $period): array
    {
        $granularity = $period->granularity();
        $chartPeriod = $period->chartPeriod();
        $bucket = $granularity->bucketSql();

        $bars = [];
        foreach ($this->bucketStarts($chartPeriod, $granularity) as $start) {
            $key = $granularity->key($start);
            $bars[$key] = ['key' => $key, 'label' => $granularity->label($start), 'genuine' => 0, 'suspected' => 0, 'spam' => 0];
        }

        $rows = $this->inPeriod(WithdrawalDailyCount::query()->totalRows(), $chartPeriod)
            ->selectRaw($bucket.' as bucket, level, outcome, SUM(count) as total')
            ->groupBy('bucket', 'level', 'outcome')
            ->get();

        foreach ($rows as $row) {
            $bucketKey = $row->getAttribute('bucket');
            $key = is_scalar($bucketKey) ? (string) $bucketKey : '';
            $category = $row->outcome->category($row->level)->value;
            $bars[$key] ??= ['key' => $key, 'label' => $key, 'genuine' => 0, 'suspected' => 0, 'spam' => 0];
            $bars[$key][$category] += $this->sum($row);
        }

        ksort($bars);

        return array_values($bars);
    }

    /**
     * One row per signal that fired in the period, busiest first.
     *
     * @return list<SignalRow>
     */
    public function signals(StatisticsPeriod $period): array
    {
        $rows = $this->inPeriod(WithdrawalDailyCount::query()->signalRows(), $period)
            ->selectRaw('signal, level, outcome, SUM(count) as total')
            ->groupBy('signal', 'level', 'outcome')
            ->get();

        $signals = [];
        foreach ($rows as $row) {
            $signal = $row->signal;
            $signals[$signal] ??= ['signal' => $signal, 'genuine' => 0, 'suspected' => 0, 'spam' => 0, 'total' => 0];

            $count = $this->sum($row);
            $signals[$signal][$row->outcome->category($row->level)->value] += $count;
            $signals[$signal]['total'] += $count;
        }

        $signals = array_values(array_filter($signals, fn (array $row): bool => $row['total'] > 0));
        usort($signals, fn (array $a, array $b): int => [$b['total'], $a['signal']] <=> [$a['total'], $b['signal']]);

        return $signals;
    }

    /**
     * The summed count per stored state in the query.
     *
     * @param  Builder<WithdrawalDailyCount>  $query
     * @return list<array{0: SpamLevel, 1: Outcome, 2: int}>
     */
    private function states(Builder $query): array
    {
        $states = [];

        foreach ($query->selectRaw('level, outcome, SUM(count) as total')->groupBy('level', 'outcome')->get() as $row) {
            $states[] = [$row->level, $row->outcome, $this->sum($row)];
        }

        return $states;
    }

    /**
     * @param  Builder<WithdrawalDailyCount>  $query
     * @return Builder<WithdrawalDailyCount>
     */
    private function inPeriod(Builder $query, StatisticsPeriod $period): Builder
    {
        $from = $period->firstDay();
        $until = $period->lastDay();

        return $query
            ->when($from !== null, fn (Builder $q): Builder => $q->where('day', '>=', $from?->toDateString()))
            ->when($until !== null, fn (Builder $q): Builder => $q->where('day', '<=', $until?->toDateString()));
    }

    /**
     * The first day of every bucket in the chart's range; for all time from the first counter day.
     *
     * @return list<Carbon>
     */
    private function bucketStarts(StatisticsPeriod $period, Granularity $granularity): array
    {
        $from = $period->firstDay();
        $until = $period->lastDay();

        if ($from === null) {
            $first = WithdrawalDailyCount::query()->min('day');
            $from = is_string($first) ? Carbon::parse($first)->startOfYear() : now()->startOfYear();
            $until = now()->startOfDay();
        }

        $starts = [];
        for ($date = $from->copy(); $date->lte($until ?? $from); $date = $granularity->next($date)) {
            $starts[] = $date;
        }

        return $starts;
    }

    private function sum(WithdrawalDailyCount $row): int
    {
        $total = $row->getAttribute('total');

        return is_numeric($total) ? (int) $total : 0;
    }
}

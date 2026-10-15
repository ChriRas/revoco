<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Statistics\SpamStatistics;
use App\Statistics\StatisticsPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** The six figure tiles of the statistics page for the selected period. */
final class StatisticsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $period = StatisticsPeriod::fromFilter($this->pageFilters['period'] ?? null);
        $totals = app(SpamStatistics::class)->totals($period);

        return [
            Stat::make(__('panel.statistics.tile.genuine.label'), Number::format($totals['genuine'], locale: app()->getLocale()))
                ->description(__('panel.statistics.tile.genuine.description'))
                ->color('success'),
            Stat::make(__('panel.statistics.tile.suspected.label'), Number::format($totals['suspected'], locale: app()->getLocale()))
                ->description(__('panel.statistics.tile.suspected.description'))
                ->color('warning'),
            Stat::make(__('panel.statistics.tile.spam.label'), Number::format($totals['spam'], locale: app()->getLocale()))
                ->description(__('panel.statistics.tile.spam.description'))
                ->color('danger'),
            Stat::make(__('panel.statistics.tile.auto_sorted.label'), Number::format($totals['auto_sorted'], locale: app()->getLocale()))
                ->description(__('panel.statistics.tile.auto_sorted.description')),
            Stat::make(__('panel.statistics.tile.false_alarms.label'), Number::format($totals['false_alarms'], locale: app()->getLocale()))
                ->description(__('panel.statistics.tile.false_alarms.description')),
            Stat::make(__('panel.statistics.tile.missed.label'), Number::format($totals['missed'], locale: app()->getLocale()))
                ->description(__('panel.statistics.tile.missed.description')),
        ];
    }
}

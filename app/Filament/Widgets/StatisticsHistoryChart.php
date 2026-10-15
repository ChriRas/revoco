<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Statistics\SpamStatistics;
use App\Statistics\StatisticsPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Stacked bars of genuine / suspected / spam over the selected period: one bar per day
 * (today and month), per month (year) or per year (all time). Auto-sorted, false alarms
 * and missed are tiles only, since auto-sorted is a part of spam.
 */
final class StatisticsHistoryChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): string
    {
        return __('panel.statistics.chart.heading');
    }

    public function getEmptyStateHeading(): string
    {
        return __('panel.statistics.chart.empty');
    }

    public function isEmpty(): bool
    {
        foreach ($this->bars() as $bar) {
            if ($bar['genuine'] + $bar['suspected'] + $bar['spam'] > 0) {
                return false;
            }
        }

        return true;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $bars = $this->bars();

        $series = [
            'genuine' => '#22c55e',
            'suspected' => '#f59e0b',
            'spam' => '#ef4444',
        ];

        $datasets = [];
        foreach ($series as $category => $color) {
            $datasets[] = [
                'label' => __('panel.statistics.chart.series.'.$category),
                'data' => array_column($bars, $category),
                'backgroundColor' => $color,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_column($bars, 'label'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, genuine: int, suspected: int, spam: int}>
     */
    private function bars(): array
    {
        return app(SpamStatistics::class)->history(StatisticsPeriod::fromFilter($this->pageFilters['period'] ?? null));
    }
}

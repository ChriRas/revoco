<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\WithdrawalResource;
use App\Spam\SpamReasonLabels;
use App\Statistics\SpamStatistics;
use App\Statistics\StatisticsPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * Per signal that fired in the period: how many declarations of each category carried it. A
 * count opens the withdrawals list on the matching tab, filtered by the signal and the period.
 */
final class SignalBreakdownTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('panel.statistics.signals.heading'))
            ->records(fn (): array => $this->records())
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label(__('panel.statistics.signals.column.signal')),
                $this->countColumn('genuine', 'success'),
                $this->countColumn('suspected', 'warning'),
                $this->countColumn('spam', 'danger'),
                TextColumn::make('total')
                    ->label(__('panel.statistics.signals.column.total'))
                    ->numeric()
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->emptyStateHeading(__('panel.statistics.signals.empty'))
            ->contentFooter(view('panels.statistics-signals-note'));
    }

    /**
     * One row per signal, keyed by its key; the label goes through the one rendering point.
     *
     * @return array<string, array<string, mixed>>
     */
    private function records(): array
    {
        $records = [];

        foreach (app(SpamStatistics::class)->signals($this->period()) as $row) {
            $records[$row['signal']] = [...$row, 'label' => SpamReasonLabels::render($row['signal']) ?? $row['signal']];
        }

        return $records;
    }

    private function countColumn(string $category, string $color): TextColumn
    {
        return TextColumn::make($category)
            ->label(__('panel.statistics.signals.column.'.$category))
            ->numeric()
            ->alignEnd()
            ->color(fn (mixed $state): string => is_int($state) && $state > 0 ? $color : 'gray')
            ->url(fn (array $record): ?string => ($record[$category] ?? 0) > 0 && is_string($record['signal'] ?? null) ? $this->listUrl($category, $record['signal']) : null);
    }

    /** The withdrawals list: Inbox for genuine and suspected, Spam for spam; signal and period filtered. */
    private function listUrl(string $category, string $signal): string
    {
        $period = $this->period();
        $filters = ['signal' => ['values' => [$signal]]];

        if (($from = $period->firstDay()) !== null && ($until = $period->lastDay()) !== null) {
            $filters['date_range'] = ['from' => $from->toDateString(), 'until' => $until->toDateString()];
        }

        return WithdrawalResource::getUrl('index', [
            'tab' => $category === 'spam' ? 'spam' : 'inbox',
            'filters' => $filters,
        ]);
    }

    private function period(): StatisticsPeriod
    {
        return StatisticsPeriod::fromFilter($this->pageFilters['period'] ?? null);
    }
}

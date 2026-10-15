<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\SignalBreakdownTable;
use App\Filament\Widgets\StatisticsHistoryChart;
use App\Filament\Widgets\StatisticsOverview;
use App\Statistics\StatisticsPeriod;
use BackedEnum;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

/**
 * Spam statistics read from the anonymous daily counters: figure tiles, a history chart and a
 * per-signal table for a calendar period. The period lives in the URL (`?filters[period]=year`).
 */
final class Statistics extends Page
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('panel.statistics.navigation_label');
    }

    /** The period lives in the URL only, so a bare visit always opens the default period. */
    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __('panel.statistics.title');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('period')
                ->hiddenLabel()
                ->options(collect(StatisticsPeriod::cases())->mapWithKeys(
                    fn (StatisticsPeriod $period): array => [$period->value => $period->label()],
                )->all())
                ->default(StatisticsPeriod::DEFAULT->value)
                ->inline()
                ->grouped()
                ->live(),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFiltersFormContentComponent(),
            Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents([
                StatisticsOverview::class,
                StatisticsHistoryChart::class,
                SignalBreakdownTable::class,
            ])),
        ]);
    }

    public function getFiltersFormContentComponent(): Component
    {
        return EmbeddedSchema::make('filtersForm');
    }
}

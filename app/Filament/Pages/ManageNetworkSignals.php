<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\RefreshNetworkData;
use App\Settings\NetworkSignalSettings;
use App\Support\CountryNames;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Operator settings page for the network spam signals: the shipping countries, the status
 * of the downloaded data and a "Refresh now" action (queued). Persists to
 * App\Settings\NetworkSignalSettings. The signals only classify; nothing here blocks a
 * submit (§ 356a).
 */
final class ManageNetworkSignals extends SettingsPage
{
    protected static string $settings = NetworkSignalSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    public static function getNavigationLabel(): string
    {
        return __('panel.settings.network.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.settings.navigation_group');
    }

    public function getTitle(): string
    {
        return __('panel.settings.network.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('panel.settings.network.description'))
                ->components([
                    Select::make('shipping_countries')
                        ->label(__('panel.settings.network.shipping_countries.label'))
                        ->helperText(__('panel.settings.network.shipping_countries.help'))
                        ->options(fn (): array => CountryNames::options(app()->getLocale()))
                        ->multiple()
                        ->searchable(),
                ]),
            Section::make(__('panel.settings.network.status.heading'))
                ->components([
                    View::make('panels.network-status'),
                    View::make('panels.db-ip-attribution'),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshNow')
                ->label(__('panel.settings.network.refresh.button'))
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    RefreshNetworkData::dispatch();

                    Notification::make()
                        ->title(__('panel.settings.network.refresh.queued'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

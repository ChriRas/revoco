<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\WithdrawalScopeSettings;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Operator settings page for the withdrawal scope: which contract categories the
 * merchant offers (goods / services / digital content). Persists to
 * App\Settings\WithdrawalScopeSettings, which App\Support\WithdrawalScope reads to
 * tailor the consumer form's copy.
 *
 * DISPLAY ONLY (§ 356a): the toggles shape wording only — the section description
 * spells this out so an operator never expects them to restrict what can be
 * submitted. The free-text `subject` fallback always remains.
 */
final class ManageWithdrawalScope extends SettingsPage
{
    protected static string $settings = WithdrawalScopeSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public static function getNavigationLabel(): string
    {
        return __('panel.settings.scope.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.settings.navigation_group');
    }

    public function getTitle(): string
    {
        return __('panel.settings.scope.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('panel.settings.scope.description'))
                ->components([
                    Toggle::make('offers_goods')
                        ->label(__('panel.settings.scope.goods.label'))
                        ->helperText(__('panel.settings.scope.goods.help')),
                    Toggle::make('offers_services')
                        ->label(__('panel.settings.scope.services.label'))
                        ->helperText(__('panel.settings.scope.services.help')),
                    Toggle::make('offers_digital')
                        ->label(__('panel.settings.scope.digital.label'))
                        ->helperText(__('panel.settings.scope.digital.help')),
                ]),
        ]);
    }
}

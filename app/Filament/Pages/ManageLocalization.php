<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\LocaleSettings;
use App\Support\ConsumerLocales;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Operator settings page for the consumer form's languages. The offered set is
 * chosen from the SHIPPED locales (translation dirs under lang/); the default
 * must be one of the offered locales. Persists to App\Settings\LocaleSettings,
 * which App\Support\ConsumerLocales reads.
 */
final class ManageLocalization extends SettingsPage
{
    protected static string $settings = LocaleSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';

    public static function getNavigationLabel(): string
    {
        return __('panel.settings.localization.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.settings.navigation_group');
    }

    public function getTitle(): string
    {
        return __('panel.settings.localization.title');
    }

    public function form(Schema $schema): Schema
    {
        $options = $this->localeOptions();

        return $schema->components([
            CheckboxList::make('available')
                ->label(__('panel.settings.localization.available.label'))
                ->helperText(__('panel.settings.localization.available.help'))
                ->options($options)
                ->bulkToggleable()
                ->required()
                ->minItems(1)
                ->live(),
            Select::make('default')
                ->label(__('panel.settings.localization.default.label'))
                ->helperText(__('panel.settings.localization.default.help'))
                // Offer only the locales the operator has currently enabled (the
                // available CheckboxList is ->live()), so the default can't name a
                // disabled language; the ->in() rule still enforces this on save.
                ->options(function (Get $get) use ($options): array {
                    $available = $get('available');

                    return is_array($available)
                        ? array_filter(
                            $options,
                            static fn (string $code): bool => in_array($code, $available, true),
                            ARRAY_FILTER_USE_KEY,
                        )
                        : [];
                })
                ->required()
                ->in(fn (Get $get): array => is_array($available = $get('available')) ? array_values($available) : [])
                ->validationMessages([
                    'in' => __('panel.settings.localization.default.not_available'),
                ]),
        ]);
    }

    /**
     * Shipped locales as CheckboxList/Select options, labelled with each
     * language's autonym (falling back to the upper-cased code).
     *
     * @return array<string, string>
     */
    private function localeOptions(): array
    {
        $options = [];

        foreach (ConsumerLocales::shipped() as $code) {
            $key = 'wf.language.names.'.$code;
            $name = __($key);
            $options[$code] = $name === $key ? strtoupper($code) : $name;
        }

        return $options;
    }
}

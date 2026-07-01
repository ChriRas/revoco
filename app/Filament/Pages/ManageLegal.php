<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\LegalSettings;
use App\Support\ConsumerLocales;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Operator settings page for the legal pages. This slice covers the privacy policy;
 * the imprint reuses the same page shape in its own slice.
 *
 * One rich editor per ENABLED consumer locale (App\Support\ConsumerLocales) writes
 * App\Settings\LegalSettings::$privacy_content (keyed by locale). An optional
 * override URL and the fallback-language chain complete the group. Revoco authors
 * no legal text — this is the mechanism only; the operator supplies the content.
 * Persists to LegalSettings, which App\Support\LegalPages reads for the consumer page.
 */
final class ManageLegal extends SettingsPage
{
    protected static string $settings = LegalSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    public static function getNavigationLabel(): string
    {
        return __('panel.settings.legal.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.settings.navigation_group');
    }

    public function getTitle(): string
    {
        return __('panel.settings.legal.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panel.settings.legal.privacy_content.label'))
                ->description(__('panel.settings.legal.privacy_content.help'))
                ->schema($this->privacyEditors()),
            TextInput::make('privacy_link')
                ->label(__('panel.settings.legal.privacy_link.label'))
                ->helperText(__('panel.settings.legal.privacy_link.help'))
                ->url()
                ->maxLength(2048),
            Select::make('fallback_order')
                ->label(__('panel.settings.legal.fallback_order.label'))
                ->helperText(__('panel.settings.legal.fallback_order.help'))
                ->options($this->localeOptions())
                ->multiple()
                ->reorderable()
                // Filament's styled dropdown (matches the rest of the panel) rather
                // than the native OS <select>; the locale list is short.
                ->native(false),
        ]);
    }

    /**
     * A rich editor per enabled consumer locale, each bound to the matching
     * privacy_content.<locale> key and labelled with the language autonym. The
     * toolbar excludes file attachments — legal text needs no image uploads, and
     * dropping them keeps rendering free of the private-attachment disk plumbing.
     *
     * @return list<RichEditor>
     */
    private function privacyEditors(): array
    {
        $editors = [];

        foreach ($this->localeOptions() as $code => $label) {
            $editors[] = RichEditor::make('privacy_content.'.$code)
                ->label($label)
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3'],
                    ['blockquote', 'bulletList', 'orderedList'],
                    ['undo', 'redo'],
                ]);
        }

        return $editors;
    }

    /**
     * Enabled consumer locales as Select/editor options, labelled with each
     * language's autonym (falling back to the upper-cased code).
     *
     * @return array<string, string>
     */
    private function localeOptions(): array
    {
        $options = [];

        foreach (ConsumerLocales::available() as $code) {
            $key = 'wf.language.names.'.$code;
            $name = __($key);
            $options[$code] = $name === $key ? strtoupper($code) : $name;
        }

        return $options;
    }
}

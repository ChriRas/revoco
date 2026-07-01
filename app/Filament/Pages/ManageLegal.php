<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\LegalSettings;
use App\Support\ConsumerLocales;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/**
 * Operator settings page for the legal pages. Organized in two tabs:
 * "Privacy Policy" (slice-013) and "Imprint" (slice-014, § 5 DDG).
 *
 * One rich editor per ENABLED consumer locale (App\Support\ConsumerLocales) writes
 * App\Settings\LegalSettings::$privacy_content (keyed by locale). Structured
 * imprint fields (locale-independent operator data) plus a per-language addendum
 * complete the imprint tab. Revoco authors no legal text — this is the mechanism
 * only; the operator supplies the content.
 */
final class ManageLegal extends SettingsPage
{
    protected static string $settings = LegalSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    /** Widen the form to the full available width (BasePage API — Width enum, vendor-verified). */
    protected Width|string|null $maxContentWidth = Width::Full;

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
            Tabs::make()
                ->tabs([
                    Tab::make(__('panel.settings.legal.tab_privacy'))
                        ->schema($this->privacyTabSchema()),
                    Tab::make(__('panel.settings.legal.tab_imprint'))
                        ->schema($this->imprintTabSchema()),
                ]),
        ]);
    }

    /**
     * Preserve content for locales not currently enabled. The form renders editors
     * only per enabled locale, so on save Filament prunes the keyed content to enabled
     * keys — which would wipe a disabled locale's stored legal text. Disabling a locale
     * can be temporary, so we never drop authored legal text: submitted (enabled)
     * entries win; stored entries for absent locales are kept.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $settings = app(LegalSettings::class);

        // Preserve privacy content for disabled locales.
        $submittedPrivacy = $data['privacy_content'] ?? [];
        $data['privacy_content'] = (is_array($submittedPrivacy) ? $submittedPrivacy : [])
            + $settings->privacy_content;

        // Preserve imprint address for disabled locales.
        $submittedAddress = $data['imprint_address'] ?? [];
        $data['imprint_address'] = (is_array($submittedAddress) ? $submittedAddress : [])
            + $settings->imprint_address;

        // Preserve imprint addendum for disabled locales.
        $submittedAddendum = $data['imprint_addendum'] ?? [];
        $data['imprint_addendum'] = (is_array($submittedAddendum) ? $submittedAddendum : [])
            + $settings->imprint_addendum;

        return $data;
    }

    /**
     * Privacy tab: one rich editor per enabled locale + override URL + fallback chain.
     *
     * @return array<int, Component>
     */
    private function privacyTabSchema(): array
    {
        return [
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
                // Filament styled dropdown — locale list is short, native not needed.
                ->native(false),
        ];
    }

    /**
     * Imprint tab: external-link override + structured § 5 DDG fields (grouped by
     * theme) + a per-language free-form addendum.
     *
     * @return array<int, Component>
     */
    private function imprintTabSchema(): array
    {
        return [
            TextInput::make('imprint_link')
                ->label(__('panel.settings.legal.imprint_link.label'))
                ->helperText(__('panel.settings.legal.imprint_link.help'))
                ->url()
                ->maxLength(2048),

            Section::make(__('panel.settings.legal.imprint_entity.label'))
                ->description(__('panel.settings.legal.imprint_entity.help'))
                ->columns(2)
                ->schema([
                    TextInput::make('imprint_name')
                        ->label(__('panel.settings.legal.imprint_name.label'))
                        ->maxLength(512)
                        ->columnSpanFull(),
                    TextInput::make('imprint_legal_form')
                        ->label(__('panel.settings.legal.imprint_legal_form.label'))
                        ->maxLength(256),
                    TextInput::make('imprint_represented_by')
                        ->label(__('panel.settings.legal.imprint_represented_by.label'))
                        ->maxLength(512),
                ]),

            Section::make(__('panel.settings.legal.imprint_address.label'))
                ->description(__('panel.settings.legal.imprint_address.help'))
                ->schema($this->imprintAddressEditors()),

            Section::make(__('panel.settings.legal.imprint_contact.label'))
                ->description(__('panel.settings.legal.imprint_contact.help'))
                ->columns(2)
                ->schema([
                    TextInput::make('imprint_email')
                        ->label(__('panel.settings.legal.imprint_email.label'))
                        ->email()
                        ->maxLength(512),
                    TextInput::make('imprint_phone')
                        ->label(__('panel.settings.legal.imprint_phone.label'))
                        ->tel()
                        ->maxLength(256),
                    Textarea::make('imprint_contact_note')
                        ->label(__('panel.settings.legal.imprint_contact_note.label'))
                        ->rows(2)
                        ->maxLength(1024)
                        ->columnSpanFull(),
                ]),

            Section::make(__('panel.settings.legal.imprint_register.label'))
                ->description(__('panel.settings.legal.imprint_register.help'))
                ->columns(2)
                ->schema([
                    TextInput::make('imprint_register_court')
                        ->label(__('panel.settings.legal.imprint_register_court.label'))
                        ->maxLength(512),
                    TextInput::make('imprint_register_number')
                        ->label(__('panel.settings.legal.imprint_register_number.label'))
                        ->maxLength(256),
                ]),

            Section::make(__('panel.settings.legal.imprint_tax.label'))
                ->description(__('panel.settings.legal.imprint_tax.help'))
                ->columns(2)
                ->schema([
                    TextInput::make('imprint_vat_id')
                        ->label(__('panel.settings.legal.imprint_vat_id.label'))
                        ->maxLength(256),
                    TextInput::make('imprint_business_id')
                        ->label(__('panel.settings.legal.imprint_business_id.label'))
                        ->maxLength(256),
                ]),

            Section::make(__('panel.settings.legal.imprint_professional.label'))
                ->description(__('panel.settings.legal.imprint_professional.help'))
                ->schema([
                    Textarea::make('imprint_supervisory_authority')
                        ->label(__('panel.settings.legal.imprint_supervisory_authority.label'))
                        ->rows(2)
                        ->maxLength(1024),
                    TextInput::make('imprint_chamber')
                        ->label(__('panel.settings.legal.imprint_chamber.label'))
                        ->maxLength(512),
                    TextInput::make('imprint_job_title')
                        ->label(__('panel.settings.legal.imprint_job_title.label'))
                        ->maxLength(512),
                    Textarea::make('imprint_professional_rules')
                        ->label(__('panel.settings.legal.imprint_professional_rules.label'))
                        ->rows(2)
                        ->maxLength(1024),
                    Textarea::make('imprint_liquidation_note')
                        ->label(__('panel.settings.legal.imprint_liquidation_note.label'))
                        ->rows(2)
                        ->maxLength(1024),
                ])
                ->collapsed(),

            Section::make(__('panel.settings.legal.imprint_addendum.label'))
                ->description(__('panel.settings.legal.imprint_addendum.help'))
                ->schema($this->imprintAddendumEditors()),
        ];
    }

    /**
     * A rich editor per enabled consumer locale for the privacy-policy content.
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
     * A textarea per enabled consumer locale for the postal address.
     * One entry per language (address differs e.g. in country name: Deutschland / Germany).
     *
     * @return list<Textarea>
     */
    private function imprintAddressEditors(): array
    {
        $editors = [];

        foreach ($this->localeOptions() as $code => $label) {
            $editors[] = Textarea::make('imprint_address.'.$code)
                ->label($label)
                ->rows(3)
                ->maxLength(1024);
        }

        return $editors;
    }

    /**
     * A rich editor per enabled consumer locale for the imprint addendum.
     *
     * @return list<RichEditor>
     */
    private function imprintAddendumEditors(): array
    {
        $editors = [];

        foreach ($this->localeOptions() as $code => $label) {
            $editors[] = RichEditor::make('imprint_addendum.'.$code)
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

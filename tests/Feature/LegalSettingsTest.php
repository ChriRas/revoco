<?php

declare(strict_types=1);

use App\Filament\Pages\ManageLegal;
use App\Models\User;
use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Filament "Legal" settings page — renders a rich editor per enabled locale plus
// the override URL and fallback-order controls, and persists to LegalSettings.
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    // Both locales enabled so a rich editor renders for each (the page reads the
    // enabled set from LocaleSettings via ConsumerLocales::available()).
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'de']);
});

it('renders the legal settings page for the operator', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ManageLegal::class)
        ->assertOk()
        ->assertFormFieldExists('privacy_content.de')
        ->assertFormFieldExists('privacy_content.en')
        ->assertFormFieldExists('privacy_link')
        ->assertFormFieldExists('fallback_order');
});

it('persists the privacy content, override link and fallback order', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ManageLegal::class)
        ->fillForm([
            'privacy_content' => [
                'de' => '<p>Datenschutz DE gespeichert</p>',
                'en' => '<p>Privacy EN saved</p>',
            ],
            'privacy_link' => 'https://shop.example/privacy',
            'fallback_order' => ['de'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(LegalSettings::class)->refresh();
    expect($settings->privacy_content['de'])->toContain('Datenschutz DE gespeichert');
    expect($settings->privacy_content['en'])->toContain('Privacy EN saved');
    expect($settings->privacy_link)->toBe('https://shop.example/privacy');
    expect($settings->fallback_order)->toBe(['de']);
});

it('redirects back to the Legal page after a successful save so the warning banner clears', function (): void {
    // The top-bar completeness banner is a layout render hook, not part of this
    // page's Livewire tree — a redirect-to-self after save is what makes it
    // re-evaluate immediately instead of lingering until a manual reload.
    $this->actingAs(User::factory()->create());

    livewire(ManageLegal::class)
        ->fillForm([
            'privacy_content' => ['de' => '<p>Datenschutz</p>'],
            'privacy_link' => null,
            'fallback_order' => ['de'],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(ManageLegal::getUrl());
});

it('rejects an invalid override URL', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ManageLegal::class)
        ->fillForm(['privacy_link' => 'not a valid url'])
        ->call('save')
        ->assertHasFormErrors(['privacy_link']);
});

it('preserves stored content for a locale that is later disabled', function (): void {
    $this->actingAs(User::factory()->create());

    // Operator authors both locales.
    livewire(ManageLegal::class)
        ->fillForm([
            'privacy_content' => ['de' => '<p>DE Original</p>', 'en' => '<p>EN Original</p>'],
            'privacy_link' => null,
            'fallback_order' => ['de'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // en is disabled → the page now renders only the de editor; a save must NOT wipe
    // the stored en text (disabling a locale can be temporary).
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);

    livewire(ManageLegal::class)
        ->fillForm([
            'privacy_content' => ['de' => '<p>DE Aktualisiert</p>'],
            'privacy_link' => null,
            'fallback_order' => ['de'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(LegalSettings::class)->refresh();
    expect($settings->privacy_content['de'])->toContain('DE Aktualisiert');       // enabled-locale edit applied
    expect($settings->privacy_content['en'] ?? null)->toContain('EN Original');   // disabled-locale text preserved
});

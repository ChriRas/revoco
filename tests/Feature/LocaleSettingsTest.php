<?php

declare(strict_types=1);

use App\Filament\Pages\ManageLocalization;
use App\Models\User;
use App\Settings\LocaleSettings;
use App\Support\ConsumerLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Settings drive the consumer switcher + default. LocaleSettings::fake() swaps
// the DB-backed values for the assertion; the real seed/persistence is exercised
// by the Filament settings-page tests below.
// ---------------------------------------------------------------------------

it('offers both locales and shows the switcher when both are enabled', function () {
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'de']);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('<nav class="wf-langs"', false)
        ->assertSee(route('locale.set', 'en'), false);
});

it('hides the switcher and rejects a disabled locale when only one is enabled', function () {
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertDontSee('<nav class="wf-langs"', false);

    // en is no longer offered → the controller writes no cookie for it.
    $this->get(route('locale.set', 'en'), ['referer' => route('withdrawal.form')])
        ->assertRedirect(route('withdrawal.form'))
        ->assertCookieMissing('locale');
});

it('applies the operator-configured default when no cookie is present, even if it diverges from APP_LOCALE', function () {
    // APP_LOCALE is 'de' in the test env; the operator picks 'en' as the default.
    // A fresh visitor (no cookie) must be served the panel default, not APP_LOCALE.
    expect(config('app.locale'))->toBe('de');
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'en']);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('lang="en"', false);

    expect(ConsumerLocales::default())->toBe('en');
    expect(app()->getLocale())->toBe('en');
});

it('honours a valid locale cookie over the default', function () {
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'de']);

    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('lang="en"', false);

    expect(app()->getLocale())->toBe('en');
});

it('leaves switcher behaviour unchanged for the seeded defaults (de, en / default de)', function () {
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'de']);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('<nav class="wf-langs"', false)
        ->assertSee('Deutsch')
        ->assertSee('English')
        ->assertSee(route('locale.set', 'en'), false);
});

// ---------------------------------------------------------------------------
// Filament "Localization" settings page — renders, persists, and validates
// against real DB-backed settings (seeded by the settings migration).
// ---------------------------------------------------------------------------

it('renders the localization settings page for the operator', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageLocalization::class)
        ->assertOk()
        ->assertFormFieldExists('available')
        ->assertFormFieldExists('default');
});

it('persists the offered locales and default chosen on the settings page', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageLocalization::class)
        ->fillForm(['available' => ['de', 'en'], 'default' => 'en'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(LocaleSettings::class)->refresh();
    expect($settings->available)->toBe(['de', 'en']);
    expect($settings->default)->toBe('en');
});

it('rejects a default that is not among the offered locales', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageLocalization::class)
        ->fillForm(['available' => ['de'], 'default' => 'en'])
        ->call('save')
        ->assertHasFormErrors(['default']);
});

it('rejects an empty set of offered locales', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageLocalization::class)
        ->fillForm(['available' => [], 'default' => 'de'])
        ->call('save')
        ->assertHasFormErrors(['available']);
});

it('auto-selects the sole remaining language as the default when the current default is unchecked', function () {
    $this->actingAs(User::factory()->create());

    // Start from de,en / default de, then uncheck de — the default (de) is no
    // longer offered, so it should jump to the only language left (en) instead
    // of leaving the operator to re-pick it.
    livewire(ManageLocalization::class)
        ->fillForm(['available' => ['de', 'en'], 'default' => 'de'])
        ->set('data.available', ['en'])
        ->assertFormSet(['default' => 'en']);
});

it('keeps the default when a non-default language is unchecked', function () {
    $this->actingAs(User::factory()->create());

    // Default en, uncheck de (not the default) → the default must stay en.
    livewire(ManageLocalization::class)
        ->fillForm(['available' => ['de', 'en'], 'default' => 'en'])
        ->set('data.available', ['en'])
        ->assertFormSet(['default' => 'en']);
});

it('clears the default (rather than guessing) when its language is removed but several remain', function () {
    $this->actingAs(User::factory()->create());

    // 'fr' is a synthetic third entry only to exercise the "several remain"
    // branch (the app ships de,en). Remove the default (de) leaving two → the
    // default is cleared to null so the operator picks, not auto-guessed.
    livewire(ManageLocalization::class)
        ->fillForm(['available' => ['de', 'en'], 'default' => 'de'])
        ->set('data.available', ['en', 'fr'])
        ->assertFormSet(['default' => null]);
});

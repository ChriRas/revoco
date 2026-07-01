<?php

declare(strict_types=1);

use App\Filament\Pages\ManageWithdrawalScope;
use App\Models\User;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Consumer form copy reflects the operator-declared scope. WithdrawalScopeSettings
// is faked per case (all three properties supplied, so no settings table is hit);
// LocaleSettings is the migration-seeded de default unless a case overrides it.
// ---------------------------------------------------------------------------

it('names goods only in the intro and subject label when only goods is enabled', function () {
    WithdrawalScopeSettings::fake(['offers_goods' => true, 'offers_services' => false, 'offers_digital' => false]);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('Hier können Sie Verträge über Waren widerrufen.')
        ->assertSee('Betreffende Waren')
        // The other categories are not named and the generic three-way subject
        // label is replaced by the scoped one.
        ->assertDontSee('Dienstleistung')
        ->assertDontSee('digitale Inhalte')
        ->assertDontSee('wf.scope');
});

it('names services and digital content joined when both are enabled', function () {
    WithdrawalScopeSettings::fake(['offers_goods' => false, 'offers_services' => true, 'offers_digital' => true]);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('Hier können Sie Verträge über Dienstleistungen und digitale Inhalte widerrufen.')
        ->assertSee('Betreffende Dienstleistungen und digitale Inhalte')
        // Goods is not enabled, so it is never named.
        ->assertDontSee('Waren');
});

it('falls back to the generic intro and generic subject label when no category is enabled', function () {
    WithdrawalScopeSettings::fake(['offers_goods' => false, 'offers_services' => false, 'offers_digital' => false]);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        // Regression: matches today's generic copy.
        ->assertSee('Hier können Sie Ihren Vertrag widerrufen.')
        ->assertSee('Betreffende Ware, digitale Inhalte oder Dienstleistung');
});

it('renders the scope copy in the consumer locale (English)', function () {
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'de']);
    WithdrawalScopeSettings::fake(['offers_goods' => true, 'offers_services' => false, 'offers_digital' => false]);

    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('Here you can withdraw from contracts for goods.')
        ->assertSee('Affected goods')
        // The generic three-way English label tail is gone (scoped label replaced it).
        ->assertDontSee(', or service');
});

// ---------------------------------------------------------------------------
// § 356a GUARDRAIL — the scope config is display only. It must never gate the
// submit, add a required field, or remove the free-text `subject` fallback.
// ---------------------------------------------------------------------------

it('accepts a free-text submit and redirects regardless of the scope config (§ 356a)', function () {
    // Only goods is declared, yet the consumer withdraws something that matches
    // NONE of the enabled categories — the submit must still succeed.
    WithdrawalScopeSettings::fake(['offers_goods' => true, 'offers_services' => false, 'offers_digital' => false]);

    $this->post(route('withdrawal.store'), [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => '',
        'subject' => 'Ein Sonderfall, der in keine Kategorie passt',
        'website' => '',
    ])->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'subject' => 'Ein Sonderfall, der in keine Kategorie passt',
    ]);
});

it('introduces no new required field — the three mandated fields still suffice with every category enabled', function () {
    WithdrawalScopeSettings::fake(['offers_goods' => true, 'offers_services' => true, 'offers_digital' => true]);

    // Exactly the three § 356a Abs. 2 fields — no orderNumber, no scope field.
    $this->post(route('withdrawal.store'), [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'subject' => 'Betrifft eine Bestellung',
    ])->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseCount('withdrawals', 1);
});

// ---------------------------------------------------------------------------
// Filament "Withdrawal scope" settings page — renders, persists, localized.
// ---------------------------------------------------------------------------

it('renders the withdrawal-scope settings page for the operator', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageWithdrawalScope::class)
        ->assertOk()
        ->assertFormFieldExists('offers_goods')
        ->assertFormFieldExists('offers_services')
        ->assertFormFieldExists('offers_digital');
});

it('persists the scope toggles chosen on the settings page', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageWithdrawalScope::class)
        ->fillForm(['offers_goods' => true, 'offers_services' => false, 'offers_digital' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(WithdrawalScopeSettings::class)->refresh();
    expect($settings->offers_goods)->toBeTrue();
    expect($settings->offers_services)->toBeFalse();
    expect($settings->offers_digital)->toBeTrue();
});

it('localizes the withdrawal-scope page labels (German), leaking no raw keys', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageWithdrawalScope::class)
        ->assertOk()
        ->assertSee('Widerrufsumfang')
        ->assertSee('Waren')
        ->assertSee('Dienstleistungen')
        ->assertSee('Digitale Inhalte')
        ->assertDontSee('panel.settings.scope');
});

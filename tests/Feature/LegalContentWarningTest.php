<?php

declare(strict_types=1);

use App\Filament\Pages\ManageLegal;
use App\Models\User;
use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;
use App\Support\LegalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Shared beforeEach — runs before every test in this file.
// Consumer-form tests need WithdrawalScopeSettings + LocaleSettings.
// Panel tests only need those to not throw; neither the panel render hook
// nor LegalContent reads them (WithdrawalScopeSettings never read by panel).
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);
    WithdrawalScopeSettings::fake(['offers_goods' => false, 'offers_services' => false, 'offers_digital' => false]);
});

// ---------------------------------------------------------------------------
// Helper arrays / functions (unique names — no collision with ImprintTest.legalFake
// or WithdrawalSubmitTest.withdrawalPayload).
//
// Functions are defined at the bottom of the file so they do not clutter the
// test definitions; they are available when closures execute (file is fully
// parsed before any test runs).
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// LegalContent helper — unit combinations (no HTTP layer required)
// ---------------------------------------------------------------------------

it('imprintComplete returns false when all imprint fields are empty', function (): void {
    fakeLegalSettingsIncomplete();

    expect(LegalContent::imprintComplete())->toBeFalse();
});

it('imprintComplete returns true when mandatory § 5 DDG fields are configured', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
    ]));

    expect(LegalContent::imprintComplete())->toBeTrue();
});

it('imprintComplete returns true when only the imprint override link is set', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'imprint_link' => 'https://shop.example/impressum',
    ]));

    // No structured fields — link alone satisfies completeness.
    expect(LegalContent::imprintComplete())->toBeTrue();
});

it('imprintComplete treats a whitespace-only override link as not configured', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'imprint_link' => '   ',
    ]));

    // Spaces must not count as "configured" (§ 5 DDG core cannot be satisfied by blanks).
    expect(LegalContent::imprintComplete())->toBeFalse();
});

it('privacyComplete returns false when no content and no link', function (): void {
    fakeLegalSettingsIncomplete();

    expect(LegalContent::privacyComplete())->toBeFalse();
});

it('privacyComplete returns true when privacy content is present for at least one locale', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'privacy_content' => ['de' => '<p>Datenschutz vorhanden</p>'],
    ]));

    expect(LegalContent::privacyComplete())->toBeTrue();
});

it('privacyComplete returns true when only the privacy override link is set', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'privacy_link' => 'https://shop.example/privacy',
    ]));

    // No content — link alone satisfies completeness.
    expect(LegalContent::privacyComplete())->toBeTrue();
});

it('privacyComplete treats whitespace-only content and link as not configured', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'privacy_content' => ['de' => '   '],
        'privacy_link' => '   ',
    ]));

    expect(LegalContent::privacyComplete())->toBeFalse();
});

it('isComplete returns true only when both imprint and privacy are complete', function (): void {
    fakeLegalSettingsComplete();

    expect(LegalContent::isComplete())->toBeTrue();
});

it('isComplete returns false when imprint is complete but privacy content is missing', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
        // privacy_content stays [] and privacy_link stays null
    ]));

    expect(LegalContent::isComplete())->toBeFalse();
});

it('isComplete returns false when privacy is complete but imprint fields are missing', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'privacy_content' => ['de' => '<p>Datenschutz</p>'],
        // imprint fields stay null/empty
    ]));

    expect(LegalContent::isComplete())->toBeFalse();
});

it('missing returns both page identifiers when nothing is configured', function (): void {
    fakeLegalSettingsIncomplete();

    expect(LegalContent::missing())->toBe(['imprint', 'privacy']);
});

it('missing returns only privacy when imprint is configured but privacy is not', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
    ]));

    expect(LegalContent::missing())->toBe(['privacy']);
});

it('missing returns only imprint when privacy is configured but imprint is not', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'privacy_content' => ['de' => '<p>Datenschutz</p>'],
    ]));

    expect(LegalContent::missing())->toBe(['imprint']);
});

it('composes the link override across pages — imprint via link, privacy still missing', function (): void {
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'imprint_link' => 'https://shop.example/impressum',
        // privacy has neither content nor link
    ]));

    expect(LegalContent::missing())->toBe(['privacy']);
    expect(LegalContent::isComplete())->toBeFalse();
});

it('missing returns empty array when both pages are complete', function (): void {
    fakeLegalSettingsComplete();

    expect(LegalContent::missing())->toBe([]);
});

it('treats an unseeded settings store as incomplete (MissingSettings → false)', function (): void {
    // No LegalSettings::fake() and DB is empty (RefreshDatabase) →
    // app(LegalSettings::class) throws MissingSettings → caught → incomplete.
    expect(LegalContent::isComplete())->toBeFalse();
    expect(LegalContent::missing())->toBe(['imprint', 'privacy']);
});

// ---------------------------------------------------------------------------
// Public consumer form — setup notice shown/hidden
// ---------------------------------------------------------------------------

it('shows the setup notice on the form when legal content is incomplete', function (): void {
    fakeLegalSettingsIncomplete();

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee(__('wf.setup.pending'));
});

it('does not show the setup notice on the form when legal content is complete', function (): void {
    fakeLegalSettingsComplete();

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertDontSee(__('wf.setup.pending'));
});

it('never 500s on GET / and still shows the notice when LegalSettings is unseeded', function (): void {
    // Force MissingSettings on every access — consumer form must stay up (§ 356a).
    app()->bind(LegalSettings::class, fn () => throw new MissingSettings('unseeded'));

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee(__('wf.setup.pending'));
});

// ---------------------------------------------------------------------------
// § 356a — POST / succeeds even while the notice is visible (non-blocking)
// ---------------------------------------------------------------------------

it('POST / succeeds and redirects to success even when legal content is incomplete', function (): void {
    fakeLegalSettingsIncomplete();

    $this->post(route('withdrawal.store'), [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'B-99999',
        'subject' => 'Kaffeemaschine Modell Y',
        'website' => '',
    ])->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
    ]);
});

// ---------------------------------------------------------------------------
// Filament panel — red banner shown/hidden for authenticated operator
// ---------------------------------------------------------------------------

it('shows the setup banner in the panel when legal content is incomplete', function (): void {
    $operator = User::factory()->create();
    // No LegalSettings seed → DB empty (RefreshDatabase) → MissingSettings → banner shown.

    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee(__('panel.setup.link'));
});

it('the panel banner links to the ManageLegal settings page', function (): void {
    $operator = User::factory()->create();

    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee(ManageLegal::getUrl(), false);
});

it('does not show the setup banner in the panel when legal content is complete', function (): void {
    $operator = User::factory()->create();
    fakeLegalSettingsComplete();

    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertDontSee(__('panel.setup.link'));
});

it('names the missing pages in the panel banner', function (): void {
    $operator = User::factory()->create();
    fakeLegalSettingsIncomplete();

    // Backend locale defaults to 'en' (config/operator.php → BACKEND_LOCALE=en).
    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee(__('panel.setup.page_imprint'))
        ->assertSee(__('panel.setup.page_privacy'));
});

// ---------------------------------------------------------------------------
// Helper functions — unique names to avoid collision with legalFake()
// (ImprintTest) and withdrawalPayload() (WithdrawalSubmitTest).
// ---------------------------------------------------------------------------

/** Returns the base incomplete LegalSettings array for use with array_merge. */
function legalSettingsIncompleteArray(): array
{
    return [
        'privacy_content' => [],
        'privacy_link' => null,
        'fallback_order' => ['de'],
        'imprint_name' => null,
        'imprint_legal_form' => null,
        'imprint_represented_by' => null,
        'imprint_address' => [],
        'imprint_email' => null,
        'imprint_phone' => null,
        'imprint_contact_note' => null,
        'imprint_register_court' => null,
        'imprint_register_number' => null,
        'imprint_vat_id' => null,
        'imprint_business_id' => null,
        'imprint_supervisory_authority' => null,
        'imprint_chamber' => null,
        'imprint_job_title' => null,
        'imprint_professional_rules' => null,
        'imprint_liquidation_note' => null,
        'imprint_addendum' => [],
        'imprint_link' => null,
    ];
}

/** Fakes LegalSettings in an empty/incomplete state (nothing configured). */
function fakeLegalSettingsIncomplete(): void
{
    LegalSettings::fake(legalSettingsIncompleteArray());
}

/** Fakes LegalSettings in a fully configured/complete state. */
function fakeLegalSettingsComplete(): void
{
    LegalSettings::fake(array_merge(legalSettingsIncompleteArray(), [
        'privacy_content' => ['de' => '<p>Datenschutztext vorhanden</p>'],
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
    ]));
}

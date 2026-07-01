<?php

declare(strict_types=1);

use App\Filament\Pages\ManageLegal;
use App\Models\User;
use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;
use App\Support\LegalPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// /impressum (legal.imprint) — structured § 5 DDG fields, per-locale addendum,
// external-link override (302), and the neutral empty-state placeholder.
// The footer "Impressum" link is re-wired to LegalPages::imprintUrl().
// ---------------------------------------------------------------------------

/** Minimal full fake including imprint fields to avoid DB load for privacy tests. */
function legalFake(array $override = []): void
{
    LegalSettings::fake(array_merge([
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
    ], $override));
}

beforeEach(function (): void {
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'de']);
    WithdrawalScopeSettings::fake(['offers_goods' => false, 'offers_services' => false, 'offers_digital' => false]);
});

it('renders structured imprint fields in the consumer locale (de)', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
    ]);

    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Impressum')                             // German page title
        ->assertSee('Muster GmbH')
        ->assertSee('Musterstraße 1, 12345 Berlin, Deutschland')
        ->assertSee('kontakt@example.com')
        ->assertSee('Anschrift')                             // German field label for address
        ->assertSee('E-Mail');                               // German field label for email
});

it('renders imprint with English labels when the consumer locale is en', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => [
            'de' => 'Musterstraße 1, 12345 Berlin, Deutschland',
            'en' => 'Musterstrasse 1, 12345 Berlin, Germany',
        ],
        'imprint_email' => 'kontakt@example.com',
    ]);

    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Imprint')                           // English page title
        ->assertSee('Muster GmbH')                       // name is locale-independent
        ->assertSee('Musterstrasse 1, 12345 Berlin, Germany') // EN address resolved
        ->assertSee('Address')                           // English field label
        ->assertSee('Email');                            // English field label
});

it('omits empty optional fields from the rendered imprint', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
        // No phone, no register, no VAT — these must not produce empty rows.
    ]);

    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertDontSee('Telefon')
        ->assertDontSee('Registergericht')
        ->assertDontSee('Umsatzsteuer-Identifikationsnummer');
});

it('shows the de addendum by default and falls back when only de is configured', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
        'imprint_addendum' => ['de' => '<p>Hinweis nur auf Deutsch</p>'],
        'fallback_order' => ['de'],
    ]);

    // De request shows the de addendum.
    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Hinweis nur auf Deutsch');

    // En request falls back to de addendum via the chain.
    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Hinweis nur auf Deutsch');
});

it('302-redirects to the external override URL when imprint_link is set', function (): void {
    legalFake(['imprint_link' => 'https://shop.example/impressum']);

    $this->get(route('legal.imprint'))
        ->assertRedirect('https://shop.example/impressum');
});

it('shows a neutral placeholder when no fields and no override are configured', function (): void {
    legalFake(); // all nulls / empty

    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee(__('wf.legal.placeholder'))
        ->assertDontSee('Lorem')
        ->assertDontSee('wf.legal');
});

it('renders the addendum and suppresses the placeholder when only the addendum is configured', function (): void {
    // No structured fields, but an addendum IS set — the isEmpty gate must not
    // treat this as empty: the addendum renders and the placeholder is suppressed.
    legalFake([
        'imprint_addendum' => ['de' => '<p>Nur ein Zusatz, keine Felder</p>'],
        'fallback_order' => ['de'],
    ]);

    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Nur ein Zusatz, keine Felder')
        ->assertDontSee(__('wf.legal.placeholder'));
});

it('never 500s and shows the placeholder when LegalSettings is unseeded', function (): void {
    // Simulate a missing settings row — consumer pages must stay up (§ 356a).
    app()->bind(LegalSettings::class, fn () => throw new MissingSettings('unseeded'));

    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee(__('wf.legal.placeholder'));
});

// ---------------------------------------------------------------------------
// Footer re-wire: the "Impressum" link now uses LegalPages::imprintUrl()
// ---------------------------------------------------------------------------

it('points the footer imprint link at the internal route when no override is set', function (): void {
    legalFake(); // imprint_link = null

    $internal = 'href="'.route('legal.imprint').'"';

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee($internal, false);

    $this->withoutVite()->get(route('withdrawal.success'))
        ->assertOk()
        ->assertSee($internal, false);
});

it('points the footer imprint link at the external override URL when imprint_link is set', function (): void {
    legalFake(['imprint_link' => 'https://shop.example/impressum']);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('href="https://shop.example/impressum"', false)
        ->assertDontSee('href="'.route('legal.imprint').'"', false);
});

// ---------------------------------------------------------------------------
// Completeness helper — used by the S4 missing-content-warning slice
// ---------------------------------------------------------------------------

it('reports the imprint as configured when name, address and email are all non-empty', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
    ]);

    expect(LegalPages::imprintIsConfigured())->toBeTrue();
});

it('reports the imprint as not configured when mandatory fields are absent', function (): void {
    legalFake(['imprint_name' => 'Muster GmbH']); // address and email missing

    expect(LegalPages::imprintIsConfigured())->toBeFalse();
});

it('reports not configured when LegalSettings is unseeded', function (): void {
    app()->bind(LegalSettings::class, fn () => throw new MissingSettings('unseeded'));

    expect(LegalPages::imprintIsConfigured())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Filament "Impressum" tab — renders and persists
// ---------------------------------------------------------------------------

it('renders the Impressum tab with its form fields', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ManageLegal::class)
        ->assertOk()
        ->assertFormFieldExists('imprint_name')
        ->assertFormFieldExists('imprint_address.de') // per-locale textarea (de enabled by default)
        ->assertFormFieldExists('imprint_email')
        ->assertFormFieldExists('imprint_link');
});

it('persists imprint fields and the override link', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ManageLegal::class)
        ->fillForm([
            'imprint_name' => 'Muster GmbH',
            'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
            'imprint_email' => 'kontakt@example.com',
            'imprint_phone' => '+49 30 123456',
            'imprint_vat_id' => 'DE123456789',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(LegalSettings::class)->refresh();
    expect($settings->imprint_name)->toBe('Muster GmbH');
    expect($settings->imprint_address['de'])->toBe('Musterstraße 1, 12345 Berlin, Deutschland');
    expect($settings->imprint_email)->toBe('kontakt@example.com');
    expect($settings->imprint_vat_id)->toBe('DE123456789');
});

it('persists the imprint addendum per locale and preserves disabled-locale content', function (): void {
    $this->actingAs(User::factory()->create());

    // Author both locales.
    livewire(ManageLegal::class)
        ->fillForm([
            'imprint_name' => 'Muster GmbH',
            'imprint_address' => [
                'de' => 'Musterstraße 1, 12345 Berlin, Deutschland',
                'en' => 'Musterstrasse 1, 12345 Berlin, Germany',
            ],
            'imprint_email' => 'kontakt@example.com',
            'imprint_addendum' => [
                'de' => '<p>Zusatz DE</p>',
                'en' => '<p>Addendum EN</p>',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Disable the en locale — the next save must NOT wipe the stored en address / addendum.
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);

    livewire(ManageLegal::class)
        ->fillForm([
            'imprint_name' => 'Muster GmbH',
            'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
            'imprint_email' => 'kontakt@example.com',
            'imprint_addendum' => ['de' => '<p>Zusatz DE aktualisiert</p>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(LegalSettings::class)->refresh();
    expect($settings->imprint_address['de'])->toBe('Musterstraße 1, 12345 Berlin, Deutschland');
    expect($settings->imprint_address['en'] ?? null)->toBe('Musterstrasse 1, 12345 Berlin, Germany');
    expect($settings->imprint_addendum['de'])->toContain('Zusatz DE aktualisiert');
    expect($settings->imprint_addendum['en'] ?? null)->toContain('Addendum EN');
});

it('rejects an invalid override imprint URL', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ManageLegal::class)
        ->fillForm(['imprint_link' => 'not-a-valid-url'])
        ->call('save')
        ->assertHasFormErrors(['imprint_link']);
});

// ---------------------------------------------------------------------------
// Per-locale address resolution — proving locale-specific content, not just labels
// ---------------------------------------------------------------------------

it('renders the German address for a de request and the English address for an en request', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => [
            'de' => 'Musterstraße 1, 12345 Berlin, Deutschland',
            'en' => 'Musterstrasse 1, 12345 Berlin, Germany',
        ],
        'imprint_email' => 'kontakt@example.com',
        'fallback_order' => ['de'],
    ]);

    // DE request — ß and "Deutschland" must appear.
    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Musterstraße 1, 12345 Berlin, Deutschland')
        ->assertDontSee('Musterstrasse 1, 12345 Berlin, Germany');

    // EN request — ß→ss and "Germany" must appear instead.
    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Musterstrasse 1, 12345 Berlin, Germany')
        ->assertDontSee('Musterstraße 1, 12345 Berlin, Deutschland');
});

it('falls back to the de address when an en request finds no en address', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
        'fallback_order' => ['de'],
    ]);

    // EN request — no en address is set, must fall back to de.
    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('Musterstraße 1, 12345 Berlin, Deutschland');
});

// ---------------------------------------------------------------------------
// Completeness helper — per-locale address rules
// ---------------------------------------------------------------------------

it('reports not configured when the address map is empty for the default locale', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => [], // default locale (de) has no entry
        'imprint_email' => 'kontakt@example.com',
    ]);

    expect(LegalPages::imprintIsConfigured())->toBeFalse();
});

it('reports not configured when only a non-default locale has an address', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['en' => 'Musterstrasse 1, 12345 Berlin, Germany'], // de missing
        'imprint_email' => 'kontakt@example.com',
    ]);

    // Default locale is 'de' (from beforeEach LocaleSettings::fake); 'de' key absent.
    expect(LegalPages::imprintIsConfigured())->toBeFalse();
});

it('reports configured when the default locale has a non-empty address', function (): void {
    legalFake([
        'imprint_name' => 'Muster GmbH',
        'imprint_address' => ['de' => 'Musterstraße 1, 12345 Berlin, Deutschland'],
        'imprint_email' => 'kontakt@example.com',
    ]);

    expect(LegalPages::imprintIsConfigured())->toBeTrue();
});

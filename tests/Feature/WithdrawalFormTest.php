<?php

declare(strict_types=1);

use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;

// The form reads LocaleSettings (offered locales), LegalSettings (footer privacy
// link) and WithdrawalScopeSettings (scope copy); fake all three so these DB-less
// tests need no settings table. Scope all off → the generic copy these assertions
// rely on.
beforeEach(function () {
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);
    LegalSettings::fake([
        'privacy_content' => [], 'privacy_link' => null, 'fallback_order' => ['de'],
        // Imprint fields (slice-014) — empty defaults so this DB-less test
        // requires no settings table.
        'imprint_name' => null, 'imprint_legal_form' => null, 'imprint_represented_by' => null,
        'imprint_address' => null, 'imprint_email' => null, 'imprint_phone' => null,
        'imprint_contact_note' => null, 'imprint_register_court' => null,
        'imprint_register_number' => null, 'imprint_vat_id' => null, 'imprint_business_id' => null,
        'imprint_supervisory_authority' => null, 'imprint_chamber' => null,
        'imprint_job_title' => null, 'imprint_professional_rules' => null,
        'imprint_liquidation_note' => null, 'imprint_addendum' => [], 'imprint_link' => null,
    ]);
    WithdrawalScopeSettings::fake(['offers_goods' => false, 'offers_services' => false, 'offers_digital' => false]);
});

it('renders the neutral withdrawal form', function () {
    $response = $this->withoutVite()->get(route('withdrawal.form'));

    $response->assertOk();

    // The four fields from the prototype, by their submit name.
    $response->assertSee('name="name"', false);
    $response->assertSee('name="email"', false);
    $response->assertSee('name="orderNumber"', false);
    $response->assertSee('name="subject"', false);

    // Translated copy is rendered — not the raw translation keys.
    $response->assertSee('Ihr Vor- und Nachname');
    $response->assertSee('E-Mail-Adresse');
    $response->assertSee('Bestellnummer / Vertragsnummer');
    $response->assertSee('Pflichtfeld');
    $response->assertDontSee('wf.');

    // Honeypot field is present in the markup.
    $response->assertSee('name="website"', false);

    // Neutral theme applied by default.
    $response->assertSee('data-theme="neutral"', false);
});

it('exposes a named store route for the form action', function () {
    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertSee('action="'.route('withdrawal.store').'"', false);
});

it('applies the configured theme to the form card', function () {
    // config('revoco.theme') is backed by env('APP_THEME'); overriding the
    // resolved config value exercises the same data-theme mechanism.
    config()->set('revoco.theme', 'foo');

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('data-theme="foo"', false);
});

it('links to the AGPL source from the footer (§ 13 network notice)', function () {
    // The source link must point at the configured corresponding source and carry
    // the translated label — satisfying the AGPL-3.0 network-use offer.
    config()->set('revoco.source_url', 'https://example.test/src');

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('href="https://example.test/src"', false)
        ->assertSee('Quelltext'); // de footer label (default consumer locale)
});

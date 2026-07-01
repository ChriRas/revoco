<?php

use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;

// The form reads LocaleSettings (offered locales), LegalSettings (footer privacy
// link) and WithdrawalScopeSettings (scope copy); fake all three so this DB-less
// smoke test needs no settings table.
beforeEach(function () {
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);
    LegalSettings::fake([
        'privacy_content' => [], 'privacy_link' => null, 'fallback_order' => ['de'],
        // Imprint fields (slice-014) — empty defaults so the DB-less smoke test
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

it('serves the withdrawal form on the root route', function () {
    $this->withoutVite()->get('/')->assertOk();
});

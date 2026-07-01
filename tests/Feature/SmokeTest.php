<?php

use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;

// The form reads LocaleSettings (offered locales), LegalSettings (footer privacy
// link) and WithdrawalScopeSettings (scope copy); fake all three so this DB-less
// smoke test needs no settings table.
beforeEach(function () {
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);
    LegalSettings::fake(['privacy_content' => [], 'privacy_link' => null, 'fallback_order' => ['de']]);
    WithdrawalScopeSettings::fake(['offers_goods' => false, 'offers_services' => false, 'offers_digital' => false]);
});

it('serves the withdrawal form on the root route', function () {
    $this->withoutVite()->get('/')->assertOk();
});

<?php

use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;

// The form reads LocaleSettings (offered locales) and LegalSettings (footer privacy
// link); fake both so this DB-less smoke test needs no settings table.
beforeEach(function () {
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);
    LegalSettings::fake(['privacy_content' => [], 'privacy_link' => null, 'fallback_order' => ['de']]);
});

it('serves the withdrawal form on the root route', function () {
    $this->withoutVite()->get('/')->assertOk();
});

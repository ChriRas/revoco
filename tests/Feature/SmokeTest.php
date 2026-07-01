<?php

use App\Settings\LocaleSettings;

// The form reads the offered locales from LocaleSettings; fake it so this DB-less
// smoke test needs no settings table.
beforeEach(fn () => LocaleSettings::fake(['available' => ['de'], 'default' => 'de']));

it('serves the withdrawal form on the root route', function () {
    $this->withoutVite()->get('/')->assertOk();
});

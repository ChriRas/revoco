<?php

declare(strict_types=1);

use App\Settings\LocaleSettings;
use App\Support\ConsumerLocales;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Tests\TestCase;

uses(TestCase::class);

// ---------------------------------------------------------------------------
// ConsumerLocales — the § 356a fallback. The consumer form (GET / and the submit
// POST both run SetConsumerLocale) must never 500 on a missing/unseeded
// LocaleSettings row, so the seam falls back to the framework base locale
// (APP_LOCALE) instead of letting spatie's MissingSettings bubble up.
// ---------------------------------------------------------------------------

it('falls back to the base locale when LocaleSettings is missing', function (): void {
    config(['app.locale' => 'de']);
    app()->bind(LocaleSettings::class, fn () => throw new MissingSettings('missing'));

    expect(ConsumerLocales::default())->toBe('de');
    expect(ConsumerLocales::available())->toBe(['de']);
    // resolve() runs against the fallback set: the base locale is accepted, others rejected.
    expect(ConsumerLocales::resolve('de'))->toBe('de');
    expect(ConsumerLocales::resolve('en'))->toBeNull();
});

it('falls back to the base locale when available is somehow empty', function (): void {
    config(['app.locale' => 'en']);
    LocaleSettings::fake(['available' => [], 'default' => 'en']);

    expect(ConsumerLocales::available())->toBe(['en']);
});

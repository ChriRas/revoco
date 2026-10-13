<?php

declare(strict_types=1);

use App\Spam\SpamReasonLabels;

it('returns null for no reason', function () {
    expect(SpamReasonLabels::render(null))->toBeNull()
        ->and(SpamReasonLabels::render(' , '))->toBeNull();
});

it('renders every key as its label, in order, in de and en', function () {
    app()->setLocale('de');
    expect(SpamReasonLabels::render('fill_time_too_fast,honeypot'))
        ->toBe('Formular unplausibel schnell ausgefüllt, Verstecktes Honeypot-Feld ausgefüllt');

    app()->setLocale('en');
    expect(SpamReasonLabels::render('fill_time_too_fast,honeypot'))
        ->toBe('Form filled in implausibly fast, Hidden honeypot field filled');
});

it('keeps an unknown key raw and trims surrounding whitespace', function () {
    app()->setLocale('de');

    expect(SpamReasonLabels::render('foo_bar'))->toBe('foo_bar')
        ->and(SpamReasonLabels::render(' honeypot , foo_bar '))->toBe('Verstecktes Honeypot-Feld ausgefüllt, foo_bar');
});

it('has a label in both languages for every shipped signal key', function () {
    foreach (array_keys(config('revoco.spam.weights')) as $key) {
        foreach (['de', 'en'] as $locale) {
            expect(trans('panel.spam_signal.'.$key, [], $locale))->not->toBe('panel.spam_signal.'.$key, "{$locale}/{$key}");
        }
    }
});

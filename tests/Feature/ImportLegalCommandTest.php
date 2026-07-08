<?php

declare(strict_types=1);

use App\Settings\LegalSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature coverage for `revoco:import-legal` — the deterministic core of the
 * legal-extraction skill. Exercises the command from a JSON payload through to the
 * persisted LegalSettings, plus the validation, sanitisation and overwrite guards.
 */
uses(RefreshDatabase::class);

/** Write a JSON payload to a throwaway temp file and return its path. */
function writeLegalPayload(array $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'legal-payload-') ?: sys_get_temp_dir().'/legal-payload.json';
    file_put_contents($path, (string) json_encode($data));

    return $path;
}

it('imports imprint fields and per-locale privacy content', function (): void {
    $path = writeLegalPayload([
        'imprint' => [
            'name' => 'Muster GmbH',
            'vat_id' => 'DE123456789',
            'email' => 'kontakt@example.com',
            'address' => "Musterstraße 1\n12345 Musterstadt",
        ],
        'privacy' => ['content' => '<p>Datenschutz-Text</p>'],
    ]);

    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path])
        ->assertSuccessful();

    $s = app(LegalSettings::class)->refresh();
    expect($s->imprint_name)->toBe('Muster GmbH');
    expect($s->imprint_vat_id)->toBe('DE123456789');
    expect($s->imprint_email)->toBe('kontakt@example.com');
    expect($s->imprint_address['de'])->toContain('Musterstraße 1');
    expect($s->privacy_content['de'])->toContain('Datenschutz-Text');

    @unlink($path);
});

it('scopes per-locale fields to --locale and leaves other locales untouched', function (): void {
    $seed = app(LegalSettings::class);
    $seed->imprint_address = ['en' => 'Example Street 1'];
    $seed->save();

    $path = writeLegalPayload(['imprint' => ['address' => 'Musterstraße 1']]);
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path])->assertSuccessful();

    $s = app(LegalSettings::class)->refresh();
    expect($s->imprint_address['de'])->toBe('Musterstraße 1');
    expect($s->imprint_address['en'])->toBe('Example Street 1');

    @unlink($path);
});

it('sanitizes scraped HTML before storing it', function (): void {
    $path = writeLegalPayload(['privacy' => ['content' => '<p>Safe content</p><script>alert(1)</script>']]);
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path])->assertSuccessful();

    $stored = app(LegalSettings::class)->refresh()->privacy_content['de'];
    expect($stored)->toContain('Safe content');
    expect($stored)->not->toContain('<script>');

    @unlink($path);
});

it('rejects an unknown key and writes nothing', function (): void {
    $path = writeLegalPayload(['imprint' => ['name' => 'Muster GmbH', 'bogus' => 'x']]);
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path])
        ->expectsOutputToContain("Unknown imprint key 'bogus'")
        ->assertFailed();

    expect(app(LegalSettings::class)->refresh()->imprint_name)->toBeNull();

    @unlink($path);
});

it('rejects an invalid e-mail and writes nothing', function (): void {
    $path = writeLegalPayload(['imprint' => ['name' => 'Muster GmbH', 'email' => 'not-an-email']]);
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path])
        ->expectsOutputToContain('not a valid e-mail')
        ->assertFailed();

    expect(app(LegalSettings::class)->refresh()->imprint_name)->toBeNull();

    @unlink($path);
});

it('rejects an invalid locale', function (): void {
    $path = writeLegalPayload(['imprint' => ['name' => 'Muster GmbH']]);
    $this->artisan('revoco:import-legal', ['--locale' => 'Deutsch', '--input' => $path])
        ->expectsOutputToContain('Invalid --locale')
        ->assertFailed();

    @unlink($path);
});

it('refuses to overwrite a populated field without --overwrite', function (): void {
    $seed = app(LegalSettings::class);
    $seed->imprint_name = 'Existing GmbH';
    $seed->save();

    $path = writeLegalPayload(['imprint' => ['name' => 'New GmbH']]);
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path])
        ->expectsOutputToContain('Refusing to overwrite')
        ->assertFailed();

    expect(app(LegalSettings::class)->refresh()->imprint_name)->toBe('Existing GmbH');

    @unlink($path);
});

it('replaces a populated field with --overwrite', function (): void {
    $seed = app(LegalSettings::class);
    $seed->imprint_name = 'Existing GmbH';
    $seed->save();

    $path = writeLegalPayload(['imprint' => ['name' => 'New GmbH']]);
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path, '--overwrite' => true])
        ->assertSuccessful();

    expect(app(LegalSettings::class)->refresh()->imprint_name)->toBe('New GmbH');

    @unlink($path);
});

it('leaves fallback_order and the *_link overrides untouched', function (): void {
    $before = app(LegalSettings::class)->refresh();
    $fallbackBefore = $before->fallback_order;
    $privacyLinkBefore = $before->privacy_link;
    $imprintLinkBefore = $before->imprint_link;

    $path = writeLegalPayload([
        'imprint' => ['name' => 'Muster GmbH'],
        'privacy' => ['content' => '<p>x</p>'],
    ]);
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--input' => $path])->assertSuccessful();

    $after = app(LegalSettings::class)->refresh();
    expect($after->fallback_order)->toBe($fallbackBefore);
    expect($after->privacy_link)->toBe($privacyLinkBefore);
    expect($after->imprint_link)->toBe($imprintLinkBefore);

    @unlink($path);
});

it('fails cleanly under --no-interaction when --locale is missing', function (): void {
    $this->artisan('revoco:import-legal', ['--no-interaction' => true])->assertFailed();
});

it('fails cleanly under --no-interaction when --input is missing', function (): void {
    $this->artisan('revoco:import-legal', ['--locale' => 'de', '--no-interaction' => true])->assertFailed();
});

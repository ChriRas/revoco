<?php

declare(strict_types=1);

/**
 * Feature coverage for `revoco:make-theme` — the deterministic core of the
 * design-adoption skill. Exercises the command from options through to the
 * generated overlay + placement report, plus the validation and a11y guards.
 */

/** A throwaway output path outside the repo; removed after each test. */
function overlayTmpPath(): string
{
    $path = tempnam(sys_get_temp_dir(), 'wf-overlay-');

    return $path !== false ? $path : sys_get_temp_dir().'/wf-overlay-fallback.css';
}

it('generates a valid brand overlay to the --output file', function () {
    $out = overlayTmpPath();

    $this->artisan('revoco:make-theme', [
        '--slug' => 'acme',
        '--accent' => '#c1121f',
        '--font' => '"Inter", system-ui, sans-serif',
        '--logo-url' => 'https://acme.example/logo.svg',
        '--output' => $out,
    ])->assertSuccessful();

    expect(file_get_contents($out))
        ->toContain('.wf-card[data-theme="acme"]')
        ->toContain('--wf-accent: #c1121f;')
        ->toContain('--wf-font: "Inter", system-ui, sans-serif;');

    @unlink($out);
});

it('emits only --wf-* tokens that exist in the withdrawal.css contract', function () {
    $out = overlayTmpPath();

    $this->artisan('revoco:make-theme', [
        '--slug' => 'acme',
        '--accent' => '#c1121f',
        '--heading-fg' => '#111111',
        '--font-display' => 'Georgia, serif',
        '--output' => $out,
    ])->assertSuccessful();

    preg_match_all('/--wf-[a-z0-9-]+/', (string) file_get_contents(resource_path('css/withdrawal.css')), $contract);
    preg_match_all('/--wf-[a-z0-9-]+/', (string) file_get_contents($out), $emitted);

    foreach (array_unique($emitted[0]) as $token) {
        expect(array_unique($contract[0]))->toContain($token);
    }

    @unlink($out);
});

it('never overrides the accessibility-locked focus-ring token', function () {
    $out = overlayTmpPath();

    $this->artisan('revoco:make-theme', [
        '--slug' => 'acme',
        '--accent' => '#c1121f',
        '--output' => $out,
    ])->assertSuccessful();

    expect(file_get_contents($out))->not->toContain('--wf-focus-ring');

    @unlink($out);
});

it('prints a placement report with APP_THEME and logo wiring', function () {
    $this->artisan('revoco:make-theme', [
        '--slug' => 'acme',
        '--accent' => '#c1121f',
        '--logo-url' => 'https://acme.example/logo.svg',
    ])
        ->expectsOutputToContain('APP_THEME=acme')
        ->expectsOutputToContain('REVOCO_LOGO_URL=https://acme.example/logo.svg')
        ->assertSuccessful();
});

it('rejects an invalid colour and writes nothing', function () {
    $out = overlayTmpPath();
    @unlink($out); // the command must not create it

    $this->artisan('revoco:make-theme', [
        '--slug' => 'acme',
        '--accent' => 'red; } body { display:none',
        '--output' => $out,
    ])
        ->expectsOutputToContain('Invalid colour')
        ->assertFailed();

    expect(file_exists($out))->toBeFalse();
});

it('rejects an invalid slug', function () {
    $this->artisan('revoco:make-theme', [
        '--slug' => 'Acme Corp',
        '--accent' => '#c1121f',
    ])
        ->expectsOutputToContain('Invalid --slug')
        ->assertFailed();
});

it('rejects an invalid logo URL', function () {
    $this->artisan('revoco:make-theme', [
        '--slug' => 'acme',
        '--logo-url' => 'javascript:alert(1)',
    ])
        ->expectsOutputToContain('Invalid --logo-url')
        ->assertFailed();
});

it('warns when no brand tokens are provided', function () {
    $this->artisan('revoco:make-theme', ['--slug' => 'empty'])
        ->expectsOutputToContain('No brand tokens')
        ->assertSuccessful();
});

it('prompts for the slug when it is not provided on a TTY', function () {
    $this->artisan('revoco:make-theme', ['--accent' => '#c1121f'])
        ->expectsQuestion('Theme slug (data-theme / APP_THEME value)', 'prompted')
        ->assertSuccessful();
});

it('fails cleanly under --no-interaction when --slug is missing', function () {
    $this->artisan('revoco:make-theme', ['--no-interaction' => true])
        ->assertFailed();
});

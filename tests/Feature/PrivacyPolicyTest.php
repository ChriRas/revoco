<?php

declare(strict_types=1);

use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// /datenschutz (legal.privacy) — content per consumer locale, fallback chain,
// external-link override (302), and the neutral empty-state placeholder. The
// footer "Datenschutzerklärung" link is re-wired to this resolver.
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    // Both locales offered so the en cookie is honoured by SetConsumerLocale.
    LocaleSettings::fake(['available' => ['de', 'en'], 'default' => 'de']);
});

it('renders the privacy content in the consumer locale', function (): void {
    LegalSettings::fake([
        'privacy_content' => ['de' => '<p>Datenschutz DE Inhalt</p>', 'en' => '<p>Privacy EN content</p>'],
        'privacy_link' => null,
        'fallback_order' => ['de'],
    ]);

    $this->withoutVite()->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Datenschutzerklärung')      // localized page title
        ->assertSee('Datenschutz DE Inhalt')
        ->assertDontSee('Privacy EN content');

    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Privacy EN content')
        ->assertDontSee('Datenschutz DE Inhalt');
});

it('falls back to another locale when the requested one has no content', function (): void {
    // Only de configured; an en visitor still sees the German text via the chain.
    LegalSettings::fake([
        'privacy_content' => ['de' => '<p>Nur auf Deutsch vorhanden</p>'],
        'privacy_link' => null,
        'fallback_order' => ['de'],
    ]);

    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Nur auf Deutsch vorhanden');
});

it('302-redirects to the external override URL when one is set', function (): void {
    LegalSettings::fake([
        'privacy_content' => ['de' => '<p>wird ignoriert</p>'],
        'privacy_link' => 'https://shop.example/datenschutz',
        'fallback_order' => ['de'],
    ]);

    $this->get(route('legal.privacy'))
        ->assertRedirect('https://shop.example/datenschutz');
});

it('shows a neutral placeholder (no fabricated legal text) when nothing is configured', function (): void {
    LegalSettings::fake([
        'privacy_content' => [],
        'privacy_link' => null,
        'fallback_order' => ['de'],
    ]);

    $this->withoutVite()->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee(__('wf.legal.placeholder'))
        ->assertDontSee('Lorem')
        ->assertDontSee('wf.legal');
});

it('safe-renders the operator rich text, stripping scripts and event handlers', function (): void {
    LegalSettings::fake([
        'privacy_content' => ['de' => '<p>Sicherer <strong>Text</strong></p><script>alert(1)</script><img src=x onerror=alert(2)>'],
        'privacy_link' => null,
        'fallback_order' => ['de'],
    ]);

    $response = $this->withoutVite()->get(route('legal.privacy'))->assertOk();

    $response->assertSee('Sicherer', false);
    $response->assertSee('<strong>Text</strong>', false);   // safe markup preserved
    $response->assertDontSee('<script>', false);            // script stripped
    $response->assertDontSee('onerror', false);             // event handler stripped
});

it('points the footer privacy link at the internal route when content is set', function (): void {
    LegalSettings::fake([
        'privacy_content' => ['de' => '<p>Datenschutz</p>'],
        'privacy_link' => null,
        'fallback_order' => ['de'],
    ]);

    $internal = 'href="'.route('legal.privacy').'"';

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee($internal, false);

    $this->withoutVite()->get(route('withdrawal.success'))
        ->assertOk()
        ->assertSee($internal, false);
});

it('points the footer privacy link at the external override URL when set', function (): void {
    LegalSettings::fake([
        'privacy_content' => [],
        'privacy_link' => 'https://shop.example/datenschutz',
        'fallback_order' => ['de'],
    ]);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('href="https://shop.example/datenschutz"', false)
        ->assertDontSee('href="'.route('legal.privacy').'"', false);
});

it('never 500s and shows the placeholder when LegalSettings is unseeded (§ 356a)', function (): void {
    // Spatie loads settings lazily; simulate a missing row via a throwing binding.
    // The consumer pages must stay up: the form + success render (footer falls back
    // to the internal route), and /datenschutz shows the neutral placeholder.
    app()->bind(LegalSettings::class, fn () => throw new MissingSettings('unseeded'));

    $internal = 'href="'.route('legal.privacy').'"';

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee($internal, false);

    $this->withoutVite()->get(route('withdrawal.success'))
        ->assertOk();

    $this->withoutVite()->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee(__('wf.legal.placeholder'));
});

<?php

declare(strict_types=1);

use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;

// The shared <x-wf-footer> (Design-16, slice-011) renders on both consumer pages:
// the form (/) and the success page (/success). It reads LegalPages (imprint /
// privacy hrefs) and, on the form, WithdrawalScope + LegalContent — fake all the
// settings so these DB-less tests need no settings table.
beforeEach(function () {
    LocaleSettings::fake(['available' => ['de'], 'default' => 'de']);
    LegalSettings::fake([
        'privacy_content' => [], 'privacy_link' => null, 'fallback_order' => ['de'],
        'imprint_name' => null, 'imprint_legal_form' => null, 'imprint_represented_by' => null,
        'imprint_address' => [], 'imprint_email' => null, 'imprint_phone' => null,
        'imprint_contact_note' => null, 'imprint_register_court' => null,
        'imprint_register_number' => null, 'imprint_vat_id' => null, 'imprint_business_id' => null,
        'imprint_supervisory_authority' => null, 'imprint_chamber' => null,
        'imprint_job_title' => null, 'imprint_professional_rules' => null,
        'imprint_liquidation_note' => null, 'imprint_addendum' => [], 'imprint_link' => null,
    ]);
    WithdrawalScopeSettings::fake(['offers_goods' => false, 'offers_services' => false, 'offers_digital' => false]);
});

// Both consumer pages share the one footer component — the assertions below hold
// identically on each, proving the form/success duplication is gone.
dataset('consumer pages', [
    'form' => ['withdrawal.form'],
    'success' => ['withdrawal.success'],
]);

it('renders the GitHub source mark carrying the AGPL § 13 corresponding-source link', function (string $route) {
    config()->set('revoco.source_url', 'https://example.test/src');

    $this->withoutVite()->get(route($route))
        ->assertOk()
        // The mark anchor is the AGPL source link: configured URL, new tab, safe rel.
        ->assertSee('href="https://example.test/src"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        // English accessible name — reachable for assistive tech before the expand.
        ->assertSee('aria-label="Revoco App on GitHub"', false)
        // The retired standalone "Quelltext" text link is gone on these two pages.
        ->assertDontSee('Quelltext');
})->with('consumer pages');

it('renders the imprint and privacy links in the footer', function (string $route) {
    $this->withoutVite()->get(route($route))
        ->assertOk()
        ->assertSee('Impressum')
        ->assertSee('Datenschutzerklärung')
        // Default (no operator override) → the internal legal routes.
        ->assertSee('href="'.route('legal.imprint').'"', false)
        ->assertSee('href="'.route('legal.privacy').'"', false);
})->with('consumer pages');

it('renders exactly one shared footer component per page (no duplicated markup)', function (string $route) {
    $html = $this->withoutVite()->get(route($route))->assertOk()->getContent();

    // One <footer class="wf-foot"> and one mark row → a single shared component.
    // Match the class="…" attribute form: withdrawal.css is inlined into the page
    // <style> block, so a bare class-name substring would also hit the CSS selectors.
    expect(substr_count($html, 'class="wf-foot"'))->toBe(1)
        ->and(substr_count($html, 'class="wf-foot-mark-row"'))->toBe(1)
        // The legacy single-row footer is not emitted on the consumer pages.
        ->and($html)->not->toContain('class="wf-page-foot"');
})->with('consumer pages');

it('keeps the legacy footer on the still-out-of-scope legal pages', function () {
    // Scope-split guard (slice-011): only the form + success pages adopted the
    // Design-16 <x-wf-footer>. The legal pages deliberately keep the legacy
    // .wf-page-foot row with the "Quelltext" link — hence wf.footer.source is
    // retained. This pins that coexistence until they migrate too.
    $this->withoutVite()->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('class="wf-page-foot"', false)
        ->assertSee('Quelltext')
        ->assertDontSee('class="wf-foot"', false);
});

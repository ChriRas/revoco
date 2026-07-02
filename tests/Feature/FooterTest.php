<?php

declare(strict_types=1);

use App\Settings\LegalSettings;
use App\Settings\LocaleSettings;
use App\Settings\WithdrawalScopeSettings;

// The shared <x-wf-footer> (Design-16) renders on all four consumer pages: the
// form (/), the success page (/success), and — after slice-017 — the imprint
// (/impressum) and privacy (/datenschutz) legal pages. It reads LegalPages
// (imprint / privacy hrefs) and, on the form, WithdrawalScope + LegalContent —
// fake all the settings so these DB-less tests need no settings table.
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

// All four consumer pages share the one footer component — the assertions below
// hold identically on each, proving the footer duplication is gone project-wide.
dataset('consumer pages', [
    'form' => ['withdrawal.form'],
    'success' => ['withdrawal.success'],
    'imprint' => ['legal.imprint'],
    'privacy' => ['legal.privacy'],
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

it('has migrated the legal pages off the legacy footer', function (string $route) {
    // Migration (slice-017): the legal pages now render the shared Design-16
    // <x-wf-footer>, not the old .wf-page-foot row with its "Quelltext" link.
    // This is the inverse of the slice-011 coexistence guard, which held only
    // until this migration.
    $this->withoutVite()->get(route($route))
        ->assertOk()
        ->assertSee('class="wf-foot"', false)
        ->assertDontSee('class="wf-page-foot"', false)
        ->assertDontSee('Quelltext');
})->with([
    'imprint' => ['legal.imprint'],
    'privacy' => ['legal.privacy'],
]);

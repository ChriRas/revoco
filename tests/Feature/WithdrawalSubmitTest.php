<?php

declare(strict_types=1);

use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function withdrawalPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Erika Mustermann',
        'email' => 'erika@example.com',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'website' => '', // honeypot — empty for a legitimate submit
    ], $overrides);
}

it('stores a valid withdrawal and redirects to the success page', function () {
    $this->post(route('withdrawal.store'), withdrawalPayload())
        ->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', [
        'name' => 'Erika Mustermann',
        'email' => 'erika@example.com',
        'order_number' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'locale' => 'de',
        'spam' => false,
        'spam_reason' => null,
    ]);
});

it('persists order_number as null when omitted', function () {
    $this->post(route('withdrawal.store'), withdrawalPayload(['orderNumber' => '']));

    $this->assertDatabaseHas('withdrawals', [
        'email' => 'erika@example.com',
        'order_number' => null,
    ]);
});

it('rejects missing mandatory fields with errors and stores nothing', function () {
    $this->from(route('withdrawal.form'))
        ->post(route('withdrawal.store'), withdrawalPayload([
            'name' => '',
            'email' => '',
            'subject' => '',
        ]))
        ->assertRedirect(route('withdrawal.form'))
        ->assertSessionHasErrors(['name', 'email', 'subject']);

    $this->assertDatabaseCount('withdrawals', 0);
});

it('rejects an invalid e-mail address with the invalid-format message', function () {
    $this->post(route('withdrawal.store'), withdrawalPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors(['email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.']);

    $this->assertDatabaseCount('withdrawals', 0);
});

it('stores a honeypot submission flagged as spam, never rejecting it', function () {
    $this->post(route('withdrawal.store'), withdrawalPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', [
        'email' => 'erika@example.com',
        'spam' => true,
        'spam_reason' => 'honeypot',
    ]);
});

it('flags throttled submissions as spam but still stores them (never 429)', function () {
    // Five clean submits exhaust the per-IP window.
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('withdrawal.store'), withdrawalPayload(['email' => "user{$i}@example.com"]))
            ->assertRedirect(route('withdrawal.success'));
    }

    // The sixth is over the limit → flagged, still stored, still redirected (no 429).
    $this->post(route('withdrawal.store'), withdrawalPayload(['email' => 'over@example.com']))
        ->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', [
        'email' => 'over@example.com',
        'spam' => true,
        'spam_reason' => 'throttle',
    ]);
    $this->assertDatabaseCount('withdrawals', 6);
});

it('captures the consumer locale and an Europe/Berlin timestamp', function () {
    $this->post(route('withdrawal.store'), withdrawalPayload());

    $withdrawal = Withdrawal::firstOrFail();

    expect($withdrawal->locale)->toBe('de');
    expect(config('app.timezone'))->toBe('Europe/Berlin');
    expect($withdrawal->created_at?->timezone->getName())->toBe('Europe/Berlin');
});

it('renders the on-screen success confirmation', function () {
    $this->withoutVite()->get(route('withdrawal.success'))
        ->assertOk()
        ->assertSee('Eingang Ihres Widerrufs bestätigt')
        ->assertSee('Ihre Widerrufserklärung ist bei uns eingegangen und wird geprüft.')
        ->assertSee('Sie können dieses Fenster jetzt schließen.')
        ->assertSee('<main', false)
        ->assertDontSee('wf.');
});

it('shows styled inline errors and repopulates input after a validation error', function () {
    $this->withoutVite()
        ->from(route('withdrawal.form'))
        ->post(route('withdrawal.store'), withdrawalPayload([
            'name' => 'Max Mustermann',
            'subject' => '',
        ]))
        ->assertRedirect(route('withdrawal.form'));

    $this->withoutVite()->get(route('withdrawal.form'))
        // old() repopulation: the name survives the round-trip to the redisplayed form.
        ->assertSee('value="Max Mustermann"', false)
        // Our server-rendered, translated inline error — NOT the browser's native bubble.
        ->assertSee('Bitte geben Sie an, welche Ware, digitalen Inhalte oder Dienstleistung Sie widerrufen möchten.')
        ->assertSee('is-invalid', false)
        // The form opts out of native browser validation so the submit reaches the server.
        ->assertSee('novalidate', false);
});

it('opts the form out of native browser validation', function () {
    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertSee('novalidate', false);
});
